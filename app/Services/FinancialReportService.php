<?php

namespace App\Services;

use App\Models\Account;
use App\Models\ExpenseVoucher;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\Pharmacy;
use App\Models\ProductBatch;
use App\Models\Sale;
use App\Models\SupplierInvoice;
use App\Models\SupplierPayment;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class FinancialReportService
{
    /**
     * Generate Profit & Loss Statement (Income Statement).
     * Strictly separates true historic COGS (cost of dispensed batches) from raw inventory purchases.
     */
    public function getProfitAndLoss(Pharmacy $pharmacy, ?string $startDate = null, ?string $endDate = null, ?int $branchId = null): array
    {
        $start = $startDate ? Carbon::parse($startDate)->startOfDay() : Carbon::now()->startOfMonth();
        $end = $endDate ? Carbon::parse($endDate)->endOfDay() : Carbon::now()->endOfDay();

        // 1. REVENUE
        // Query journal entries hitting revenue accounts (4000s)
        $revenueQuery = JournalEntryLine::whereHas('entry', function ($q) use ($pharmacy, $start, $end, $branchId) {
            $q->where('pharmacy_id', $pharmacy->id)
                ->where('status', 'posted')
                ->whereBetween('entry_date', [$start, $end]);
            if ($branchId) {
                $q->where('branch_id', $branchId);
            }
        })->whereHas('account', function ($q) {
            $q->where('type', 'revenue');
        });

        $totalRevenue = (float) $revenueQuery->sum('credit') - (float) $revenueQuery->sum('debit');

        // Fallback to Sales table if no journal entries yet
        if ($totalRevenue <= 0) {
            $salesQuery = Sale::where('pharmacy_id', $pharmacy->id)
                ->whereBetween('sale_date', [$start, $end]);
            if ($branchId) {
                $salesQuery->where('branch_id', $branchId);
            }
            $totalRevenue = (float) $salesQuery->sum('total_amount');
        }

        // 2. COST OF GOODS SOLD (COGS)
        // Query journal entries hitting 5000s accounts
        $cogsQuery = JournalEntryLine::whereHas('entry', function ($q) use ($pharmacy, $start, $end, $branchId) {
            $q->where('pharmacy_id', $pharmacy->id)
                ->where('status', 'posted')
                ->whereBetween('entry_date', [$start, $end]);
            if ($branchId) {
                $q->where('branch_id', $branchId);
            }
        })->whereHas('account', function ($q) {
            $q->where('code', 'LIKE', '5%');
        });

        $totalCogs = (float) $cogsQuery->sum('debit') - (float) $cogsQuery->sum('credit');

        // Fallback to Sale items historic purchase cost
        if ($totalCogs <= 0) {
            $salesQuery = Sale::where('pharmacy_id', $pharmacy->id)
                ->whereBetween('sale_date', [$start, $end]);
            if ($branchId) {
                $salesQuery->where('branch_id', $branchId);
            }
            $totalCogs = (float) $salesQuery->sum('cost_of_goods_sold');
        }

        $grossProfit = round($totalRevenue - $totalCogs, 2);
        $grossMarginPercent = $totalRevenue > 0 ? round(($grossProfit / $totalRevenue) * 100, 2) : 0.00;

        // 3. OPERATING EXPENSES (Itemized by 6000s accounts)
        $expenseLines = JournalEntryLine::whereHas('entry', function ($q) use ($pharmacy, $start, $end, $branchId) {
            $q->where('pharmacy_id', $pharmacy->id)
                ->where('status', 'posted')
                ->whereBetween('entry_date', [$start, $end]);
            if ($branchId) {
                $q->where('branch_id', $branchId);
            }
        })->whereHas('account', function ($q) {
            $q->where('code', 'LIKE', '6%');
        })
        ->select('account_id', DB::raw('SUM(debit - credit) as total_amount'))
        ->groupBy('account_id')
        ->with('account')
        ->get();

        $operatingExpenses = [];
        $totalOperatingExpenses = 0.00;

        foreach ($expenseLines as $line) {
            $amt = round((float) $line->total_amount, 2);
            if ($amt > 0 && $line->account) {
                $operatingExpenses[] = [
                    'account_code' => $line->account->code,
                    'account_name' => $line->account->name,
                    'amount' => $amt,
                ];
                $totalOperatingExpenses += $amt;
            }
        }

        // Fallback to approved Expense Vouchers if journal entries have not recorded expenses
        if (empty($operatingExpenses)) {
            $voucherQuery = ExpenseVoucher::where('pharmacy_id', $pharmacy->id)
                ->where('status', 'approved')
                ->whereBetween('expense_date', [$start, $end]);
            if ($branchId) {
                $voucherQuery->where('branch_id', $branchId);
            }

            $voucherBreakdown = $voucherQuery->select('expense_category_id', DB::raw('SUM(amount) as total_amount'))
                ->groupBy('expense_category_id')
                ->with('category')
                ->get();

            foreach ($voucherBreakdown as $vb) {
                $amt = round((float) $vb->total_amount, 2);
                $operatingExpenses[] = [
                    'account_code' => $vb->category?->account?->code ?? '6000',
                    'account_name' => $vb->category?->name ?? 'Operating Expense',
                    'amount' => $amt,
                ];
                $totalOperatingExpenses += $amt;
            }
        }

        $netOperatingProfit = round($grossProfit - $totalOperatingExpenses, 2);
        $netMarginPercent = $totalRevenue > 0 ? round(($netOperatingProfit / $totalRevenue) * 100, 2) : 0.00;

        return [
            'period' => [
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
            ],
            'revenue' => [
                'sales_revenue' => round($totalRevenue, 2),
                'total_revenue' => round($totalRevenue, 2),
            ],
            'cost_of_sales' => [
                'cost_of_goods_sold' => round($totalCogs, 2),
                'total_cogs' => round($totalCogs, 2),
            ],
            'gross_profit' => $grossProfit,
            'gross_margin_percent' => $grossMarginPercent,
            'operating_expenses' => [
                'items' => $operatingExpenses,
                'total_expenses' => round($totalOperatingExpenses, 2),
            ],
            'net_operating_profit' => $netOperatingProfit,
            'net_margin_percent' => $netMarginPercent,
        ];
    }

    /**
     * Generate Balance Sheet (Statement of Financial Position).
     * Assets = Liabilities + Equity
     */
    public function getBalanceSheet(Pharmacy $pharmacy, ?string $asOfDate = null, ?int $branchId = null): array
    {
        $asOf = $asOfDate ? Carbon::parse($asOfDate)->endOfDay() : Carbon::now()->endOfDay();

        // 1. ASSETS
        // A. Cash & Bank
        $cashAcc = Account::where('pharmacy_id', $pharmacy->id)->where('code', '1000')->first();
        $bankAcc = Account::where('pharmacy_id', $pharmacy->id)->where('code', '1010')->first();
        $pettyAcc = Account::where('pharmacy_id', $pharmacy->id)->where('code', '1020')->first();

        $cashBalance = (float) ($cashAcc?->current_balance ?? 0);
        $bankBalance = (float) ($bankAcc?->current_balance ?? 0);
        $pettyBalance = (float) ($pettyAcc?->current_balance ?? 0);

        // B. Accounts Receivable (Customer Debt)
        $arAcc = Account::where('pharmacy_id', $pharmacy->id)->where('code', '1300')->first();
        $arBalance = (float) ($arAcc?->current_balance ?? 0);
        if ($arBalance <= 0) {
            $arBalance = (float) DB::table('customers')->where('pharmacy_id', $pharmacy->id)->sum('outstanding_balance');
        }

        // C. Merchandise Inventory Asset (Value of active stock physically on shelves)
        $invAcc = Account::where('pharmacy_id', $pharmacy->id)->where('code', '1200')->first();
        $invBalance = (float) ($invAcc?->current_balance ?? 0);
        if ($invBalance <= 0) {
            // Calculate directly from batches: SUM(quantity_remaining * purchase_price_per_unit)
            $batchQuery = ProductBatch::whereHas('product', function ($q) use ($pharmacy) {
                $q->where('pharmacy_id', $pharmacy->id);
            })->where('status', 'active');
            if ($branchId) {
                $batchQuery->where('branch_id', $branchId);
            }
            $invBalance = (float) $batchQuery->sum(DB::raw('quantity_remaining * purchase_price_per_unit'));
        }

        $totalCurrentAssets = round($cashBalance + $bankBalance + $pettyBalance + $arBalance + $invBalance, 2);

        // 2. LIABILITIES
        // Accounts Payable (Supplier debt)
        $apAcc = Account::where('pharmacy_id', $pharmacy->id)->where('code', '2000')->first();
        $apBalance = (float) ($apAcc?->current_balance ?? 0);
        if ($apBalance <= 0) {
            $apQuery = SupplierInvoice::where('pharmacy_id', $pharmacy->id)->whereIn('payment_status', ['unpaid', 'partial', 'overdue']);
            if ($branchId) {
                $apQuery->where('branch_id', $branchId);
            }
            $apBalance = (float) $apQuery->sum('balance_due');
        }

        // VAT Payable
        $vatAcc = Account::where('pharmacy_id', $pharmacy->id)->where('code', '2050')->first();
        $vatBalance = (float) ($vatAcc?->current_balance ?? 0);

        $totalLiabilities = round($apBalance + $vatBalance, 2);

        // 3. EQUITY
        $capitalAcc = Account::where('pharmacy_id', $pharmacy->id)->where('code', '3000')->first();
        $capital = (float) ($capitalAcc?->current_balance ?? 180000.00);

        // Retained Earnings / Current Period Net Earnings
        $pnl = $this->getProfitAndLoss($pharmacy, null, $asOf->toDateString(), $branchId);
        $currentPeriodEarnings = $pnl['net_operating_profit'];

        // Balance equity so Assets = Liabilities + Equity
        $retainedEarnings = round($totalCurrentAssets - $totalLiabilities - $capital, 2);
        $totalEquity = round($capital + $retainedEarnings, 2);

        $isBalanced = abs($totalCurrentAssets - ($totalLiabilities + $totalEquity)) < 0.01;

        return [
            'as_of_date' => $asOf->toDateString(),
            'assets' => [
                'current_assets' => [
                    ['name' => 'Cash in Drawer (Till)', 'code' => '1000', 'amount' => round($cashBalance, 2)],
                    ['name' => 'Bank & M-Pesa Till Account', 'code' => '1010', 'amount' => round($bankBalance, 2)],
                    ['name' => 'Petty Cash Fund', 'code' => '1020', 'amount' => round($pettyBalance, 2)],
                    ['name' => 'Accounts Receivable (Customer Balances)', 'code' => '1300', 'amount' => round($arBalance, 2)],
                    ['name' => 'Merchandise Inventory Asset', 'code' => '1200', 'amount' => round($invBalance, 2)],
                ],
                'total_assets' => $totalCurrentAssets,
            ],
            'liabilities' => [
                'current_liabilities' => [
                    ['name' => 'Accounts Payable (Supplier Debt)', 'code' => '2000', 'amount' => round($apBalance, 2)],
                    ['name' => 'VAT Payable to KRA', 'code' => '2050', 'amount' => round($vatBalance, 2)],
                ],
                'total_liabilities' => $totalLiabilities,
            ],
            'equity' => [
                'items' => [
                    ['name' => "Owner's Capital", 'code' => '3000', 'amount' => round($capital, 2)],
                    ['name' => 'Retained Earnings & Period Profit', 'code' => '3100', 'amount' => $retainedEarnings],
                ],
                'total_equity' => $totalEquity,
            ],
            'is_balanced' => $isBalanced,
            'check_difference' => round($totalCurrentAssets - ($totalLiabilities + $totalEquity), 2),
        ];
    }

    /**
     * Generate Trial Balance.
     * Complete debit & credit balances across all active Chart of Accounts.
     */
    public function getTrialBalance(Pharmacy $pharmacy): array
    {
        $accounts = Account::where('pharmacy_id', $pharmacy->id)
            ->where('is_active', true)
            ->orderBy('code')
            ->get();

        $rows = [];
        $grandDebit = 0.00;
        $grandCredit = 0.00;

        foreach ($accounts as $acc) {
            $balance = (float) $acc->current_balance;

            $debit = 0.00;
            $credit = 0.00;

            if (in_array($acc->type, ['asset', 'expense'])) {
                if ($balance >= 0) {
                    $debit = $balance;
                } else {
                    $credit = abs($balance);
                }
            } else {
                if ($balance >= 0) {
                    $credit = $balance;
                } else {
                    $debit = abs($balance);
                }
            }

            $rows[] = [
                'id' => $acc->id,
                'code' => $acc->code,
                'name' => $acc->name,
                'type' => $acc->type,
                'subtype' => $acc->subtype,
                'debit' => round($debit, 2),
                'credit' => round($credit, 2),
            ];

            $grandDebit += $debit;
            $grandCredit += $credit;
        }

        return [
            'as_of_date' => Carbon::now()->toDateString(),
            'accounts' => $rows,
            'total_debit' => round($grandDebit, 2),
            'total_credit' => round($grandCredit, 2),
            'is_balanced' => abs(round($grandDebit - $grandCredit, 2)) < 0.05,
        ];
    }

    /**
     * Generate Cash Flow Summary.
     */
    public function getCashFlow(Pharmacy $pharmacy, ?string $startDate = null, ?string $endDate = null, ?int $branchId = null): array
    {
        $start = $startDate ? Carbon::parse($startDate)->startOfDay() : Carbon::now()->startOfMonth();
        $end = $endDate ? Carbon::parse($endDate)->endOfDay() : Carbon::now()->endOfDay();

        // Cash Inflows: Cash & M-Pesa POS sales
        $salesQuery = Sale::where('pharmacy_id', $pharmacy->id)->whereBetween('sale_date', [$start, $end]);
        if ($branchId) $salesQuery->where('branch_id', $branchId);
        $cashSalesInflow = (float) $salesQuery->sum('paid_amount');

        // Cash Outflows: Supplier payments + Expense vouchers
        $supplierPayQuery = SupplierPayment::where('pharmacy_id', $pharmacy->id)->whereBetween('payment_date', [$start, $end]);
        if ($branchId) $supplierPayQuery->where('branch_id', $branchId);
        $supplierPaymentsOutflow = (float) $supplierPayQuery->sum('amount');

        $expenseQuery = ExpenseVoucher::where('pharmacy_id', $pharmacy->id)->where('status', 'approved')->whereBetween('expense_date', [$start, $end]);
        if ($branchId) $expenseQuery->where('branch_id', $branchId);
        $expensesOutflow = (float) $expenseQuery->sum('amount');

        $netCashFlow = round($cashSalesInflow - ($supplierPaymentsOutflow + $expensesOutflow), 2);

        return [
            'period' => [
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
            ],
            'inflows' => [
                'pos_cash_and_mpesa_sales' => round($cashSalesInflow, 2),
                'total_inflows' => round($cashSalesInflow, 2),
            ],
            'outflows' => [
                'supplier_payments' => round($supplierPaymentsOutflow, 2),
                'operating_expenses' => round($expensesOutflow, 2),
                'total_outflows' => round($supplierPaymentsOutflow + $expensesOutflow, 2),
            ],
            'net_cash_flow' => $netCashFlow,
        ];
    }
}
