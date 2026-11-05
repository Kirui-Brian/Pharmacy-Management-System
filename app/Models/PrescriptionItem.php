<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrescriptionItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'prescription_id',
        'product_id',
        'dosage_instruction',
        'frequency',
        'duration_days',
        'quantity_prescribed',
        'quantity_dispensed',
        'remaining_quantity',
        'notes',
    ];

    protected $casts = [
        'duration_days' => 'integer',
        'quantity_prescribed' => 'decimal:2',
        'quantity_dispensed' => 'decimal:2',
        'remaining_quantity' => 'decimal:2',
    ];

    public function prescription(): BelongsTo
    {
        return $this->belongsTo(Prescription::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
