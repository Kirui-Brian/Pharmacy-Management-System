<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Dispensation;
use App\Services\DispensingService;
use App\Services\PosService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DispensingController extends Controller
{
    protected DispensingService $dispensingService;
    protected PosService $posService;

    public function __construct(DispensingService $dispensingService, PosService $posService)
    {
        $this->dispensingService = $dispensingService;
        $this->posService = $posService;
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $branchId = $request->query('branch_id', $user->branch_id);

        $query = Dispensation::where('pharmacy_id', $user->pharmacy_id)
            ->with(['items.product.unit', 'dispenser:id,name', 'prescription', 'customer', 'sale:id,receipt_number']);

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('patient_name', 'like', "%{$search}%")
                    ->orWhereHas('items.product', function ($pq) use ($search) {
                        $pq->where('name', 'like', "%{$search}%");
                    });
            });
        }

        $dispensations = $query->latest('dispensation_date')->paginate(25);

        return response()->json([
            'status' => 'success',
            'data' => $dispensations,
        ]);
    }

    /**
     * Dispense against prescription via POS checkout.
     */
    public function dispense(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'prescription_id' => ['required', 'exists:prescriptions,id'],
            'customer_id' => ['nullable', 'exists:customers,id'],
            'pharmacist_notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'items.*.unit_selling_price' => ['nullable', 'numeric', 'min:0'],
            'payments' => ['required', 'array', 'min:1'],
            'payments.*.payment_method' => ['required', 'in:cash,mpesa,card,bank_transfer,credit'],
            'payments.*.amount' => ['required', 'numeric', 'min:0.01'],
            'payments.*.amount_tendered' => ['nullable', 'numeric', 'min:0'],
            'payments.*.reference_number' => ['nullable', 'string', 'max:100'],
        ]);

        $sale = $this->posService->createSale($validated, $user);

        return response()->json([
            'status' => 'success',
            'message' => 'Prescription dispensed successfully.',
            'data' => $sale,
        ], 201);
    }

    /**
     * Retrieve complete patient dispensing history.
     */
    public function patientHistory(Request $request): JsonResponse
    {
        $user = $request->user();
        $customerId = $request->query('customer_id');
        $patientName = $request->query('patient_name');

        $history = $this->dispensingService->getPatientDispensingHistory(
            $user->pharmacy_id,
            $customerId ? (int) $customerId : null,
            $patientName
        );

        return response()->json([
            'status' => 'success',
            'data' => $history,
        ]);
    }
}
