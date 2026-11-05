<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Prescription;
use App\Services\DispensingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PrescriptionController extends Controller
{
    protected DispensingService $dispensingService;

    public function __construct(DispensingService $dispensingService)
    {
        $this->dispensingService = $dispensingService;
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $branchId = $request->query('branch_id', $user->branch_id);

        $query = Prescription::where('pharmacy_id', $user->pharmacy_id)
            ->with(['items.product.unit', 'customer', 'creator:id,name']);

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('prescription_number', 'like', "%{$search}%")
                    ->orWhere('patient_name', 'like', "%{$search}%")
                    ->orWhere('prescriber_name', 'like', "%{$search}%");
            });
        }

        $prescriptions = $query->latest()->paginate(25);

        return response()->json([
            'status' => 'success',
            'data' => $prescriptions,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'customer_id' => ['nullable', 'exists:customers,id'],
            'patient_name' => ['required', 'string', 'max:255'],
            'prescription_number' => ['nullable', 'string', 'max:50'],
            'prescriber_name' => ['required', 'string', 'max:255'],
            'prescriber_facility' => ['nullable', 'string', 'max:255'],
            'prescriber_license' => ['nullable', 'string', 'max:100'],
            'prescriber_phone' => ['nullable', 'string', 'max:50'],
            'prescription_date' => ['required', 'date'],
            'diagnosis_notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.dosage_instruction' => ['required', 'string', 'max:255'],
            'items.*.frequency' => ['nullable', 'string', 'max:100'],
            'items.*.duration_days' => ['required', 'integer', 'min:1'],
            'items.*.quantity_prescribed' => ['required', 'numeric', 'min:0.01'],
            'items.*.notes' => ['nullable', 'string'],
        ]);

        $prescription = $this->dispensingService->createPrescription($validated, $user);

        return response()->json([
            'status' => 'success',
            'message' => 'Prescription registered successfully.',
            'data' => $prescription,
        ], 201);
    }

    public function show(Request $request, Prescription $prescription): JsonResponse
    {
        $user = $request->user();
        if ($prescription->pharmacy_id !== $user->pharmacy_id) {
            abort(403);
        }

        $prescription->load([
            'items.product.unit',
            'customer',
            'creator:id,name',
            'dispensations.items.product',
            'dispensations.dispenser:id,name'
        ]);

        return response()->json([
            'status' => 'success',
            'data' => $prescription,
        ]);
    }
}
