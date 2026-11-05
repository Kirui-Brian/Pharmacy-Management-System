<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PurchaseReturn;
use App\Services\ProcurementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PurchaseReturnController extends Controller
{
    protected ProcurementService $procurementService;

    public function __construct(ProcurementService $procurementService)
    {
        $this->procurementService = $procurementService;
    }

    /**
     * List purchase returns with items and suppliers.
     */
    public function index(Request $request): JsonResponse
    {
        $pharmacyId = $request->user()->pharmacy_id;
        $branchId = $request->query('branch_id', $request->user()->branch_id);

        $query = PurchaseReturn::where('pharmacy_id', $pharmacyId)
            ->with(['supplier:id,name', 'processor:id,name', 'branch:id,name', 'goodsReceivedNote:id,grn_number'])
            ->withCount('items');

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        if ($request->filled('supplier_id')) {
            $query->where('supplier_id', $request->query('supplier_id'));
        }

        if ($request->filled('search')) {
            $search = $request->query('search');
            $query->where(function ($q) use ($search) {
                $q->where('return_number', 'like', "%{$search}%")
                  ->orWhere('reason', 'like', "%{$search}%")
                  ->orWhereHas('supplier', function ($sq) use ($search) {
                      $sq->where('name', 'like', "%{$search}%");
                  });
            });
        }

        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereBetween('return_date', [$request->query('start_date'), $request->query('end_date')]);
        }

        $returns = $query->orderBy('id', 'desc')->paginate($request->query('per_page', 15));

        return response()->json([
            'status' => 'success',
            'data' => $returns,
        ]);
    }

    /**
     * Process return of medicines to supplier.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'supplier_id' => ['required', 'exists:suppliers,id'],
            'branch_id' => ['nullable', 'exists:branches,id'],
            'goods_received_note_id' => ['nullable', 'exists:goods_received_notes,id'],
            'reason' => ['required', 'string', 'max:255'],
            'return_date' => ['nullable', 'date'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_batch_id' => ['required', 'exists:product_batches,id'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'items.*.unit_cost' => ['nullable', 'numeric', 'min:0'],
            'items.*.reason' => ['nullable', 'string', 'max:255'],
        ]);

        $purchaseReturn = $this->procurementService->processPurchaseReturn($validated, $request->user());

        return response()->json([
            'status' => 'success',
            'message' => "Purchase return {$purchaseReturn->return_number} processed successfully",
            'data' => $purchaseReturn,
        ], 201);
    }

    /**
     * View purchase return details.
     */
    public function show(Request $request, PurchaseReturn $purchaseReturn): JsonResponse
    {
        if ($purchaseReturn->pharmacy_id !== $request->user()->pharmacy_id) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized access.'], 403);
        }

        $purchaseReturn->load([
            'items.product:id,name,brand_name,generic_name,sku',
            'items.batch:id,batch_number,expiry_date',
            'supplier',
            'processor:id,name',
            'branch:id,name',
            'goodsReceivedNote',
        ]);

        return response()->json([
            'status' => 'success',
            'data' => $purchaseReturn,
        ]);
    }
}
