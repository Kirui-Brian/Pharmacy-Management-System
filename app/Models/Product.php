<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'pharmacy_id',
        'category_id',
        'manufacturer_id',
        'unit_id',
        'name',
        'generic_name',
        'brand_name',
        'sku',
        'barcode',
        'pack_size',
        'purchase_price',
        'selling_price',
        'wholesale_price',
        'reorder_level',
        'max_stock_level',
        'is_prescription_required',
        'is_controlled',
        'tax_rate',
        'description',
        'is_active',
    ];

    protected $casts = [
        'pack_size' => 'integer',
        'purchase_price' => 'float',
        'selling_price' => 'float',
        'wholesale_price' => 'float',
        'reorder_level' => 'integer',
        'max_stock_level' => 'integer',
        'tax_rate' => 'float',
        'is_prescription_required' => 'boolean',
        'is_controlled' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function pharmacy(): BelongsTo
    {
        return $this->belongsTo(Pharmacy::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'category_id');
    }

    public function manufacturer(): BelongsTo
    {
        return $this->belongsTo(Manufacturer::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function batches(): HasMany
    {
        return $this->hasMany(ProductBatch::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    /**
     * Get active batches sorted by FEFO (earliest expiry first).
     */
    public function fefoBatches(?int $branchId = null): Builder
    {
        $query = $this->batches()
            ->where('status', 'active')
            ->where('quantity_remaining', '>', 0)
            ->where('expiry_date', '>', now()->toDateString())
            ->orderBy('expiry_date', 'asc')
            ->orderBy('id', 'asc');

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        return $query;
    }

    /**
     * Get total unexpired stock quantity in a branch.
     */
    public function getTotalStockAttribute(): float
    {
        $branchId = auth()->user()?->branch_id;
        $query = $this->batches()
            ->where('status', 'active')
            ->where('expiry_date', '>', now()->toDateString());

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        return (float) $query->sum('quantity_remaining');
    }
}
