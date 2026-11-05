<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\SupplierPayment;
use App\Services\ProcurementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupplierPaymentController extends Controller
{
    protected ProcurementService $procurementService;

    public function __construct(ProcurementService $procurementService)
    {
        $this->procurementService = $procurementService;
    }

    /**
     * List supplier payments with filtering.
     */
    public function index(Request $request): JsonResponse
    {
        $pharmacyId = $request->user()->pharmacy_id;
        $branchId = $request->query('branch_id', $request->user()->branch_id);

        $query = SupplierPayment::where('pharmacy_id', $pharmacyId)
            ->with(['supplier:id,name', 'invoice:id,invoice_number,total_amount,balance_due', 'payer:id,name', 'branch:id,name']);

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        if ($request->filled('supplier_id')) {
            $query->where('supplier_id', $request->query('supplier_id'));
        }

        if ($request->filled('payment_method')) {
            $query->where('payment_method', $request->query('payment_method'));
        }

        if ($request->filled('search')) {
            $search = $request->query('search');
            $query->where(function ($q) use ($search) {
                $q->where('payment_number', 'like', "%{$search}%")
                  ->orWhere('reference_number', 'like', "%{$search}%")
                  ->orWhereHas('supplier', function ($sq) use ($search) {
                      $sq->where('name', 'like', "%{$search}%");
                  });
            });
        }

        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereBetween('payment_date', [$request->query('start_date'), $request->query('end_date')]);
        }

        $payments = $query->orderBy('id', 'desc')->paginate($request->query('per_page', 15));

        return response()->json([
            'status' => 'success',
            'data' => $payments,
        ]);
    }

    /**
     * Record a new supplier payment.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'supplier_id' => ['required', 'exists:suppliers,id'],
            'branch_id' => ['nullable', 'exists:branches,id'],
            'supplier_invoice_id' => ['nullable', 'exists:supplier_invoices,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_method' => ['required', 'in:bank_transfer,mpesa,cash,cheque'],
            'reference_number' => ['nullable', 'string', 'max:100'],
            'payment_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        $payment = $this->procurementService->recordSupplierPayment($validated, $request->user());

        return response()->json([
            'status' => 'success',
            'message' => 'Supplier payment recorded successfully',
            'data' => $payment,
        ], 201);
    }

    /**
     * View supplier payment details.
     */
    public function show(Request $request, SupplierPayment $supplierPayment): JsonResponse
    {
        if ($supplierPayment->pharmacy_id !== $request->user()->pharmacy_id) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized access.'], 403);
        }

        $supplierPayment->load(['supplier', 'invoice', 'payer:id,name', 'branch:id,name']);

        return response()->json([
            'status' => 'success',
            'data' => $supplierPayment,
        ]);
    }
}
