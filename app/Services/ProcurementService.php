<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\GoodsReceivedNote;
use App\Models\GoodsReceivedNoteItem;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseReturn;
use App\Models\PurchaseReturnItem;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\SupplierInvoice;
use App\Models\SupplierPayment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProcurementService
{
    /**
     * Create a new Purchase Order with ordered line items.
     */
    public function createPurchaseOrder(array $data, User $user): PurchaseOrder
    {
        return DB::transaction(function () use ($data, $user) {
            $pharmacyId = $user->pharmacy_id;
            $branchId = $data['branch_id'] ?? $user->branch_id;
            $supplierId = $data['supplier_id'];

            Supplier::where('pharmacy_id', $pharmacyId)->findOrFail($supplierId);

            // Generate unique PO Number: PO-{BRANCH}-{YYYYMMDD}-{INDEX}
            $datePrefix = date('Ymd');
            $countToday = PurchaseOrder::where('pharmacy_id', $pharmacyId)
                ->whereDate('created_at', today())
                ->count();
            $poNumber = sprintf('PO-%d-%s-%04d', $branchId, $datePrefix, $countToday + 1);

            $itemsData = $data['items'] ?? [];
            if (empty($itemsData)) {
                throw ValidationException::withMessages([
                    'items' => ['At least one item is required to create a purchase order.'],
                ]);
            }

            $subtotal = 0.00;
            $totalTax = 0.00;
            $totalDiscount = 0.00;
            $calculatedItems = [];

            foreach ($itemsData as $item) {
                $product = Product::where('pharmacy_id', $pharmacyId)->findOrFail($item['product_id']);
                $qtyOrdered = (float) $item['quantity_ordered'];
                $unitCost = (float) ($item['unit_cost'] ?? $product->purchase_price);
                $itemDiscount = (float) ($item['discount_amount'] ?? 0.00);
                $itemTax = (float) ($item['tax_amount'] ?? 0.00);
                $itemSubtotal = round(($qtyOrdered * $unitCost) - $itemDiscount + $itemTax, 2);

                $subtotal += ($qtyOrdered * $unitCost);
                $totalDiscount += $itemDiscount;
                $totalTax += $itemTax;

                $calculatedItems[] = [
                    'product_id' => $product->id,
                    'quantity_ordered' => $qtyOrdered,
                    'quantity_received' => 0.00,
                    'unit_cost' => $unitCost,
                    'discount_amount' => $itemDiscount,
                    'tax_amount' => $itemTax,
                    'subtotal' => $itemSubtotal,
                ];
            }

            $shippingCost = (float) ($data['shipping_cost'] ?? 0.00);
            $totalAmount = round($subtotal - $totalDiscount + $totalTax + $shippingCost, 2);

            $po = PurchaseOrder::create([
                'pharmacy_id' => $pharmacyId,
                'branch_id' => $branchId,
                'supplier_id' => $supplierId,
                'user_id' => $user->id,
                'po_number' => $poNumber,
                'order_date' => $data['order_date'] ?? now()->toDateString(),
                'expected_delivery_date' => $data['expected_delivery_date'] ?? null,
                'subtotal' => $subtotal,
                'discount_amount' => $totalDiscount,
                'tax_amount' => $totalTax,
                'shipping_cost' => $shippingCost,
                'total_amount' => $totalAmount,
                'status' => $data['status'] ?? 'ordered',
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($calculatedItems as $cItem) {
                $po->items()->create($cItem);
            }

            AuditLog::create([
                'branch_id' => $branchId,
                'user_id' => $user->id,
                'action' => 'procurement.po_created',
                'description' => "Created Purchase Order {$poNumber} for KES {$totalAmount}",
                'ip_address' => request()->ip() ?? '127.0.0.1',
                'user_agent' => request()->userAgent() ?? 'CLI/System',
            ]);

            return $po->load(['items.product', 'supplier', 'creator', 'branch']);
        });
    }

    /**
     * Update Purchase Order status.
     */
    public function updatePurchaseOrderStatus(PurchaseOrder $po, string $status, User $user): PurchaseOrder
    {
        $validStatuses = ['draft', 'ordered', 'partially_received', 'received', 'cancelled'];
        if (!in_array($status, $validStatuses, true)) {
            throw ValidationException::withMessages([
                'status' => ["Invalid purchase order status: {$status}."],
            ]);
        }

        $po->status = $status;
        $po->save();

        AuditLog::create([
            'branch_id' => $po->branch_id,
            'user_id' => $user->id,
            'action' => 'procurement.po_status_updated',
            'description' => "Updated Purchase Order {$po->po_number} status to {$status}",
            'ip_address' => request()->ip() ?? '127.0.0.1',
            'user_agent' => request()->userAgent() ?? 'CLI/System',
        ]);

        return $po;
    }

    /**
     * Receive inventory stock via Goods Received Note (GRN).
     * Validates expiry dates, increments/creates batches, logs stock movements,
     * updates PO received quantities and status, creates supplier invoice, and increments AP.
     */
    public function receiveStock(array $data, User $user): GoodsReceivedNote
    {
        return DB::transaction(function () use ($data, $user) {
            $pharmacyId = $user->pharmacy_id;
            $branchId = $data['branch_id'] ?? $user->branch_id;
            $supplierId = $data['supplier_id'];

            $supplier = Supplier::where('pharmacy_id', $pharmacyId)->lockForUpdate()->findOrFail($supplierId);

            $purchaseOrderId = $data['purchase_order_id'] ?? null;
            $purchaseOrder = null;
            if ($purchaseOrderId) {
                $purchaseOrder = PurchaseOrder::where('pharmacy_id', $pharmacyId)
                    ->with('items')
                    ->findOrFail($purchaseOrderId);
            }

            // Generate GRN Number: GRN-{BRANCH}-{YYYYMMDD}-{INDEX}
            $datePrefix = date('Ymd');
            $countToday = GoodsReceivedNote::where('pharmacy_id', $pharmacyId)
                ->whereDate('created_at', today())
                ->count();
            $grnNumber = sprintf('GRN-%d-%s-%04d', $branchId, $datePrefix, $countToday + 1);

            $grn = GoodsReceivedNote::create([
                'pharmacy_id' => $pharmacyId,
                'branch_id' => $branchId,
                'supplier_id' => $supplierId,
                'purchase_order_id' => $purchaseOrderId,
                'received_by' => $user->id,
                'grn_number' => $grnNumber,
                'delivery_note_number' => $data['delivery_note_number'] ?? null,
                'supplier_invoice_number' => $data['supplier_invoice_number'] ?? null,
                'received_date' => $data['received_date'] ?? now(),
                'total_received_value' => 0.00,
                'notes' => $data['notes'] ?? null,
            ]);

            $itemsData = $data['items'] ?? [];
            if (empty($itemsData)) {
                throw ValidationException::withMessages([
                    'items' => ['At least one stock item must be received in a GRN.'],
                ]);
            }

            $totalReceivedValue = 0.00;

            foreach ($itemsData as $item) {
                $productId = $item['product_id'];
                $product = Product::where('pharmacy_id', $pharmacyId)->findOrFail($productId);
                $batchNumber = trim($item['batch_number']);
                $qtyReceived = (float) $item['quantity_received'];
                $unitPurchasePrice = (float) $item['unit_purchase_price'];
                $unitSellingPrice = (float) ($item['unit_selling_price'] ?? ($product->selling_price > 0 ? $product->selling_price : $unitPurchasePrice * 1.33));
                $expiryDate = Carbon::parse($item['expiry_date'])->toDateString();
                $manufacturingDate = !empty($item['manufacturing_date']) ? Carbon::parse($item['manufacturing_date'])->toDateString() : null;

                // Enforce safety: do not accept already expired medicines!
                if (Carbon::parse($expiryDate)->isPast()) {
                    throw ValidationException::withMessages([
                        'items' => ["Batch {$batchNumber} for {$product->brand_name} has already expired ({$expiryDate}). Cannot receive expired stock."],
                    ]);
                }

                if ($qtyReceived <= 0) {
                    throw ValidationException::withMessages([
                        'items' => ["Received quantity must be greater than zero."],
                    ]);
                }

                // Check if identical batch exists in this branch
                $batch = ProductBatch::where('branch_id', $branchId)
                    ->where('product_id', $productId)
                    ->where('batch_number', $batchNumber)
                    ->lockForUpdate()
                    ->first();

                if ($batch) {
                    $batch->quantity_received += $qtyReceived;
                    $batch->quantity_remaining += $qtyReceived;
                    $batch->purchase_price_per_unit = $unitPurchasePrice;
                    $batch->selling_price_per_unit = $unitSellingPrice;
                    $batch->status = 'active';
                    $batch->save();
                } else {
                    $batch = ProductBatch::create([
                        'branch_id' => $branchId,
                        'product_id' => $productId,
                        'supplier_id' => $supplierId,
                        'batch_number' => $batchNumber,
                        'manufacturing_date' => $manufacturingDate,
                        'expiry_date' => $expiryDate,
                        'quantity_received' => $qtyReceived,
                        'quantity_remaining' => $qtyReceived,
                        'purchase_price_per_unit' => $unitPurchasePrice,
                        'selling_price_per_unit' => $unitSellingPrice,
                        'status' => 'active',
                        'notes' => $item['notes'] ?? null,
                    ]);
                }

                // Update product baseline purchase and selling prices
                $product->purchase_price = $unitPurchasePrice;
                if ($unitSellingPrice > 0) {
                    $product->selling_price = $unitSellingPrice;
                }
                $product->save();

                $subtotalCost = round($qtyReceived * $unitPurchasePrice, 2);
                $totalReceivedValue += $subtotalCost;

                // Create GRN line item
                $poItemId = $item['purchase_order_item_id'] ?? null;
                $grn->items()->create([
                    'purchase_order_item_id' => $poItemId,
                    'product_id' => $productId,
                    'product_batch_id' => $batch->id,
                    'batch_number' => $batchNumber,
                    'manufacturing_date' => $manufacturingDate,
                    'expiry_date' => $expiryDate,
                    'quantity_received' => $qtyReceived,
                    'unit_purchase_price' => $unitPurchasePrice,
                    'unit_selling_price' => $unitSellingPrice,
                    'subtotal_cost' => $subtotalCost,
                ]);

                // Create Stock Movement Ledger entry
                StockMovement::create([
                    'branch_id' => $branchId,
                    'product_id' => $productId,
                    'product_batch_id' => $batch->id,
                    'user_id' => $user->id,
                    'movement_type' => 'purchase_receipt',
                    'quantity_delta' => $qtyReceived,
                    'balance_after' => $batch->quantity_remaining,
                    'unit_cost' => $unitPurchasePrice,
                    'total_cost' => $subtotalCost,
                    'reference_type' => GoodsReceivedNote::class,
                    'reference_id' => $grn->id,
                    'reason' => "Stock received via GRN {$grnNumber} (Batch: {$batchNumber})",
                    'created_at' => now(),
                ]);

                // If linked to PO item, increment its received count
                if ($poItemId && $purchaseOrder) {
                    $poItem = $purchaseOrder->items->firstWhere('id', $poItemId);
                    if ($poItem) {
                        $poItem->quantity_received = (float) $poItem->quantity_received + $qtyReceived;
                        $poItem->save();
                    }
                }
            }

            // Update GRN total
            $grn->total_received_value = $totalReceivedValue;
            $grn->save();

            // If linked to a PO, determine if all items are fully received
            if ($purchaseOrder) {
                $purchaseOrder->refresh();
                $allReceived = true;
                foreach ($purchaseOrder->items as $pItem) {
                    if ((float) $pItem->quantity_received < (float) $pItem->quantity_ordered) {
                        $allReceived = false;
                        break;
                    }
                }
                $purchaseOrder->status = $allReceived ? 'received' : 'partially_received';
                $purchaseOrder->save();
            }

            // Auto-generate Supplier Invoice for Accounts Payable
            $invoiceNumber = !empty($data['supplier_invoice_number'])
                ? $data['supplier_invoice_number']
                : sprintf('INV-SUP-%d-%s-%04d', $supplierId, $datePrefix, $grn->id);

            // Avoid collision if invoice already exists
            $existingInvoice = SupplierInvoice::where('pharmacy_id', $pharmacyId)
                ->where('supplier_id', $supplierId)
                ->where('invoice_number', $invoiceNumber)
                ->first();

            if (!$existingInvoice) {
                $taxAmount = (float) ($data['tax_amount'] ?? 0.00);
                $invoiceTotal = round($totalReceivedValue + $taxAmount, 2);
                $receivedDate = Carbon::parse($data['received_date'] ?? now());
                $dueDate = $receivedDate->copy()->addDays($supplier->payment_terms_days ?: 30);

                SupplierInvoice::create([
                    'pharmacy_id' => $pharmacyId,
                    'branch_id' => $branchId,
                    'supplier_id' => $supplierId,
                    'goods_received_note_id' => $grn->id,
                    'purchase_order_id' => $purchaseOrderId,
                    'invoice_number' => $invoiceNumber,
                    'invoice_date' => $receivedDate->toDateString(),
                    'due_date' => $dueDate->toDateString(),
                    'subtotal' => $totalReceivedValue,
                    'tax_amount' => $taxAmount,
                    'total_amount' => $invoiceTotal,
                    'paid_amount' => 0.00,
                    'balance_due' => $invoiceTotal,
                    'payment_status' => 'unpaid',
                    'notes' => "Auto-created from GRN {$grnNumber}",
                ]);

                // Increment Accounts Payable (supplier balance)
                $supplier->outstanding_balance = (float) $supplier->outstanding_balance + $invoiceTotal;
                $supplier->save();
            }

            AuditLog::create([
                'branch_id' => $branchId,
                'user_id' => $user->id,
                'action' => 'procurement.grn_completed',
                'description' => "Received GRN {$grnNumber} with " . count($itemsData) . " items, total KES {$totalReceivedValue}",
                'ip_address' => request()->ip() ?? '127.0.0.1',
                'user_agent' => request()->userAgent() ?? 'CLI/System',
            ]);

            // Auto-post journal entry to General Ledger: Inventory Asset Dr, Accounts Payable Cr
            try {
                app(\App\Services\AccountingService::class)->postGrnJournalEntry($grn);
            } catch (\Exception $e) {
                \Log::warning("Could not auto-post journal entry for GRN #{$grn->grn_number}: " . $e->getMessage());
            }

            return $grn->load(['items.product', 'items.batch', 'supplier', 'purchaseOrder', 'receiver', 'supplierInvoices']);
        });
    }

    /**
     * Record payment to a supplier against invoices or on-account.
     * Decrements Accounts Payable and updates invoice balances.
     */
    public function recordSupplierPayment(array $data, User $user): SupplierPayment
    {
        return DB::transaction(function () use ($data, $user) {
            $pharmacyId = $user->pharmacy_id;
            $branchId = $data['branch_id'] ?? $user->branch_id;
            $supplierId = $data['supplier_id'];
            $amount = (float) $data['amount'];

            if ($amount <= 0) {
                throw ValidationException::withMessages([
                    'amount' => ['Payment amount must be greater than zero.'],
                ]);
            }

            $supplier = Supplier::where('pharmacy_id', $pharmacyId)->lockForUpdate()->findOrFail($supplierId);

            $datePrefix = date('Ymd');
            $countToday = SupplierPayment::where('pharmacy_id', $pharmacyId)
                ->whereDate('created_at', today())
                ->count();
            $paymentNumber = sprintf('SPAY-%d-%s-%04d', $branchId, $datePrefix, $countToday + 1);

            $invoice = null;
            if (!empty($data['supplier_invoice_id'])) {
                $invoice = SupplierInvoice::where('pharmacy_id', $pharmacyId)
                    ->where('supplier_id', $supplierId)
                    ->lockForUpdate()
                    ->findOrFail($data['supplier_invoice_id']);

                $invoice->paid_amount = (float) $invoice->paid_amount + $amount;
                $invoice->balance_due = max(0.00, round((float) $invoice->total_amount - (float) $invoice->paid_amount, 2));
                $invoice->payment_status = ($invoice->balance_due <= 0.001) ? 'paid' : 'partial';
                $invoice->save();
            }

            // Decrement supplier outstanding balance
            $supplier->outstanding_balance = max(0.00, round((float) $supplier->outstanding_balance - $amount, 2));
            $supplier->save();

            $payment = SupplierPayment::create([
                'pharmacy_id' => $pharmacyId,
                'branch_id' => $branchId,
                'supplier_id' => $supplierId,
                'supplier_invoice_id' => $invoice ? $invoice->id : null,
                'payment_number' => $paymentNumber,
                'payment_date' => $data['payment_date'] ?? now(),
                'amount' => $amount,
                'payment_method' => $data['payment_method'],
                'reference_number' => $data['reference_number'] ?? null,
                'paid_by' => $user->id,
                'notes' => $data['notes'] ?? null,
            ]);

            AuditLog::create([
                'branch_id' => $branchId,
                'user_id' => $user->id,
                'action' => 'procurement.supplier_payment',
                'description' => "Recorded supplier payment {$paymentNumber} of KES {$amount} to {$supplier->name}",
                'ip_address' => request()->ip() ?? '127.0.0.1',
                'user_agent' => request()->userAgent() ?? 'CLI/System',
            ]);

            // Auto-post journal entry: Accounts Payable Dr, Cash/Bank Cr
            try {
                app(\App\Services\AccountingService::class)->postSupplierPaymentJournalEntry($payment);
            } catch (\Exception $e) {
                \Log::warning("Could not auto-post journal entry for supplier payment #{$payment->payment_number}: " . $e->getMessage());
            }

            return $payment->load(['supplier', 'invoice', 'payer']);
        });
    }

    /**
     * Process return of inventory stock to a supplier.
     * Deducts batch stock, records stock movement, credits supplier balance.
     */
    public function processPurchaseReturn(array $data, User $user): PurchaseReturn
    {
        return DB::transaction(function () use ($data, $user) {
            $pharmacyId = $user->pharmacy_id;
            $branchId = $data['branch_id'] ?? $user->branch_id;
            $supplierId = $data['supplier_id'];

            $supplier = Supplier::where('pharmacy_id', $pharmacyId)->lockForUpdate()->findOrFail($supplierId);

            $datePrefix = date('Ymd');
            $countToday = PurchaseReturn::where('pharmacy_id', $pharmacyId)
                ->whereDate('created_at', today())
                ->count();
            $returnNumber = sprintf('PR-%d-%s-%04d', $branchId, $datePrefix, $countToday + 1);

            $purchaseReturn = PurchaseReturn::create([
                'pharmacy_id' => $pharmacyId,
                'branch_id' => $branchId,
                'supplier_id' => $supplierId,
                'goods_received_note_id' => $data['goods_received_note_id'] ?? null,
                'processed_by' => $user->id,
                'return_number' => $returnNumber,
                'return_date' => $data['return_date'] ?? now(),
                'total_amount' => 0.00,
                'reason' => $data['reason'] ?? 'Goods returned to supplier',
                'status' => 'completed',
            ]);

            $itemsData = $data['items'] ?? [];
            if (empty($itemsData)) {
                throw ValidationException::withMessages([
                    'items' => ['At least one item must be specified for a purchase return.'],
                ]);
            }

            $totalReturnAmount = 0.00;

            foreach ($itemsData as $item) {
                $batchId = $item['product_batch_id'];
                $qty = (float) $item['quantity'];

                if ($qty <= 0) {
                    throw ValidationException::withMessages([
                        'items' => ['Returned quantity must be greater than zero.'],
                    ]);
                }

                $batch = ProductBatch::where('branch_id', $branchId)
                    ->lockForUpdate()
                    ->findOrFail($batchId);

                if ((float) $batch->quantity_remaining < $qty) {
                    throw ValidationException::withMessages([
                        'items' => ["Cannot return {$qty} units of batch {$batch->batch_number}; only {$batch->quantity_remaining} units are available in stock."],
                    ]);
                }

                $unitCost = (float) ($item['unit_cost'] ?? $batch->purchase_price_per_unit);
                $subtotal = round($qty * $unitCost, 2);
                $totalReturnAmount += $subtotal;

                // Deduct stock from batch
                $batch->quantity_remaining = (float) $batch->quantity_remaining - $qty;
                if ($batch->quantity_remaining <= 0) {
                    $batch->status = 'depleted';
                }
                $batch->save();

                // Create Purchase Return Item
                $purchaseReturn->items()->create([
                    'product_id' => $batch->product_id,
                    'product_batch_id' => $batch->id,
                    'quantity' => $qty,
                    'unit_cost' => $unitCost,
                    'subtotal' => $subtotal,
                    'reason' => $item['reason'] ?? $purchaseReturn->reason,
                ]);

                // Create Stock Movement Ledger entry
                StockMovement::create([
                    'branch_id' => $branchId,
                    'product_id' => $batch->product_id,
                    'product_batch_id' => $batch->id,
                    'user_id' => $user->id,
                    'movement_type' => 'purchase_return',
                    'quantity_delta' => -$qty,
                    'balance_after' => $batch->quantity_remaining,
                    'unit_cost' => $unitCost,
                    'total_cost' => $subtotal,
                    'reference_type' => PurchaseReturn::class,
                    'reference_id' => $purchaseReturn->id,
                    'reason' => "Purchase return to supplier: " . ($item['reason'] ?? $purchaseReturn->reason),
                    'created_at' => now(),
                ]);
            }

            $purchaseReturn->total_amount = $totalReturnAmount;
            $purchaseReturn->save();

            // Decrement supplier outstanding balance (credit note)
            $supplier->outstanding_balance = max(0.00, round((float) $supplier->outstanding_balance - $totalReturnAmount, 2));
            $supplier->save();

            AuditLog::create([
                'branch_id' => $branchId,
                'user_id' => $user->id,
                'action' => 'procurement.purchase_return',
                'description' => "Processed purchase return {$returnNumber} of KES {$totalReturnAmount} to {$supplier->name}",
                'ip_address' => request()->ip() ?? '127.0.0.1',
                'user_agent' => request()->userAgent() ?? 'CLI/System',
            ]);

            return $purchaseReturn->load(['items.product', 'items.batch', 'supplier', 'processor']);
        });
    }
}
