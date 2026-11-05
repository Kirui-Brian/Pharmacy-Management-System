<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\StockAdjustment;
use App\Models\StockAdjustmentItem;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class InventoryService
{
    /**
     * Allocate stock from eligible unexpired batches using FEFO (First-Expiry, First-Out).
     * Locks rows for update within a transaction to prevent race conditions.
     *
     * @throws RuntimeException If insufficient unexpired stock exists.
     */
    public function allocateFefoStock(int $branchId, int $productId, float $requestedQuantity): array
    {
        if ($requestedQuantity <= 0) {
            throw new InvalidArgumentException("Requested quantity must be greater than zero.");
        }

        $batches = ProductBatch::where('branch_id', $branchId)
            ->where('product_id', $productId)
            ->where('status', 'active')
            ->where('quantity_remaining', '>', 0)
            ->where('expiry_date', '>', now()->toDateString())
            ->orderBy('expiry_date', 'asc')
            ->orderBy('id', 'asc')
            ->lockForUpdate()
            ->get();

        $allocations = [];
        $remainingToFulfill = $requestedQuantity;

        foreach ($batches as $batch) {
            if ($remainingToFulfill <= 0) {
                break;
            }

            $takeQty = min($batch->quantity_remaining, $remainingToFulfill);

            $allocations[] = [
                'batch_id' => $batch->id,
                'batch_number' => $batch->batch_number,
                'expiry_date' => $batch->expiry_date->toDateString(),
                'quantity' => $takeQty,
                'unit_cost' => $batch->purchase_price_per_unit,
                'unit_price' => $batch->selling_price_per_unit,
                'cogs_total' => round($takeQty * $batch->purchase_price_per_unit, 4),
            ];

            $remainingToFulfill -= $takeQty;
        }

        if ($remainingToFulfill > 0.0001) {
            $product = Product::find($productId);
            $available = $requestedQuantity - $remainingToFulfill;
            throw new RuntimeException(
                "Insufficient unexpired stock for '{$product?->name}'. Requested: {$requestedQuantity}, Available eligible: {$available}."
            );
        }

        return $allocations;
    }

    /**
     * Ingest or receive new batch stock.
     */
    public function receiveBatch(
        int $branchId,
        int $productId,
        string $batchNumber,
        float $quantity,
        float $purchasePricePerUnit,
        float $sellingPricePerUnit,
        ?string $manufacturingDate,
        string $expiryDate,
        ?int $supplierId = null,
        ?string $reason = 'Stock Received'
    ): ProductBatch {
        if ($quantity <= 0) {
            throw new InvalidArgumentException("Quantity received must be greater than zero.");
        }

        return DB::transaction(function () use (
            $branchId, $productId, $batchNumber, $quantity, $purchasePricePerUnit,
            $sellingPricePerUnit, $manufacturingDate, $expiryDate, $supplierId, $reason
        ) {
            $batch = ProductBatch::firstOrNew([
                'branch_id' => $branchId,
                'product_id' => $productId,
                'batch_number' => $batchNumber,
            ]);

            if ($batch->exists) {
                $batch->quantity_received += $quantity;
                $batch->quantity_remaining += $quantity;
                $batch->purchase_price_per_unit = $purchasePricePerUnit;
                $batch->selling_price_per_unit = $sellingPricePerUnit;
                $batch->expiry_date = $expiryDate;
                if ($manufacturingDate) $batch->manufacturing_date = $manufacturingDate;
                if ($supplierId) $batch->supplier_id = $supplierId;
                if ($batch->status === 'depleted') $batch->status = 'active';
                $batch->save();
            } else {
                $batch->fill([
                    'branch_id' => $branchId,
                    'product_id' => $productId,
                    'supplier_id' => $supplierId,
                    'batch_number' => $batchNumber,
                    'manufacturing_date' => $manufacturingDate,
                    'expiry_date' => $expiryDate,
                    'quantity_received' => $quantity,
                    'quantity_remaining' => $quantity,
                    'purchase_price_per_unit' => $purchasePricePerUnit,
                    'selling_price_per_unit' => $sellingPricePerUnit,
                    'status' => 'active',
                ])->save();
            }

            // Log immutable stock movement
            StockMovement::create([
                'branch_id' => $branchId,
                'product_id' => $productId,
                'product_batch_id' => $batch->id,
                'user_id' => auth()->id(),
                'movement_type' => 'purchase_receipt',
                'quantity_delta' => $quantity,
                'balance_after' => $batch->quantity_remaining,
                'unit_cost' => $purchasePricePerUnit,
                'total_cost' => round($quantity * $purchasePricePerUnit, 4),
                'reason' => $reason,
                'created_at' => now(),
            ]);

            return $batch;
        });
    }

    /**
     * Submit and execute an authorized stock adjustment.
     */
    public function adjustStock(
        int $branchId,
        int $userId,
        string $reasonType,
        array $items,
        ?string $notes = null
    ): StockAdjustment {
        return DB::transaction(function () use ($branchId, $userId, $reasonType, $items, $notes) {
            $adjustmentNumber = 'ADJ-' . now()->format('Ymd') . '-' . strtoupper(substr(uniqid(), -4));

            $adjustment = StockAdjustment::create([
                'branch_id' => $branchId,
                'user_id' => $userId,
                'adjustment_number' => $adjustmentNumber,
                'reason_type' => $reasonType,
                'status' => 'approved',
                'approved_by' => $userId,
                'approved_at' => now(),
                'notes' => $notes,
            ]);

            foreach ($items as $itemData) {
                $batch = ProductBatch::where('id', $itemData['product_batch_id'])
                    ->where('branch_id', $branchId)
                    ->lockForUpdate()
                    ->firstOrFail();

                $currentQty = $batch->quantity_remaining;
                $newQty = (float) $itemData['new_quantity'];
                $delta = $newQty - $currentQty;

                if (abs($delta) < 0.0001) {
                    continue; // No change
                }

                $movementType = match ($reasonType) {
                    'damaged' => 'damaged_scrap',
                    'expired' => 'expired_scrap',
                    default => ($delta > 0 ? 'adjustment_addition' : 'adjustment_subtraction'),
                };

                // Adjust batch and record movement
                StockMovement::record(
                    batch: $batch,
                    movementType: $movementType,
                    quantityDelta: $delta,
                    reason: "Adjustment {$adjustmentNumber}: {$reasonType} (" . ($itemData['notes'] ?? $notes) . ")",
                    reference: $adjustment,
                    userId: $userId
                );

                StockAdjustmentItem::create([
                    'stock_adjustment_id' => $adjustment->id,
                    'product_id' => $batch->product_id,
                    'product_batch_id' => $batch->id,
                    'current_quantity' => $currentQty,
                    'new_quantity' => $newQty,
                    'quantity_delta' => $delta,
                    'unit_cost' => $batch->purchase_price_per_unit,
                    'total_cost_impact' => round($delta * $batch->purchase_price_per_unit, 4),
                    'notes' => $itemData['notes'] ?? null,
                ]);
            }

            AuditLog::log(
                action: 'stock_adjusted',
                description: "Stock adjustment {$adjustmentNumber} executed with reason '{$reasonType}'.",
                entity: $adjustment
            );

            return $adjustment;
        });
    }

    /**
     * Generate expiry risk report categorized into Expired, <30d, <60d, <90d, Safe.
     */
    public function getExpiryRiskReport(?int $branchId = null): array
    {
        $query = ProductBatch::with(['product:id,name,generic_name,sku,category_id', 'product.category:id,name', 'supplier:id,name'])
            ->where('quantity_remaining', '>', 0);

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        $batches = $query->orderBy('expiry_date', 'asc')->get();

        $summary = [
            'expired' => ['count' => 0, 'units' => 0.0, 'value_at_risk' => 0.0],
            'critical_30' => ['count' => 0, 'units' => 0.0, 'value_at_risk' => 0.0],
            'warning_60' => ['count' => 0, 'units' => 0.0, 'value_at_risk' => 0.0],
            'alert_90' => ['count' => 0, 'units' => 0.0, 'value_at_risk' => 0.0],
            'safe' => ['count' => 0, 'units' => 0.0, 'value_at_risk' => 0.0],
            'total_value_at_risk' => 0.0,
        ];

        $items = [];

        foreach ($batches as $batch) {
            $status = $batch->expiry_status; // 'expired', 'critical', 'warning', 'alert', 'safe'
            $bucketKey = match ($status) {
                'expired' => 'expired',
                'critical' => 'critical_30',
                'warning' => 'warning_60',
                'alert' => 'alert_90',
                default => 'safe',
            };

            $val = $batch->value_at_risk;
            $summary[$bucketKey]['count']++;
            $summary[$bucketKey]['units'] += $batch->quantity_remaining;
            $summary[$bucketKey]['value_at_risk'] = round($summary[$bucketKey]['value_at_risk'] + $val, 2);

            if ($status !== 'safe') {
                $summary['total_value_at_risk'] = round($summary['total_value_at_risk'] + $val, 2);
            }

            $items[] = [
                'id' => $batch->id,
                'product_name' => $batch->product?->name,
                'generic_name' => $batch->product?->generic_name,
                'sku' => $batch->product?->sku,
                'category' => $batch->product?->category?->name,
                'supplier' => $batch->supplier?->name,
                'batch_number' => $batch->batch_number,
                'expiry_date' => $batch->expiry_date->toDateString(),
                'days_until_expiry' => $batch->days_until_expiry,
                'expiry_status' => $status,
                'quantity_remaining' => $batch->quantity_remaining,
                'purchase_price_per_unit' => $batch->purchase_price_per_unit,
                'selling_price_per_unit' => $batch->selling_price_per_unit,
                'value_at_risk' => $val,
            ];
        }

        return [
            'summary' => $summary,
            'batches' => $items,
        ];
    }
}
