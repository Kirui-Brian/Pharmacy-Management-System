<?php

namespace App\Services;

use App\Models\Account;
use App\Models\GoodsReceivedNote;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\Pharmacy;
use App\Models\Sale;
use App\Models\SaleReturn;
use App\Models\SupplierPayment;
use App\Models\ExpenseVoucher;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AccountingService
{
    /**
     * Create a balanced journal entry with lines and update account balances.
     *
     * @param array $entryData [pharmacy_id, branch_id, user_id, entry_date, reference_type, reference_id, description]
     * @param array $lines [[account_id, debit, credit, description], ...]
     * @return JournalEntry
     */
    public function createJournalEntry(array $entryData, array $lines): JournalEntry
    {
        return DB::transaction(function () use ($entryData, $lines) {
            $pharmacyId = $entryData['pharmacy_id'];

            $totalDebit = 0.00;
            $totalCredit = 0.00;

            foreach ($lines as $line) {
                $totalDebit += (float) ($line['debit'] ?? 0);
                $totalCredit += (float) ($line['credit'] ?? 0);
            }

            $totalDebit = round($totalDebit, 2);
            $totalCredit = round($totalCredit, 2);

            // Double-entry balancing enforcement
            if (abs($totalDebit - $totalCredit) > 0.01) {
                throw ValidationException::withMessages([
                    'journal' => ["Unbalanced journal entry. Total Debit (KES {$totalDebit}) does not equal Total Credit (KES {$totalCredit})."],
                ]);
            }

            if ($totalDebit <= 0) {
                throw ValidationException::withMessages([
                    'journal' => ['Journal entry amount must be greater than zero.'],
                ]);
            }

            // Generate entry number: JE-YYYYMMDD-XXXX
            $datePrefix = Carbon::now()->format('Ymd');
            $countToday = JournalEntry::where('pharmacy_id', $pharmacyId)
                ->whereDate('created_at', Carbon::today())
                ->count() + 1;
            $entryNumber = sprintf("JE-%s-%04d", $datePrefix, $countToday);

            $entry = JournalEntry::create([
                'pharmacy_id' => $pharmacyId,
                'branch_id' => $entryData['branch_id'] ?? null,
                'user_id' => $entryData['user_id'] ?? null,
                'entry_number' => $entryNumber,
                'entry_date' => $entryData['entry_date'] ?? Carbon::now(),
                'reference_type' => $entryData['reference_type'] ?? 'manual',
                'reference_id' => $entryData['reference_id'] ?? null,
                'description' => $entryData['description'],
                'total_debit' => $totalDebit,
                'total_credit' => $totalCredit,
                'status' => 'posted',
            ]);

            foreach ($lines as $lineData) {
                $debit = round((float) ($lineData['debit'] ?? 0), 2);
                $credit = round((float) ($lineData['credit'] ?? 0), 2);

                if ($debit == 0 && $credit == 0) {
                    continue;
                }

                $account = Account::where('pharmacy_id', $pharmacyId)->findOrFail($lineData['account_id']);

                JournalEntryLine::create([
                    'journal_entry_id' => $entry->id,
                    'account_id' => $account->id,
                    'debit' => $debit,
                    'credit' => $credit,
                    'description' => $lineData['description'] ?? $entry->description,
                ]);

                // Update account current balance based on normal balance rules:
                // Assets & Expenses: Balance = Debit - Credit
                // Liabilities, Equity & Revenue: Balance = Credit - Debit
                if (in_array($account->type, ['asset', 'expense'])) {
                    $account->current_balance = round($account->current_balance + $debit - $credit, 2);
                } else {
                    $account->current_balance = round($account->current_balance + $credit - $debit, 2);
                }
                $account->save();
            }

            return $entry;
        });
    }

    /**
     * Auto-post Journal Entry for a POS Sale.
     * 1. Debit Cash/Bank/AR for sale amount
     * 2. Credit Sales Revenue (4000)
     * 3. Debit COGS (5000) for historic depleted batch cost
     * 4. Credit Inventory Asset (1200) for historic depleted batch cost
     */
    public function postSaleJournalEntry(Sale $sale): ?JournalEntry
    {
        $pharmacyId = $sale->pharmacy_id;

        // Fetch required system accounts
        $cashAcc = Account::where('pharmacy_id', $pharmacyId)->where('code', '1000')->first();
        $bankAcc = Account::where('pharmacy_id', $pharmacyId)->where('code', '1010')->first();
        $arAcc   = Account::where('pharmacy_id', $pharmacyId)->where('code', '1300')->first();
        $invAcc  = Account::where('pharmacy_id', $pharmacyId)->where('code', '1200')->first();
        $revAcc  = Account::where('pharmacy_id', $pharmacyId)->where('code', '4000')->first();
        $cogsAcc = Account::where('pharmacy_id', $pharmacyId)->where('code', '5000')->first();

        if (!$revAcc || !$cogsAcc || !$invAcc) {
            return null; // Accounts not seeded for this pharmacy
        }

        $lines = [];

        // 1. Inflow lines (Cash, M-Pesa, or AR)
        $sale->loadMissing('payments');
        $totalPaid = 0.00;

        foreach ($sale->payments as $payment) {
            $amt = (float) $payment->amount;
            if ($amt <= 0) continue;

            $acc = ($payment->payment_method === 'cash') ? ($cashAcc ?? $bankAcc) : ($bankAcc ?? $cashAcc);
            if ($acc) {
                $lines[] = [
                    'account_id' => $acc->id,
                    'debit' => $amt,
                    'credit' => 0.00,
                    'description' => "Sale payment ({$payment->payment_method}) - Receipt #{$sale->receipt_number}",
                ];
                $totalPaid += $amt;
            }
        }

        // Remaining balance goes to Accounts Receivable (Credit sale)
        $balanceDue = round((float) $sale->total_amount - $totalPaid, 2);
        if ($balanceDue > 0 && $arAcc) {
            $lines[] = [
                'account_id' => $arAcc->id,
                'debit' => $balanceDue,
                'credit' => 0.00,
                'description' => "Credit sale balance - Customer #{$sale->customer_id} - Receipt #{$sale->receipt_number}",
            ];
        }

        // 2. Sales Revenue Credit
        $lines[] = [
            'account_id' => $revAcc->id,
            'debit' => 0.00,
            'credit' => round((float) $sale->total_amount, 2),
            'description' => "Dispensing revenue - Receipt #{$sale->receipt_number}",
        ];

        // 3. COGS & Inventory Reduction
        $cogs = round((float) $sale->cost_of_goods_sold, 2);
        if ($cogs > 0) {
            $lines[] = [
                'account_id' => $cogsAcc->id,
                'debit' => $cogs,
                'credit' => 0.00,
                'description' => "Cost of goods sold (depleted batches) - Receipt #{$sale->receipt_number}",
            ];
            $lines[] = [
                'account_id' => $invAcc->id,
                'debit' => 0.00,
                'credit' => $cogs,
                'description' => "Inventory reduction via FEFO - Receipt #{$sale->receipt_number}",
            ];
        }

        return $this->createJournalEntry([
            'pharmacy_id' => $pharmacyId,
            'branch_id' => $sale->branch_id,
            'user_id' => $sale->user_id,
            'entry_date' => $sale->sale_date ?? Carbon::now(),
            'reference_type' => 'sale',
            'reference_id' => $sale->id,
            'description' => "POS Dispensing Sale #{$sale->receipt_number}",
        ], $lines);
    }

    /**
     * Auto-post Journal Entry for a Goods Received Note (GRN).
     * Debit: Inventory Asset (1200)
     * Credit: Accounts Payable (2000)
     */
    public function postGrnJournalEntry(GoodsReceivedNote $grn): ?JournalEntry
    {
        $pharmacyId = $grn->pharmacy_id;

        $invAcc = Account::where('pharmacy_id', $pharmacyId)->where('code', '1200')->first();
        $apAcc  = Account::where('pharmacy_id', $pharmacyId)->where('code', '2000')->first();

        if (!$invAcc || !$apAcc) {
            return null;
        }

        $totalVal = round((float) $grn->total_received_value, 2);
        if ($totalVal <= 0) {
            return null;
        }

        $supplierName = $grn->supplier?->name ?? 'Supplier';

        $lines = [
            [
                'account_id' => $invAcc->id,
                'debit' => $totalVal,
                'credit' => 0.00,
                'description' => "Stock intake received via GRN #{$grn->grn_number} from {$supplierName}",
            ],
            [
                'account_id' => $apAcc->id,
                'debit' => 0.00,
                'credit' => $totalVal,
                'description' => "Payable liability created for GRN #{$grn->grn_number} - {$supplierName}",
            ],
        ];

        return $this->createJournalEntry([
            'pharmacy_id' => $pharmacyId,
            'branch_id' => $grn->branch_id,
            'user_id' => $grn->received_by,
            'entry_date' => $grn->received_date ?? Carbon::now(),
            'reference_type' => 'grn',
            'reference_id' => $grn->id,
            'description' => "Stock intake GRN #{$grn->grn_number} ({$supplierName})",
        ], $lines);
    }

    /**
     * Auto-post Journal Entry for Supplier Payment.
     * Debit: Accounts Payable (2000)
     * Credit: Cash in Till (1000) or Bank/M-Pesa (1010)
     */
    public function postSupplierPaymentJournalEntry(SupplierPayment $payment): ?JournalEntry
    {
        $pharmacyId = $payment->pharmacy_id;

        $apAcc   = Account::where('pharmacy_id', $pharmacyId)->where('code', '2000')->first();
        $cashAcc = Account::where('pharmacy_id', $pharmacyId)->where('code', '1000')->first();
        $bankAcc = Account::where('pharmacy_id', $pharmacyId)->where('code', '1010')->first();

        if (!$apAcc) {
            return null;
        }

        $sourceAcc = ($payment->payment_method === 'cash') ? ($cashAcc ?? $bankAcc) : ($bankAcc ?? $cashAcc);
        if (!$sourceAcc) {
            return null;
        }

        $amount = round((float) $payment->amount, 2);
        if ($amount <= 0) {
            return null;
        }

        $supplierName = $payment->supplier?->name ?? 'Supplier';

        $lines = [
            [
                'account_id' => $apAcc->id,
                'debit' => $amount,
                'credit' => 0.00,
                'description' => "Settlement of supplier balance - {$supplierName} (Ref #{$payment->payment_reference})",
            ],
            [
                'account_id' => $sourceAcc->id,
                'debit' => 0.00,
                'credit' => $amount,
                'description' => "Funds disbursed via {$payment->payment_method} to {$supplierName}",
            ],
        ];

        return $this->createJournalEntry([
            'pharmacy_id' => $pharmacyId,
            'branch_id' => $payment->branch_id,
            'user_id' => $payment->user_id,
            'entry_date' => $payment->payment_date ?? Carbon::now(),
            'reference_type' => 'supplier_payment',
            'reference_id' => $payment->id,
            'description' => "Supplier Payment #{$payment->payment_reference} ({$supplierName})",
        ], $lines);
    }

    /**
     * Auto-post Journal Entry for an Operating Expense Voucher.
     * Debit: Expense Account (6000s)
     * Credit: Cash in Till (1000) or Bank/M-Pesa (1010) or Petty Cash (1020)
     */
    public function postExpenseJournalEntry(ExpenseVoucher $voucher): ?JournalEntry
    {
        $pharmacyId = $voucher->pharmacy_id;

        $expenseAcc = $voucher->account ?? $voucher->category?->account;
        if (!$expenseAcc) {
            // Fallback to General Sundry
            $expenseAcc = Account::where('pharmacy_id', $pharmacyId)->where('code', '6950')->first();
        }

        $cashAcc  = Account::where('pharmacy_id', $pharmacyId)->where('code', '1000')->first();
        $bankAcc  = Account::where('pharmacy_id', $pharmacyId)->where('code', '1010')->first();
        $pettyAcc = Account::where('pharmacy_id', $pharmacyId)->where('code', '1020')->first();

        $sourceAcc = match ($voucher->payment_method) {
            'cash' => $cashAcc ?? $bankAcc,
            'petty_cash' => $pettyAcc ?? $cashAcc ?? $bankAcc,
            default => $bankAcc ?? $cashAcc,
        };

        if (!$expenseAcc || !$sourceAcc) {
            return null;
        }

        $amount = round((float) $voucher->amount, 2);
        if ($amount <= 0) {
            return null;
        }

        $lines = [
            [
                'account_id' => $expenseAcc->id,
                'debit' => $amount,
                'credit' => 0.00,
                'description' => "Operating expense: {$voucher->paid_to} - {$voucher->description}",
            ],
            [
                'account_id' => $sourceAcc->id,
                'debit' => 0.00,
                'credit' => $amount,
                'description' => "Expense disbursed via {$voucher->payment_method} - Voucher #{$voucher->voucher_number}",
            ],
        ];

        return $this->createJournalEntry([
            'pharmacy_id' => $pharmacyId,
            'branch_id' => $voucher->branch_id,
            'user_id' => $voucher->approved_by ?? $voucher->created_by,
            'entry_date' => $voucher->expense_date ?? Carbon::now(),
            'reference_type' => 'expense_voucher',
            'reference_id' => $voucher->id,
            'description' => "Expense Voucher #{$voucher->voucher_number} ({$voucher->paid_to})",
        ], $lines);
    }
}
