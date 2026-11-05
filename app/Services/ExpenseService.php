<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\ExpenseCategory;
use App\Models\ExpenseVoucher;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ExpenseService
{
    protected AccountingService $accountingService;

    public function __construct(AccountingService $accountingService)
    {
        $this->accountingService = $accountingService;
    }

    /**
     * Create a new expense voucher.
     */
    public function createVoucher(array $data, User $user): ExpenseVoucher
    {
        return DB::transaction(function () use ($data, $user) {
            $pharmacyId = $user->pharmacy_id;
            $branchId = $data['branch_id'] ?? $user->branch_id;

            $category = ExpenseCategory::where('pharmacy_id', $pharmacyId)->findOrFail($data['expense_category_id']);

            $amount = round((float) $data['amount'], 2);
            if ($amount <= 0) {
                throw ValidationException::withMessages([
                    'amount' => ['Expense amount must be greater than zero.'],
                ]);
            }

            $datePrefix = Carbon::now()->format('Ymd');
            $countToday = ExpenseVoucher::where('pharmacy_id', $pharmacyId)
                ->whereDate('created_at', Carbon::today())
                ->count() + 1;
            $voucherNumber = sprintf("EXP-%s-%04d", $datePrefix, $countToday);

            $autoApprove = isset($data['auto_approve']) ? (bool) $data['auto_approve'] : true;

            $voucher = ExpenseVoucher::create([
                'pharmacy_id' => $pharmacyId,
                'branch_id' => $branchId,
                'expense_category_id' => $category->id,
                'account_id' => $category->account_id,
                'created_by' => $user->id,
                'approved_by' => $autoApprove ? $user->id : null,
                'voucher_number' => $voucherNumber,
                'paid_to' => $data['paid_to'],
                'amount' => $amount,
                'tax_amount' => round((float) ($data['tax_amount'] ?? 0.00), 2),
                'payment_method' => $data['payment_method'] ?? 'cash',
                'reference_number' => $data['reference_number'] ?? null,
                'expense_date' => $data['expense_date'] ?? Carbon::now(),
                'description' => $data['description'] ?? "Expense for {$category->name}",
                'receipt_file_path' => $data['receipt_file_path'] ?? null,
                'status' => $autoApprove ? 'approved' : 'draft',
                'approved_at' => $autoApprove ? Carbon::now() : null,
            ]);

            // Auto-post to general ledger if approved
            if ($voucher->status === 'approved') {
                $this->accountingService->postExpenseJournalEntry($voucher);
            }

            AuditLog::create([
                'pharmacy_id' => $pharmacyId,
                'branch_id' => $branchId,
                'user_id' => $user->id,
                'action' => 'expense.voucher_created',
                'description' => "Created expense voucher #{$voucherNumber} for KES {$amount} ({$category->name})",
                'ip_address' => request()->ip() ?? '127.0.0.1',
                'user_agent' => request()->userAgent() ?? 'CLI/System',
            ]);

            return $voucher->load(['category.account', 'creator', 'approver', 'branch']);
        });
    }

    /**
     * Approve a draft expense voucher and post journal entry.
     */
    public function approveVoucher(ExpenseVoucher $voucher, User $approver): ExpenseVoucher
    {
        if ($voucher->status === 'approved') {
            return $voucher;
        }

        return DB::transaction(function () use ($voucher, $approver) {
            $voucher->status = 'approved';
            $voucher->approved_by = $approver->id;
            $voucher->approved_at = Carbon::now();
            $voucher->save();

            // Post journal entry to general ledger
            $this->accountingService->postExpenseJournalEntry($voucher);

            AuditLog::create([
                'pharmacy_id' => $voucher->pharmacy_id,
                'branch_id' => $voucher->branch_id,
                'user_id' => $approver->id,
                'action' => 'expense.voucher_approved',
                'description' => "Approved expense voucher #{$voucher->voucher_number} for KES {$voucher->amount}",
                'ip_address' => request()->ip() ?? '127.0.0.1',
                'user_agent' => request()->userAgent() ?? 'CLI/System',
            ]);

            return $voucher->load(['category.account', 'creator', 'approver', 'branch']);
        });
    }

    /**
     * Reject an expense voucher.
     */
    public function rejectVoucher(ExpenseVoucher $voucher, User $user, ?string $reason = null): ExpenseVoucher
    {
        $voucher->status = 'rejected';
        $voucher->save();

        AuditLog::create([
            'pharmacy_id' => $voucher->pharmacy_id,
            'branch_id' => $voucher->branch_id,
            'user_id' => $user->id,
            'action' => 'expense.voucher_rejected',
            'description' => "Rejected expense voucher #{$voucher->voucher_number}. Reason: " . ($reason ?? 'Not specified'),
            'ip_address' => request()->ip() ?? '127.0.0.1',
            'user_agent' => request()->userAgent() ?? 'CLI/System',
        ]);

        return $voucher;
    }
}
