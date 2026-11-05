<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Dispensation;
use App\Models\DispensationItem;
use App\Models\Payment;
use App\Models\Prescription;
use App\Models\PrescriptionItem;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SaleReturn;
use App\Models\SaleReturnItem;
use App\Models\StockMovement;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PosService
{
    /**
     * Process a complete Point of Sale transaction.
     * Enforces strict FEFO stock batch allocation, split payments, COGS calculation, and prescription linkage.
     */
    public function createSale(array $data, User $cashier): Sale
    {
        return DB::transaction(function () use ($data, $cashier) {
            $pharmacyId = $cashier->pharmacy_id;
            $branchId = $data['branch_id'] ?? $cashier->branch_id;
            $customerId = $data['customer_id'] ?? null;
            $prescriptionId = $data['prescription_id'] ?? null;

            // 1. Validate Customer & Credit limits if credit is used
            $customer = null;
            if ($customerId) {
                $customer = Customer::where('pharmacy_id', $pharmacyId)
                    ->lockForUpdate()
                    ->find($customerId);
            }

            $payments = $data['payments'] ?? [];
            $creditAmount = 0.00;
            foreach ($payments as $p) {
                if (($p['payment_method'] ?? '') === 'credit') {
                    $creditAmount += (float) ($p['amount'] ?? 0);
                }
            }

            if ($creditAmount > 0) {
                if (!$customer) {
                    throw ValidationException::withMessages([
                        'customer_id' => ['Credit sale requires an identified, registered customer.'],
                    ]);
                }

                $availableCredit = $customer->credit_limit - $customer->outstanding_balance;
                if ($creditAmount > $availableCredit) {
                    throw ValidationException::withMessages([
                        'credit_amount' => [
                            "Credit amount (KES " . number_format($creditAmount, 2) . ") exceeds available credit limit (KES " . number_format($availableCredit, 2) . ")."
                        ],
                    ]);
                }
            }

            // 2. Preliminary calculations
            $itemsData = $data['items'] ?? [];
            if (empty($itemsData)) {
                throw ValidationException::withMessages([
                    'items' => ['Cannot checkout an empty cart.'],
                ]);
            }

            $saleDate = Carbon::now();
            $subtotal = 0.00;
            $totalCogs = 0.00;
            $saleItemsToInsert = [];

            // 3. FEFO Allocation and Batch Deductions
            foreach ($itemsData as $index => $item) {
                $productId = $item['product_id'];
                $requestedQty = (float) $item['quantity'];

                if ($requestedQty <= 0) {
                    throw ValidationException::withMessages([
                        "items.{$index}.quantity" => ['Quantity must be greater than zero.'],
                    ]);
                }

                $product = Product::where('pharmacy_id', $pharmacyId)->find($productId);
                if (!$product) {
                    throw ValidationException::withMessages([
                        "items.{$index}.product_id" => ['Medicine does not exist.'],
                    ]);
                }

                // Check prescription requirements
                if ($product->is_prescription_required && empty($prescriptionId) && empty($data['pharmacist_override'])) {
                    // Check if cashier has permission to dispense prescription meds
                    if (!$cashier->can('dispensing.approve')) {
                        throw ValidationException::withMessages([
                            "items.{$index}.product_id" => ["{$product->name} requires a prescription or pharmacist authorization."],
                        ]);
                    }
                }

                // Fetch active, non-expired batches for this product in strict FEFO order
                $activeBatches = ProductBatch::where('product_id', $productId)
                    ->where('branch_id', $branchId)
                    ->where('status', 'active')
                    ->where('expiry_date', '>', $saleDate->toDateString())
                    ->where('quantity_remaining', '>', 0)
                    ->orderBy('expiry_date', 'asc')
                    ->orderBy('id', 'asc')
                    ->lockForUpdate()
                    ->get();

                $totalStockAvailable = $activeBatches->sum('quantity_remaining');
                if ($totalStockAvailable < $requestedQty) {
                    throw ValidationException::withMessages([
                        "items.{$index}.quantity" => [
                            "Insufficient stock for {$product->name}. Requested: {$requestedQty}, Available: {$totalStockAvailable}."
                        ],
                    ]);
                }

                $qtyNeeded = $requestedQty;
                $defaultSellingPrice = (float) ($item['unit_selling_price'] ?? $product->selling_price);

                foreach ($activeBatches as $batch) {
                    if ($qtyNeeded <= 0) break;

                    $take = min($qtyNeeded, (float) $batch->quantity_remaining);
                    $unitPurchase = (float) $batch->purchase_price_per_unit;
                    $unitSelling = $defaultSellingPrice > 0 ? $defaultSellingPrice : (float) $batch->selling_price_per_unit;

                    // Deduct batch
                    $batch->quantity_remaining = (float) $batch->quantity_remaining - $take;
                    if ($batch->quantity_remaining <= 0) {
                        $batch->status = 'depleted';
                    }
                    $batch->save();

                    // Calculate amounts for this chunk
                    $itemSubtotal = round($take * $unitSelling, 2);
                    $itemCogs = round($take * $unitPurchase, 2);
                    $itemGrossProfit = round($itemSubtotal - $itemCogs, 2);

                    $subtotal += $itemSubtotal;
                    $totalCogs += $itemCogs;

                    $saleItemsToInsert[] = [
                        'product_id' => $productId,
                        'product_batch_id' => $batch->id,
                        'batch_number' => $batch->batch_number,
                        'expiry_date' => $batch->expiry_date,
                        'quantity' => $take,
                        'balance_after' => (float) $batch->quantity_remaining,
                        'unit_purchase_price' => $unitPurchase,
                        'unit_selling_price' => $unitSelling,
                        'discount_amount' => 0.00,
                        'tax_amount' => 0.00,
                        'subtotal' => $itemSubtotal,
                        'total_cogs' => $itemCogs,
                        'gross_profit' => $itemGrossProfit,
                    ];

                    $qtyNeeded -= $take;
                }
            }

            // 4. Calculate overall sale totals
            $discountType = $data['discount_type'] ?? 'none';
            $discountRate = (float) ($data['discount_rate'] ?? 0);
            $discountAmount = 0.00;

            if ($discountType === 'percentage' && $discountRate > 0) {
                $discountAmount = round(($subtotal * $discountRate) / 100, 2);
            } elseif ($discountType === 'fixed') {
                $discountAmount = min((float) ($data['discount_amount'] ?? 0), $subtotal);
            }

            $taxAmount = (float) ($data['tax_amount'] ?? 0.00);
            $totalAmount = round(max(0, $subtotal - $discountAmount + $taxAmount), 2);
            $grossProfit = round($totalAmount - $totalCogs, 2);

            // 5. Generate unique sequence codes
            $branchCode = $cashier->branch?->code ?? '-';
            $datePrefix = $saleDate->format('Ymd');
            $randomSuffix = strtoupper(substr(uniqid(), -4));

            $invoiceNumber = "INV-{$branchCode}-{$datePrefix}-{$randomSuffix}";
            $receiptNumber = "RCP-{$branchCode}-{$datePrefix}-{$randomSuffix}";

            // 6. Create Sale record
            $sale = Sale::create([
                'pharmacy_id' => $pharmacyId,
                'branch_id' => $branchId,
                'user_id' => $cashier->id,
                'customer_id' => $customerId,
                'prescription_id' => $prescriptionId,
                'invoice_number' => $invoiceNumber,
                'receipt_number' => $receiptNumber,
                'sale_date' => $saleDate,
                'subtotal' => $subtotal,
                'discount_type' => $discountType,
                'discount_rate' => $discountRate,
                'discount_amount' => $discountAmount,
                'tax_amount' => $taxAmount,
                'total_amount' => $totalAmount,
                'paid_amount' => 0.00,
                'balance_due' => $totalAmount,
                'cost_of_goods_sold' => $totalCogs,
                'gross_profit' => $grossProfit,
                'payment_status' => 'paid',
                'notes' => $data['notes'] ?? null,
            ]);

            // 7. Save Sale Items and Stock Movements
            foreach ($saleItemsToInsert as $itemData) {
                $saleItem = $sale->items()->create([
                    'product_id' => $itemData['product_id'],
                    'product_batch_id' => $itemData['product_batch_id'],
                    'batch_number' => $itemData['batch_number'],
                    'expiry_date' => $itemData['expiry_date'],
                    'quantity' => $itemData['quantity'],
                    'unit_purchase_price' => $itemData['unit_purchase_price'],
                    'unit_selling_price' => $itemData['unit_selling_price'],
                    'discount_amount' => $itemData['discount_amount'],
                    'tax_amount' => $itemData['tax_amount'],
                    'subtotal' => $itemData['subtotal'],
                    'total_cogs' => $itemData['total_cogs'],
                    'gross_profit' => $itemData['gross_profit'],
                ]);

                // Record stock movement ledger
                StockMovement::create([
                    'branch_id' => $branchId,
                    'product_id' => $itemData['product_id'],
                    'product_batch_id' => $itemData['product_batch_id'],
                    'user_id' => $cashier->id,
                    'movement_type' => !empty($prescriptionId) ? 'dispense_out' : 'pos_sale',
                    'quantity_delta' => -1 * abs($itemData['quantity']),
                    'balance_after' => $itemData['balance_after'],
                    'unit_cost' => $itemData['unit_purchase_price'],
                    'total_cost' => $itemData['total_cogs'],
                    'reference_type' => Sale::class,
                    'reference_id' => $sale->id,
                    'reason' => "POS Sale #{$receiptNumber}",
                    'created_at' => $saleDate,
                ]);
            }

            // 8. Process Payments (Split Payments supported)
            $liquidPaid = 0.00;
            $hasCredit = false;

            foreach ($payments as $paymentData) {
                $amount = (float) ($paymentData['amount'] ?? 0);
                if ($amount <= 0) continue;

                $method = $paymentData['payment_method'];
                $amountTendered = isset($paymentData['amount_tendered']) ? (float) $paymentData['amount_tendered'] : $amount;
                $changeReturned = max(0.00, round($amountTendered - $amount, 2));

                Payment::create([
                    'pharmacy_id' => $pharmacyId,
                    'branch_id' => $branchId,
                    'payable_type' => Sale::class,
                    'payable_id' => $sale->id,
                    'payment_method' => $method,
                    'amount' => $amount,
                    'amount_tendered' => $amountTendered,
                    'change_returned' => $changeReturned,
                    'reference_number' => $paymentData['reference_number'] ?? null,
                    'received_by' => $cashier->id,
                    'payment_date' => $saleDate,
                    'notes' => $paymentData['notes'] ?? null,
                ]);

                if ($method === 'credit') {
                    $hasCredit = true;
                    if ($customer) {
                        $customer->outstanding_balance = (float) $customer->outstanding_balance + $amount;
                        $customer->save();
                    }
                } else {
                    $liquidPaid += $amount;
                }
            }

            // Update sale payment status
            $balanceDue = max(0.00, round($totalAmount - $liquidPaid, 2));
            $sale->paid_amount = $liquidPaid;
            $sale->balance_due = $balanceDue;

            if ($balanceDue > 0) {
                $sale->payment_status = $hasCredit ? 'credit' : 'partial';
            } else {
                $sale->payment_status = 'paid';
            }
            $sale->save();

            // 9. If linked to Prescription: Update prescription items & create dispensation record
            if ($prescriptionId) {
                $prescription = Prescription::with('items')->find($prescriptionId);
                if ($prescription) {
                    $allFullyDispensed = true;

                    $dispensation = Dispensation::create([
                        'pharmacy_id' => $pharmacyId,
                        'branch_id' => $branchId,
                        'prescription_id' => $prescription->id,
                        'sale_id' => $sale->id,
                        'customer_id' => $customerId,
                        'patient_name' => $prescription->patient_name,
                        'dispensed_by' => $cashier->id,
                        'dispensation_date' => $saleDate,
                        'pharmacist_notes' => $data['pharmacist_notes'] ?? 'Dispensed via POS counter',
                        'verification_status' => 'verified',
                    ]);

                    foreach ($sale->items as $sItem) {
                        $pItem = $prescription->items->firstWhere('product_id', $sItem->product_id);
                        if ($pItem) {
                            $newDispensed = (float) $pItem->quantity_dispensed + (float) $sItem->quantity;
                            $pItem->quantity_dispensed = $newDispensed;
                            $pItem->remaining_quantity = max(0, (float) $pItem->quantity_prescribed - $newDispensed);
                            $pItem->save();

                            $dispensation->items()->create([
                                'prescription_item_id' => $pItem->id,
                                'product_id' => $sItem->product_id,
                                'product_batch_id' => $sItem->product_batch_id,
                                'batch_number' => $sItem->batch_number,
                                'quantity_dispensed' => $sItem->quantity,
                                'dosage_instructions' => $pItem->dosage_instruction,
                            ]);
                        }
                    }

                    // Refresh prescription items to determine overall prescription status
                    $prescription->refresh();
                    $remainingTotal = $prescription->items->sum('remaining_quantity');

                    if ($remainingTotal <= 0) {
                        $prescription->status = 'fully_dispensed';
                    } else {
                        $prescription->status = 'partially_dispensed';
                    }
                    $prescription->save();
                }
            }

            // 10. Audit Log
            AuditLog::create([
                'pharmacy_id' => $pharmacyId,
                'branch_id' => $branchId,
                'user_id' => $cashier->id,
                'action' => 'pos.sale_completed',
                'description' => "Completed sale #{$receiptNumber} total KES {$totalAmount}",
                'ip_address' => request()->ip() ?? '127.0.0.1',
                'user_agent' => request()->userAgent() ?? 'CLI/System',
            ]);

            // 11. Post Journal Entry in General Ledger
            try {
                app(\App\Services\AccountingService::class)->postSaleJournalEntry($sale);
            } catch (\Exception $e) {
                \Log::warning("Could not auto-post journal entry for sale #{$sale->receipt_number}: " . $e->getMessage());
            }

            return $sale->load(['items.product', 'items.batch', 'payments', 'customer', 'cashier']);
        });
    }

    /**
     * Process return/refund for a previously completed sale.
     */
    public function processReturn(Sale $sale, array $itemsData, string $reason, string $refundMethod, User $user): SaleReturn
    {
        return DB::transaction(function () use ($sale, $itemsData, $reason, $refundMethod, $user) {
            $pharmacyId = $sale->pharmacy_id;
            $branchId = $sale->branch_id;
            $totalRefund = 0.00;

            $datePrefix = Carbon::now()->format('Ymd');
            $returnNumber = "RET-{$sale->branch->code}-{$datePrefix}-" . strtoupper(substr(uniqid(), -4));

            $saleReturn = SaleReturn::create([
                'pharmacy_id' => $pharmacyId,
                'branch_id' => $branchId,
                'sale_id' => $sale->id,
                'user_id' => $user->id,
                'return_number' => $returnNumber,
                'total_refund_amount' => 0.00,
                'reason' => $reason,
                'refund_method' => $refundMethod,
                'status' => 'completed',
            ]);

            foreach ($itemsData as $index => $item) {
                $saleItemId = $item['sale_item_id'];
                $returnQty = (float) $item['quantity'];
                $condition = $item['condition'] ?? 'resellable';

                $saleItem = SaleItem::where('sale_id', $sale->id)->find($saleItemId);
                if (!$saleItem) {
                    throw ValidationException::withMessages([
                        "items.{$index}" => ['Sale item not found on this receipt.'],
                    ]);
                }

                if ($returnQty <= 0 || $returnQty > (float) $saleItem->quantity) {
                    throw ValidationException::withMessages([
                        "items.{$index}.quantity" => ["Invalid return quantity for {$saleItem->product->name}."],
                    ]);
                }

                $unitRefundPrice = (float) $saleItem->unit_selling_price;
                $subtotalRefund = round($returnQty * $unitRefundPrice, 2);
                $totalRefund += $subtotalRefund;

                // Create return item
                $saleReturn->items()->create([
                    'sale_item_id' => $saleItem->id,
                    'product_id' => $saleItem->product_id,
                    'product_batch_id' => $saleItem->product_batch_id,
                    'quantity' => $returnQty,
                    'unit_refund_price' => $unitRefundPrice,
                    'subtotal_refund' => $subtotalRefund,
                    'condition' => $condition,
                ]);

                // Restore batch if resellable
                if ($condition === 'resellable') {
                    $batch = ProductBatch::find($saleItem->product_batch_id);
                    if ($batch) {
                        $batch->quantity_remaining = (float) $batch->quantity_remaining + $returnQty;
                        if ($batch->status === 'depleted') {
                            $batch->status = 'active';
                        }
                        $batch->save();

                        StockMovement::create([
                            'branch_id' => $branchId,
                            'product_id' => $saleItem->product_id,
                            'product_batch_id' => $batch->id,
                            'user_id' => $user->id,
                            'movement_type' => 'sale_return_restock',
                            'quantity_delta' => $returnQty,
                            'balance_after' => (float) $batch->quantity_remaining,
                            'unit_cost' => (float) $batch->purchase_price_per_unit,
                            'total_cost' => round($returnQty * (float) $batch->purchase_price_per_unit, 2),
                            'reference_type' => SaleReturn::class,
                            'reference_id' => $saleReturn->id,
                            'reason' => "Restocked returned goods #{$returnNumber}",
                            'created_at' => Carbon::now(),
                        ]);
                    }
                } else {
                    // Damaged / Expired return - log waste movement
                    StockMovement::create([
                        'branch_id' => $branchId,
                        'product_id' => $saleItem->product_id,
                        'product_batch_id' => $saleItem->product_batch_id,
                        'user_id' => $user->id,
                        'movement_type' => 'damaged_scrap',
                        'quantity_delta' => 0,
                        'balance_after' => (float) ($saleItem->batch?->quantity_remaining ?? 0),
                        'unit_cost' => (float) $saleItem->unit_purchase_price,
                        'total_cost' => round($returnQty * (float) $saleItem->unit_purchase_price, 2),
                        'reference_type' => SaleReturn::class,
                        'reference_id' => $saleReturn->id,
                        'reason' => "Return received as damaged/expired scrap #{$returnNumber}",
                        'created_at' => Carbon::now(),
                    ]);
                }
            }

            $saleReturn->total_refund_amount = $totalRefund;
            $saleReturn->save();

            // Adjust customer credit if customer has outstanding debt and refund method is credit balance
            if ($sale->customer_id && $refundMethod === 'credit_balance') {
                $customer = Customer::find($sale->customer_id);
                if ($customer) {
                    $customer->outstanding_balance = max(0, (float) $customer->outstanding_balance - $totalRefund);
                    $customer->save();
                }
            }

            // Update sale status
            $sale->payment_status = 'refunded';
            $sale->save();

            AuditLog::create([
                'pharmacy_id' => $pharmacyId,
                'branch_id' => $branchId,
                'user_id' => $user->id,
                'action' => 'pos.sale_returned',
                'description' => "Processed return #{$returnNumber} for sale #{$sale->receipt_number} refunding KES {$totalRefund}",
                'ip_address' => request()->ip() ?? '127.0.0.1',
                'user_agent' => request()->userAgent() ?? 'CLI/System',
            ]);

            return $saleReturn->load(['items.product', 'items.batch']);
        });
    }
}
