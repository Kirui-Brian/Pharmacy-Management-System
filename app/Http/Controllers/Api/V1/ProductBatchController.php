<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ProductBatch;
use App\Services\InventoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductBatchController extends Controller
{
    public function __construct(
        protected InventoryService $inventoryService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $branchId = $request->query('branch_id', $user->branch_id);

        $query = ProductBatch::with(['product:id,name,generic_name,sku,category_id', 'product.category:id,name', 'supplier:id,name'])
            ->where('branch_id', $branchId);

        if ($productId = $request->query('product_id')) {
            $query->where('product_id', $productId);
        }

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('batch_number', 'like', "%{$search}%")
                    ->orWhereHas('product', function ($pq) use ($search) {
                        $pq->where('name', 'like', "%{$search}%")
                            ->orWhere('generic_name', 'like', "%{$search}%")
                            ->orWhere('sku', 'like', "%{$search}%");
                    });
            });
        }

        $batches = $query->orderBy('expiry_date', 'asc')->paginate(25);

        return response()->json([
            'status' => 'success',
            'data' => $batches,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'branch_id' => ['nullable', 'exists:branches,id'],
            'product_id' => ['required', 'exists:products,id'],
            'supplier_id' => ['nullable', 'exists:suppliers,id'],
            'batch_number' => ['required', 'string', 'max:100'],
            'manufacturing_date' => ['nullable', 'date'],
            'expiry_date' => ['required', 'date', 'after:today'],
            'quantity' => ['required', 'numeric', 'min:0.0001'],
            'purchase_price_per_unit' => ['required', 'numeric', 'min:0'],
            'selling_price_per_unit' => ['required', 'numeric', 'min:0'],
            'reason' => ['nullable', 'string'],
        ]);

        $branchId = $validated['branch_id'] ?? $request->user()->branch_id;

        $batch = $this->inventoryService->receiveBatch(
            branchId: $branchId,
            productId: $validated['product_id'],
            batchNumber: $validated['batch_number'],
            quantity: (float) $validated['quantity'],
            purchasePricePerUnit: (float) $validated['purchase_price_per_unit'],
            sellingPricePerUnit: (float) $validated['selling_price_per_unit'],
            manufacturingDate: $validated['manufacturing_date'] ?? null,
            expiryDate: $validated['expiry_date'],
            supplierId: $validated['supplier_id'] ?? null,
            reason: $validated['reason'] ?? 'Manual Stock Intake'
        );

        return response()->json([
            'status' => 'success',
            'message' => "Batch '{$batch->batch_number}' received successfully with {$batch->quantity_received} units.",
            'data' => $batch->load(['product', 'supplier']),
        ], 201);
    }

    public function expiryAlerts(Request $request): JsonResponse
    {
        $user = $request->user();
        $branchId = $request->query('branch_id', $user->branch_id);

        $report = $this->inventoryService->getExpiryRiskReport($branchId);

        return response()->json([
            'status' => 'success',
            'data' => $report,
        ]);
    }
}
