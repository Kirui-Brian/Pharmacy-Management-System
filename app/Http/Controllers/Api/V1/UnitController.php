<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Unit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UnitController extends Controller
{
    public function index(): JsonResponse
    {
        $units = Unit::where('is_active', true)
            ->orderBy('name')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $units,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'short_name' => ['required', 'string', 'max:30'],
            'base_unit' => ['required', 'string', 'max:50'],
            'operator' => ['required', 'in:*,/'],
            'operation_value' => ['required', 'numeric', 'min:0.0001'],
        ]);

        $unit = Unit::create($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Unit of measurement created successfully',
            'data' => $unit,
        ], 201);
    }
}
