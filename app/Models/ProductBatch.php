<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductBatch extends Model
{
    use HasFactory;

    protected $fillable = [
        'branch_id',
        'product_id',
        'supplier_id',
        'batch_number',
        'manufacturing_date',
        'expiry_date',
        'quantity_received',
        'quantity_remaining',
        'purchase_price_per_unit',
        'selling_price_per_unit',
        'status',
        'notes',
    ];

    protected $casts = [
        'manufacturing_date' => 'date',
        'expiry_date' => 'date',
        'quantity_received' => 'float',
        'quantity_remaining' => 'float',
        'purchase_price_per_unit' => 'float',
        'selling_price_per_unit' => 'float',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    // Scopes
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active')
            ->where('quantity_remaining', '>', 0)
            ->where('expiry_date', '>', now()->toDateString());
    }

    public function scopeFefo(Builder $query): Builder
    {
        return $query->orderBy('expiry_date', 'asc')->orderBy('id', 'asc');
    }

    public function scopeExpired(Builder $query): Builder
    {
        return $query->where('expiry_date', '<=', now()->toDateString());
    }

    public function scopeExpiringInDays(Builder $query, int $days): Builder
    {
        return $query->where('status', 'active')
            ->where('quantity_remaining', '>', 0)
            ->where('expiry_date', '>', now()->toDateString())
            ->where('expiry_date', '<=', now()->addDays($days)->toDateString());
    }

    // Computed Attributes
    public function getIsExpiredAttribute(): bool
    {
        return $this->expiry_date->isPast();
    }

    public function getDaysUntilExpiryAttribute(): int
    {
        return (int) now()->startOfDay()->diffInDays($this->expiry_date->startOfDay(), false);
    }

    public function getExpiryStatusAttribute(): string
    {
        $days = $this->days_until_expiry;
        if ($days <= 0) return 'expired';
        if ($days <= 30) return 'critical';
        if ($days <= 60) return 'warning';
        if ($days <= 90) return 'alert';
        return 'safe';
    }

    public function getValueAtRiskAttribute(): float
    {
        return round($this->quantity_remaining * $this->purchase_price_per_unit, 2);
    }
}
