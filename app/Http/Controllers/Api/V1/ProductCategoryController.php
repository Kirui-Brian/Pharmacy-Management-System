<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ProductCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductCategoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $pharmacyId = $request->user()->pharmacy_id;
        $categories = ProductCategory::where('pharmacy_id', $pharmacyId)
            ->where('is_active', true)
            ->withCount('products')
            ->orderBy('name')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $categories,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'code' => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string'],
        ]);

        $category = ProductCategory::create([
            ...$validated,
            'pharmacy_id' => $request->user()->pharmacy_id,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Product category created successfully',
            'data' => $category,
        ], 201);
    }
}
