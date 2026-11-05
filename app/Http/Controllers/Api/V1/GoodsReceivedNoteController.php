<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\GoodsReceivedNote;
use App\Services\ProcurementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GoodsReceivedNoteController extends Controller
{
    protected ProcurementService $procurementService;

    public function __construct(ProcurementService $procurementService)
    {
        $this->procurementService = $procurementService;
    }

    /**
     * List Goods Received Notes with filtering and pagination.
     */
    public function index(Request $request): JsonResponse
    {
        $pharmacyId = $request->user()->pharmacy_id;
        $branchId = $request->query('branch_id', $request->user()->branch_id);

        $query = GoodsReceivedNote::where('pharmacy_id', $pharmacyId)
            ->with(['supplier:id,name,phone', 'receiver:id,name', 'branch:id,name', 'purchaseOrder:id,po_number'])
            ->withCount('items');

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        if ($request->filled('supplier_id')) {
            $query->where('supplier_id', $request->query('supplier_id'));
        }

        if ($request->filled('purchase_order_id')) {
            $query->where('purchase_order_id', $request->query('purchase_order_id'));
        }

        if ($request->filled('search')) {
            $search = $request->query('search');
            $query->where(function ($q) use ($search) {
                $q->where('grn_number', 'like', "%{$search}%")
                  ->orWhere('delivery_note_number', 'like', "%{$search}%")
                  ->orWhere('supplier_invoice_number', 'like', "%{$search}%")
                  ->orWhereHas('supplier', function ($sq) use ($search) {
                      $sq->where('name', 'like', "%{$search}%");
                  });
            });
        }

        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereBetween('received_date', [$request->query('start_date'), $request->query('end_date')]);
        }

        $grns = $query->orderBy('id', 'desc')->paginate($request->query('per_page', 15));

        return response()->json([
            'status' => 'success',
            'data' => $grns,
        ]);
    }

    /**
     * Receive inventory stock via GRN.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'supplier_id' => ['required', 'exists:suppliers,id'],
            'branch_id' => ['nullable', 'exists:branches,id'],
            'purchase_order_id' => ['nullable', 'exists:purchase_orders,id'],
            'delivery_note_number' => ['nullable', 'string', 'max:100'],
            'supplier_invoice_number' => ['nullable', 'string', 'max:100'],
            'received_date' => ['nullable', 'date'],
            'tax_amount' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.purchase_order_item_id' => ['nullable', 'exists:purchase_order_items,id'],
            'items.*.batch_number' => ['required', 'string', 'max:100'],
            'items.*.manufacturing_date' => ['nullable', 'date'],
            'items.*.expiry_date' => ['required', 'date'],
            'items.*.quantity_received' => ['required', 'numeric', 'min:0.01'],
            'items.*.unit_purchase_price' => ['required', 'numeric', 'min:0'],
            'items.*.unit_selling_price' => ['nullable', 'numeric', 'min:0'],
            'items.*.notes' => ['nullable', 'string'],
        ]);

        $grn = $this->procurementService->receiveStock($validated, $request->user());

        return response()->json([
            'status' => 'success',
            'message' => "Stock received successfully under GRN {$grn->grn_number}",
            'data' => $grn,
        ], 201);
    }

    /**
     * View detailed GRN.
     */
    public function show(Request $request, GoodsReceivedNote $goodsReceivedNote): JsonResponse
    {
        if ($goodsReceivedNote->pharmacy_id !== $request->user()->pharmacy_id) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized access.'], 403);
        }

        $goodsReceivedNote->load([
            'items.product:id,name,brand_name,generic_name,sku,barcode',
            'items.batch',
            'supplier',
            'receiver:id,name',
            'branch:id,name',
            'purchaseOrder:id,po_number',
            'supplierInvoices',
        ]);

        return response()->json([
            'status' => 'success',
            'data' => $goodsReceivedNote,
        ]);
    }
}
