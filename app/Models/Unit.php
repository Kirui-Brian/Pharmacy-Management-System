<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Unit extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'short_name',
        'base_unit',
        'operator',
        'operation_value',
        'is_active',
    ];

    protected $casts = [
        'operation_value' => 'float',
        'is_active' => 'boolean',
    ];

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /**
     * Convert pack quantity to base dispensing units.
     */
    public function toBaseUnits(float $packQuantity): float
    {
        return $this->operator === '*'
            ? $packQuantity * $this->operation_value
            : $packQuantity / ($this->operation_value ?: 1);
    }
}
