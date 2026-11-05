<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExpenseVoucher extends Model
{
    use HasFactory;

    protected $fillable = [
        'pharmacy_id',
        'branch_id',
        'expense_category_id',
        'account_id',
        'created_by',
        'approved_by',
        'voucher_number',
        'paid_to',
        'amount',
        'tax_amount',
        'payment_method',
        'reference_number',
        'expense_date',
        'description',
        'receipt_file_path',
        'status',
        'approved_at',
    ];

    protected $casts = [
        'expense_date' => 'datetime',
        'approved_at' => 'datetime',
        'amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
    ];

    public function pharmacy(): BelongsTo
    {
        return $this->belongsTo(Pharmacy::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
