<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use InvalidArgumentException;

class StockMovement extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'branch_id',
        'product_id',
        'product_batch_id',
        'user_id',
        'movement_type',
        'quantity_delta',
        'balance_after',
        'unit_cost',
        'total_cost',
        'reference_type',
        'reference_id',
        'reason',
        'created_at',
    ];

    protected $casts = [
        'quantity_delta' => 'float',
        'balance_after' => 'float',
        'unit_cost' => 'float',
        'total_cost' => 'float',
        'created_at' => 'datetime',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(ProductBatch::class, 'product_batch_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Record a stock movement atomically and adjust batch balance.
     */
    public static function record(
        ProductBatch $batch,
        string $movementType,
        float $quantityDelta,
        ?string $reason = null,
        ?Model $reference = null,
        ?int $userId = null
    ): self {
        $newBalance = $batch->quantity_remaining + $quantityDelta;

        if ($newBalance < -0.0001) {
            throw new InvalidArgumentException(
                "Stock balance cannot become negative for batch {$batch->batch_number}. Current: {$batch->quantity_remaining}, Requested Delta: {$quantityDelta}"
            );
        }

        // Update batch balance
        $batch->quantity_remaining = max(0, $newBalance);
        if ($batch->quantity_remaining == 0) {
            $batch->status = 'depleted';
        } elseif ($batch->status === 'depleted' && $batch->quantity_remaining > 0) {
            $batch->status = 'active';
        }
        $batch->save();

        $userId = $userId ?: auth()->id();

        return static::create([
            'branch_id' => $batch->branch_id,
            'product_id' => $batch->product_id,
            'product_batch_id' => $batch->id,
            'user_id' => $userId,
            'movement_type' => $movementType,
            'quantity_delta' => $quantityDelta,
            'balance_after' => $batch->quantity_remaining,
            'unit_cost' => $batch->purchase_price_per_unit,
            'total_cost' => round(abs($quantityDelta) * $batch->purchase_price_per_unit, 4),
            'reference_type' => $reference ? get_class($reference) : null,
            'reference_id' => $reference?->getKey(),
            'reason' => $reason,
            'created_at' => now(),
        ]);
    }
}
