<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $branchId = $request->query('branch_id', $user->branch_id);

        $query = Product::where('pharmacy_id', $user->pharmacy_id)
            ->where('is_active', true)
            ->with(['category:id,name', 'manufacturer:id,name', 'unit:id,name,short_name,base_unit,operator,operation_value'])
            ->withSum([
                'batches as branch_stock' => function ($q) use ($branchId) {
                    $q->where('status', 'active')
                        ->where('expiry_date', '>', now()->toDateString());
                    if ($branchId) {
                        $q->where('branch_id', $branchId);
                    }
                }
            ], 'quantity_remaining');

        // Search by name, generic name, brand name, SKU, barcode
        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('generic_name', 'like', "%{$search}%")
                    ->orWhere('brand_name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%")
                    ->orWhere('barcode', 'like', "%{$search}%");
            });
        }

        // Category filter
        if ($catId = $request->query('category_id')) {
            $query->where('category_id', $catId);
        }

        // Prescription filter
        if ($request->has('is_prescription_required')) {
            $query->where('is_prescription_required', filter_var($request->query('is_prescription_required'), FILTER_VALIDATE_BOOLEAN));
        }

        // Controlled medicine filter
        if ($request->has('is_controlled')) {
            $query->where('is_controlled', filter_var($request->query('is_controlled'), FILTER_VALIDATE_BOOLEAN));
        }

        // Low stock filter
        if ($request->boolean('low_stock')) {
            $query->havingRaw('COALESCE(branch_stock, 0) <= products.reorder_level');
        }

        $perPage = min((int) $request->query('per_page', 20), 100);
        $products = $query->orderBy('name')->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'data' => $products,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'category_id' => ['required', 'exists:product_categories,id'],
            'manufacturer_id' => ['nullable', 'exists:manufacturers,id'],
            'unit_id' => ['required', 'exists:units,id'],
            'name' => ['required', 'string', 'max:255'],
            'generic_name' => ['required', 'string', 'max:255'],
            'brand_name' => ['nullable', 'string', 'max:255'],
            'sku' => ['required', 'string', 'max:100', 'unique:products,sku'],
            'barcode' => ['nullable', 'string', 'max:100'],
            'pack_size' => ['required', 'integer', 'min:1'],
            'purchase_price' => ['required', 'numeric', 'min:0'],
            'selling_price' => ['required', 'numeric', 'min:0'],
            'wholesale_price' => ['nullable', 'numeric', 'min:0'],
            'reorder_level' => ['required', 'integer', 'min:0'],
            'max_stock_level' => ['required', 'integer', 'min:1'],
            'is_prescription_required' => ['boolean'],
            'is_controlled' => ['boolean'],
            'tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'description' => ['nullable', 'string'],
        ]);

        $product = Product::create([
            ...$validated,
            'pharmacy_id' => $request->user()->pharmacy_id,
            'is_active' => true,
        ]);

        AuditLog::log('product_created', "Product '{$product->name}' (SKU: {$product->sku}) created.", $product);

        return response()->json([
            'status' => 'success',
            'message' => 'Product created successfully',
            'data' => $product->load(['category', 'manufacturer', 'unit']),
        ], 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $branchId = $request->query('branch_id', $user->branch_id);

        $product = Product::where('pharmacy_id', $user->pharmacy_id)
            ->with([
                'category',
                'manufacturer',
                'unit',
                'batches' => function ($q) use ($branchId) {
                    if ($branchId) {
                        $q->where('branch_id', $branchId);
                    }
                    $q->orderBy('expiry_date', 'asc');
                },
                'batches.supplier:id,name',
            ])
            ->findOrFail($id);

        return response()->json([
            'status' => 'success',
            'data' => $product,
        ]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $product = Product::where('pharmacy_id', $user->pharmacy_id)->findOrFail($id);

        $validated = $request->validate([
            'category_id' => ['required', 'exists:product_categories,id'],
            'manufacturer_id' => ['nullable', 'exists:manufacturers,id'],
            'unit_id' => ['required', 'exists:units,id'],
            'name' => ['required', 'string', 'max:255'],
            'generic_name' => ['required', 'string', 'max:255'],
            'brand_name' => ['nullable', 'string', 'max:255'],
            'sku' => ['required', 'string', 'max:100', 'unique:products,sku,' . $product->id],
            'barcode' => ['nullable', 'string', 'max:100'],
            'pack_size' => ['required', 'integer', 'min:1'],
            'purchase_price' => ['required', 'numeric', 'min:0'],
            'selling_price' => ['required', 'numeric', 'min:0'],
            'wholesale_price' => ['nullable', 'numeric', 'min:0'],
            'reorder_level' => ['required', 'integer', 'min:0'],
            'max_stock_level' => ['required', 'integer', 'min:1'],
            'is_prescription_required' => ['boolean'],
            'is_controlled' => ['boolean'],
            'tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'description' => ['nullable', 'string'],
        ]);

        $old = $product->toArray();
        $product->update($validated);

        AuditLog::log('product_updated', "Product '{$product->name}' updated.", $product, $old, $product->toArray());

        return response()->json([
            'status' => 'success',
            'message' => 'Product updated successfully',
            'data' => $product->load(['category', 'manufacturer', 'unit']),
        ]);
    }
}
