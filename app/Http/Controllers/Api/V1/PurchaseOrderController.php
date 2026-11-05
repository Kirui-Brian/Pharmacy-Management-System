<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PurchaseOrder;
use App\Services\ProcurementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PurchaseOrderController extends Controller
{
    protected ProcurementService $procurementService;

    public function __construct(ProcurementService $procurementService)
    {
        $this->procurementService = $procurementService;
    }

    /**
     * List purchase orders with filtering and pagination.
     */
    public function index(Request $request): JsonResponse
    {
        $pharmacyId = $request->user()->pharmacy_id;
        $branchId = $request->query('branch_id', $request->user()->branch_id);

        $query = PurchaseOrder::where('pharmacy_id', $pharmacyId)
            ->with(['supplier:id,name,phone,email', 'creator:id,name', 'branch:id,name'])
            ->withCount('items');

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        if ($request->filled('supplier_id')) {
            $query->where('supplier_id', $request->query('supplier_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        if ($request->filled('search')) {
            $search = $request->query('search');
            $query->where(function ($q) use ($search) {
                $q->where('po_number', 'like', "%{$search}%")
                  ->orWhereHas('supplier', function ($sq) use ($search) {
                      $sq->where('name', 'like', "%{$search}%");
                  });
            });
        }

        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereBetween('order_date', [$request->query('start_date'), $request->query('end_date')]);
        }

        $orders = $query->orderBy('id', 'desc')->paginate($request->query('per_page', 15));

        return response()->json([
            'status' => 'success',
            'data' => $orders,
        ]);
    }

    /**
     * Create a new purchase order.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'supplier_id' => ['required', 'exists:suppliers,id'],
            'branch_id' => ['nullable', 'exists:branches,id'],
            'order_date' => ['nullable', 'date'],
            'expected_delivery_date' => ['nullable', 'date', 'after_or_equal:order_date'],
            'shipping_cost' => ['nullable', 'numeric', 'min:0'],
            'status' => ['nullable', 'in:draft,ordered,partially_received,received,cancelled'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.quantity_ordered' => ['required', 'numeric', 'min:0.01'],
            'items.*.unit_cost' => ['required', 'numeric', 'min:0'],
            'items.*.discount_amount' => ['nullable', 'numeric', 'min:0'],
            'items.*.tax_amount' => ['nullable', 'numeric', 'min:0'],
        ]);

        $po = $this->procurementService->createPurchaseOrder($validated, $request->user());

        return response()->json([
            'status' => 'success',
            'message' => 'Purchase order created successfully',
            'data' => $po,
        ], 201);
    }

    /**
     * View detailed purchase order.
     */
    public function show(Request $request, PurchaseOrder $purchaseOrder): JsonResponse
    {
        if ($purchaseOrder->pharmacy_id !== $request->user()->pharmacy_id) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized access.'], 403);
        }

        $purchaseOrder->load([
            'items.product:id,name,brand_name,generic_name,sku,barcode',
            'supplier',
            'creator:id,name',
            'branch:id,name',
            'goodsReceivedNotes.items',
            'invoices',
        ]);

        return response()->json([
            'status' => 'success',
            'data' => $purchaseOrder,
        ]);
    }

    /**
     * Update PO status (e.g. approve, send, cancel).
     */
    public function updateStatus(Request $request, PurchaseOrder $purchaseOrder): JsonResponse
    {
        if ($purchaseOrder->pharmacy_id !== $request->user()->pharmacy_id) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized access.'], 403);
        }

        $validated = $request->validate([
            'status' => ['required', 'in:draft,pending,ordered,sent,partially_received,received,cancelled'],
        ]);

        $po = $this->procurementService->updatePurchaseOrderStatus($purchaseOrder, $validated['status'], $request->user());

        return response()->json([
            'status' => 'success',
            'message' => "Purchase order status updated to {$validated['status']}",
            'data' => $po,
        ]);
    }
}
