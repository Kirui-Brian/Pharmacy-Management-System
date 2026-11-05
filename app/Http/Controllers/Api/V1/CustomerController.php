<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = Customer::where('pharmacy_id', $user->pharmacy_id);

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('id_or_passport', 'like', "%{$search}%");
            });
        }

        if ($category = $request->query('category')) {
            $query->where('customer_category', $category);
        }

        $customers = $query->orderBy('name')->paginate(30);

        return response()->json([
            'status' => 'success',
            'data' => $customers,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'id_or_passport' => ['nullable', 'string', 'max:50'],
            'gender' => ['nullable', 'in:male,female,other'],
            'date_of_birth' => ['nullable', 'date'],
            'customer_category' => ['required', 'in:walk_in,regular,credit,insurance,corporate'],
            'credit_limit' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ]);

        $validated['pharmacy_id'] = $user->pharmacy_id;
        $validated['branch_id'] = $user->branch_id;
        $validated['credit_limit'] = $validated['credit_limit'] ?? 0.00;
        $validated['outstanding_balance'] = 0.00;

        $customer = Customer::create($validated);

        AuditLog::create([
            'pharmacy_id' => $user->pharmacy_id,
            'branch_id' => $user->branch_id,
            'user_id' => $user->id,
            'action' => 'customer.created',
            'description' => "Registered customer {$customer->name}",
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Customer registered successfully.',
            'data' => $customer,
        ], 201);
    }

    public function show(Request $request, Customer $customer): JsonResponse
    {
        $user = $request->user();
        if ($customer->pharmacy_id !== $user->pharmacy_id) {
            abort(403);
        }

        $customer->load([
            'sales' => function ($q) {
                $q->latest()->take(10)->with('payments');
            },
            'prescriptions' => function ($q) {
                $q->latest()->take(10)->with('items.product');
            },
            'dispensations' => function ($q) {
                $q->latest()->take(10)->with('items.product');
            }
        ]);

        return response()->json([
            'status' => 'success',
            'data' => $customer,
        ]);
    }
}
