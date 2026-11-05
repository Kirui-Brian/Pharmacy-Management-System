<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\JournalEntry;
use App\Services\AccountingService;
use App\Services\FinancialReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AccountingController extends Controller
{
    protected AccountingService $accountingService;
    protected FinancialReportService $reportService;

    public function __construct(AccountingService $accountingService, FinancialReportService $reportService)
    {
        $this->accountingService = $accountingService;
        $this->reportService = $reportService;
    }

    /**
     * Get Chart of Accounts.
     */
    public function getChartOfAccounts(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = Account::where('pharmacy_id', $user->pharmacy_id);

        if ($request->has('type')) {
            $query->where('type', $request->query('type'));
        }

        if ($request->boolean('active_only', true)) {
            $query->where('is_active', true);
        }

        $accounts = $query->orderBy('code')->get();

        return response()->json([
            'success' => true,
            'data' => $accounts,
        ]);
    }

    /**
     * Store new account in Chart of Accounts.
     */
    public function storeAccount(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'code' => [
                'required',
                'string',
                'max:20',
                Rule::unique('chart_of_accounts')->where('pharmacy_id', $user->pharmacy_id),
            ],
            'name' => 'required|string|max:150',
            'type' => 'required|in:asset,liability,equity,revenue,expense',
            'subtype' => 'nullable|string|max:50',
            'opening_balance' => 'nullable|numeric|min:0',
            'description' => 'nullable|string',
        ]);

        $opening = (float) ($validated['opening_balance'] ?? 0.00);

        $account = Account::create([
            'pharmacy_id' => $user->pharmacy_id,
            'code' => $validated['code'],
            'name' => $validated['name'],
            'type' => $validated['type'],
            'subtype' => $validated['subtype'] ?? null,
            'currency' => 'KES',
            'opening_balance' => $opening,
            'current_balance' => $opening,
            'is_system' => false,
            'is_active' => true,
            'description' => $validated['description'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Account created successfully.',
            'data' => $account,
        ], 201);
    }

    /**
     * Get Journal Entries with filtering.
     */
    public function getJournalEntries(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = JournalEntry::where('pharmacy_id', $user->pharmacy_id)
            ->with(['lines.account', 'user', 'branch']);

        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereBetween('entry_date', [
                $request->query('start_date') . ' 00:00:00',
                $request->query('end_date') . ' 23:59:59',
            ]);
        }

        if ($request->filled('reference_type')) {
            $query->where('reference_type', $request->query('reference_type'));
        }

        if ($request->filled('branch_id')) {
            $query->where('branch_id', $request->query('branch_id'));
        }

        $perPage = min((int) $request->query('per_page', 50), 100);
        $entries = $query->latest('entry_date')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $entries->items(),
            'meta' => [
                'current_page' => $entries->currentPage(),
                'last_page' => $entries->lastPage(),
                'per_page' => $entries->perPage(),
                'total' => $entries->total(),
            ],
        ]);
    }

    /**
     * Store manual Journal Entry with double-entry balance check.
     */
    public function storeJournalEntry(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'entry_date' => 'nullable|date',
            'description' => 'required|string|max:255',
            'branch_id' => 'nullable|exists:branches,id',
            'lines' => 'required|array|min:2',
            'lines.*.account_id' => 'required|exists:chart_of_accounts,id',
            'lines.*.debit' => 'required|numeric|min:0',
            'lines.*.credit' => 'required|numeric|min:0',
            'lines.*.description' => 'nullable|string|max:255',
        ]);

        $entry = $this->accountingService->createJournalEntry([
            'pharmacy_id' => $user->pharmacy_id,
            'branch_id' => $validated['branch_id'] ?? $user->branch_id,
            'user_id' => $user->id,
            'entry_date' => $validated['entry_date'] ?? now(),
            'reference_type' => 'manual',
            'description' => $validated['description'],
        ], $validated['lines']);

        return response()->json([
            'success' => true,
            'message' => 'Journal entry posted successfully.',
            'data' => $entry->load(['lines.account', 'user']),
        ], 201);
    }

    /**
     * Get Profit & Loss Statement.
     */
    public function getProfitAndLoss(Request $request): JsonResponse
    {
        $user = $request->user();
        $pnl = $this->reportService->getProfitAndLoss(
            $user->pharmacy,
            $request->query('start_date'),
            $request->query('end_date'),
            $request->query('branch_id') ? (int) $request->query('branch_id') : null
        );

        return response()->json([
            'success' => true,
            'data' => $pnl,
        ]);
    }

    /**
     * Get Balance Sheet.
     */
    public function getBalanceSheet(Request $request): JsonResponse
    {
        $user = $request->user();
        $balanceSheet = $this->reportService->getBalanceSheet(
            $user->pharmacy,
            $request->query('as_of_date'),
            $request->query('branch_id') ? (int) $request->query('branch_id') : null
        );

        return response()->json([
            'success' => true,
            'data' => $balanceSheet,
        ]);
    }

    /**
     * Get Trial Balance.
     */
    public function getTrialBalance(Request $request): JsonResponse
    {
        $user = $request->user();
        $trialBalance = $this->reportService->getTrialBalance($user->pharmacy);

        return response()->json([
            'success' => true,
            'data' => $trialBalance,
        ]);
    }

    /**
     * Get Cash Flow Summary.
     */
    public function getCashFlow(Request $request): JsonResponse
    {
        $user = $request->user();
        $cashFlow = $this->reportService->getCashFlow(
            $user->pharmacy,
            $request->query('start_date'),
            $request->query('end_date'),
            $request->query('branch_id') ? (int) $request->query('branch_id') : null
        );

        return response()->json([
            'success' => true,
            'data' => $cashFlow,
        ]);
    }
}
