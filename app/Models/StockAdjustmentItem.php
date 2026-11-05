<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockAdjustmentItem extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'stock_adjustment_id',
        'product_id',
        'product_batch_id',
        'current_quantity',
        'new_quantity',
        'quantity_delta',
        'unit_cost',
        'total_cost_impact',
        'notes',
    ];

    protected $casts = [
        'current_quantity' => 'float',
        'new_quantity' => 'float',
        'quantity_delta' => 'float',
        'unit_cost' => 'float',
        'total_cost_impact' => 'float',
    ];

    public function adjustment(): BelongsTo
    {
        return $this->belongsTo(StockAdjustment::class, 'stock_adjustment_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(ProductBatch::class, 'product_batch_id');
    }
}
