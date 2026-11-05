<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DispensationItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'dispensation_id',
        'prescription_item_id',
        'product_id',
        'product_batch_id',
        'batch_number',
        'quantity_dispensed',
        'dosage_instructions',
    ];

    protected $casts = [
        'quantity_dispensed' => 'decimal:2',
    ];

    public function dispensation(): BelongsTo
    {
        return $this->belongsTo(Dispensation::class);
    }

    public function prescriptionItem(): BelongsTo
    {
        return $this->belongsTo(PrescriptionItem::class);
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
