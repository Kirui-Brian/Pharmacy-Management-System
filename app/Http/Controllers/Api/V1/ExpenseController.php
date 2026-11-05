<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ExpenseCategory;
use App\Models\ExpenseVoucher;
use App\Services\ExpenseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ExpenseController extends Controller
{
    protected ExpenseService $expenseService;

    public function __construct(ExpenseService $expenseService)
    {
        $this->expenseService = $expenseService;
    }

    /**
     * Get expense categories.
     */
    public function getCategories(Request $request): JsonResponse
    {
        $user = $request->user();
        $categories = ExpenseCategory::where('pharmacy_id', $user->pharmacy_id)
            ->with('account')
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $categories,
        ]);
    }

    /**
     * Store new expense category.
     */
    public function storeCategory(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('expense_categories')->where('pharmacy_id', $user->pharmacy_id),
            ],
            'account_id' => 'nullable|exists:chart_of_accounts,id',
            'description' => 'nullable|string',
        ]);

        $category = ExpenseCategory::create([
            'pharmacy_id' => $user->pharmacy_id,
            'name' => $validated['name'],
            'account_id' => $validated['account_id'] ?? null,
            'description' => $validated['description'] ?? null,
            'is_active' => true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Expense category created.',
            'data' => $category->load('account'),
        ], 201);
    }

    /**
     * Get expense vouchers with filtering.
     */
    public function getVouchers(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = ExpenseVoucher::where('pharmacy_id', $user->pharmacy_id)
            ->with(['category.account', 'creator', 'approver', 'branch']);

        if ($request->filled('category_id')) {
            $query->where('expense_category_id', $request->query('category_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        if ($request->filled('payment_method')) {
            $query->where('payment_method', $request->query('payment_method'));
        }

        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereBetween('expense_date', [
                $request->query('start_date') . ' 00:00:00',
                $request->query('end_date') . ' 23:59:59',
            ]);
        }

        $perPage = min((int) $request->query('per_page', 25), 100);
        $vouchers = $query->latest('expense_date')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $vouchers->items(),
            'meta' => [
                'current_page' => $vouchers->currentPage(),
                'last_page' => $vouchers->lastPage(),
                'per_page' => $vouchers->perPage(),
                'total' => $vouchers->total(),
            ],
        ]);
    }

    /**
     * Store new expense voucher.
     */
    public function storeVoucher(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'expense_category_id' => 'required|exists:expense_categories,id',
            'paid_to' => 'required|string|max:150',
            'amount' => 'required|numeric|min:0.01',
            'tax_amount' => 'nullable|numeric|min:0',
            'payment_method' => 'required|in:cash,mpesa,bank_transfer,cheque,petty_cash',
            'reference_number' => 'nullable|string|max:100',
            'expense_date' => 'nullable|date',
            'description' => 'nullable|string',
            'auto_approve' => 'nullable|boolean',
        ]);

        $voucher = $this->expenseService->createVoucher($validated, $user);

        return response()->json([
            'success' => true,
            'message' => 'Expense voucher recorded.',
            'data' => $voucher,
        ], 201);
    }

    /**
     * Approve an expense voucher.
     */
    public function approveVoucher(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $voucher = ExpenseVoucher::where('pharmacy_id', $user->pharmacy_id)->findOrFail($id);

        $approved = $this->expenseService->approveVoucher($voucher, $user);

        return response()->json([
            'success' => true,
            'message' => 'Expense voucher approved and posted to general ledger.',
            'data' => $approved,
        ]);
    }

    /**
     * Reject an expense voucher.
     */
    public function rejectVoucher(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $voucher = ExpenseVoucher::where('pharmacy_id', $user->pharmacy_id)->findOrFail($id);

        $rejected = $this->expenseService->rejectVoucher($voucher, $user, $request->input('reason'));

        return response()->json([
            'success' => true,
            'message' => 'Expense voucher rejected.',
            'data' => $rejected,
        ]);
    }
}
