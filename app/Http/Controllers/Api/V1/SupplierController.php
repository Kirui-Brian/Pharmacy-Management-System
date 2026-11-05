<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupplierController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $pharmacyId = $request->user()->pharmacy_id;
        $query = Supplier::where('pharmacy_id', $pharmacyId);

        if ($request->filled('active')) {
            $query->where('is_active', $request->boolean('active'));
        }

        if ($request->filled('search')) {
            $s = $request->query('search');
            $query->where(function ($q) use ($s) {
                $q->where('name', 'like', "%{$s}%")
                  ->orWhere('phone', 'like', "%{$s}%")
                  ->orWhere('contact_person', 'like', "%{$s}%");
            });
        }

        $suppliers = $query->withCount('batches')
            ->orderBy('name')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $suppliers,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string'],
            'tax_pin' => ['nullable', 'string', 'max:50'],
            'payment_terms_days' => ['nullable', 'integer', 'min:0'],
            'credit_limit' => ['nullable', 'numeric', 'min:0'],
        ]);

        $supplier = Supplier::create([
            ...$validated,
            'pharmacy_id' => $request->user()->pharmacy_id,
            'outstanding_balance' => 0.00,
            'is_active' => true,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Supplier created successfully',
            'data' => $supplier,
        ], 201);
    }

    public function show(Request $request, Supplier $supplier): JsonResponse
    {
        if ($supplier->pharmacy_id !== $request->user()->pharmacy_id) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized access.'], 403);
        }

        $supplier->load([
            'purchaseOrders' => function ($q) { $q->latest()->limit(10); },
            'goodsReceivedNotes' => function ($q) { $q->latest()->limit(10); },
            'invoices' => function ($q) { $q->latest()->limit(10); },
            'payments' => function ($q) { $q->latest()->limit(10); },
        ]);

        return response()->json([
            'status' => 'success',
            'data' => $supplier,
        ]);
    }

    public function update(Request $request, Supplier $supplier): JsonResponse
    {
        if ($supplier->pharmacy_id !== $request->user()->pharmacy_id) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized access.'], 403);
        }

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'phone' => ['sometimes', 'required', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string'],
            'tax_pin' => ['nullable', 'string', 'max:50'],
            'payment_terms_days' => ['nullable', 'integer', 'min:0'],
            'credit_limit' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $supplier->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Supplier updated successfully',
            'data' => $supplier,
        ]);
    }
}
