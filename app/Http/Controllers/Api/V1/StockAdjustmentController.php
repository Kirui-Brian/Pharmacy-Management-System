<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\StockAdjustment;
use App\Services\InventoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StockAdjustmentController extends Controller
{
    public function __construct(
        protected InventoryService $inventoryService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $branchId = $request->query('branch_id', $user->branch_id);

        $adjustments = StockAdjustment::with([
            'user:id,name,email',
            'approver:id,name,email',
            'items.product:id,name,sku',
            'items.batch:id,batch_number,expiry_date',
        ])
        ->where('branch_id', $branchId)
        ->latest()
        ->paginate(20);

        return response()->json([
            'status' => 'success',
            'data' => $adjustments,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'branch_id' => ['nullable', 'exists:branches,id'],
            'reason_type' => ['required', 'in:inventory_count_discrepancy,damaged,expired,missing_theft,internal_use,other'],
            'notes' => ['nullable', 'string', 'max:500'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_batch_id' => ['required', 'exists:product_batches,id'],
            'items.*.new_quantity' => ['required', 'numeric', 'min:0'],
            'items.*.notes' => ['nullable', 'string'],
        ]);

        $branchId = $validated['branch_id'] ?? $request->user()->branch_id;

        $adjustment = $this->inventoryService->adjustStock(
            branchId: $branchId,
            userId: $request->user()->id,
            reasonType: $validated['reason_type'],
            items: $validated['items'],
            notes: $validated['notes'] ?? null
        );

        return response()->json([
            'status' => 'success',
            'message' => "Stock adjustment {$adjustment->adjustment_number} processed successfully.",
            'data' => $adjustment->load(['items.product', 'items.batch']),
        ], 201);
    }
}
