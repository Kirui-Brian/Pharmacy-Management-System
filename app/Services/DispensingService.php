<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Dispensation;
use App\Models\Prescription;
use App\Models\PrescriptionItem;
use App\Models\Product;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DispensingService
{
    /**
     * Register a new doctor's prescription into the pharmacy registry.
     */
    public function createPrescription(array $data, User $user): Prescription
    {
        return DB::transaction(function () use ($data, $user) {
            $pharmacyId = $user->pharmacy_id;
            $branchId = $data['branch_id'] ?? $user->branch_id;

            $datePrefix = Carbon::now()->format('Ymd');
            $prescriptionNumber = $data['prescription_number'] ?? ("RX-" . strtoupper(substr(uniqid(), -5)));

            // Customer verification or auto-creation
            $customerId = $data['customer_id'] ?? null;
            $patientName = $data['patient_name'] ?? null;

            if ($customerId) {
                $customer = Customer::where('pharmacy_id', $pharmacyId)->find($customerId);
                if ($customer && empty($patientName)) {
                    $patientName = $customer->name;
                }
            }

            if (empty($patientName)) {
                throw ValidationException::withMessages([
                    'patient_name' => ['Patient name is required.'],
                ]);
            }

            $prescription = Prescription::create([
                'pharmacy_id' => $pharmacyId,
                'branch_id' => $branchId,
                'customer_id' => $customerId,
                'patient_name' => $patientName,
                'prescription_number' => $prescriptionNumber,
                'prescriber_name' => $data['prescriber_name'],
                'prescriber_facility' => $data['prescriber_facility'] ?? null,
                'prescriber_license' => $data['prescriber_license'] ?? null,
                'prescriber_phone' => $data['prescriber_phone'] ?? null,
                'prescription_date' => $data['prescription_date'] ?? Carbon::now()->toDateString(),
                'diagnosis_notes' => $data['diagnosis_notes'] ?? null,
                'status' => 'pending',
                'created_by' => $user->id,
            ]);

            $itemsData = $data['items'] ?? [];
            if (empty($itemsData)) {
                throw ValidationException::withMessages([
                    'items' => ['Prescription must contain at least one medicine.'],
                ]);
            }

            foreach ($itemsData as $index => $item) {
                $qty = (float) $item['quantity_prescribed'];
                if ($qty <= 0) {
                    throw ValidationException::withMessages([
                        "items.{$index}.quantity_prescribed" => ['Prescribed quantity must be greater than zero.'],
                    ]);
                }

                $prescription->items()->create([
                    'product_id' => $item['product_id'],
                    'dosage_instruction' => $item['dosage_instruction'] ?? 'As directed',
                    'frequency' => $item['frequency'] ?? null,
                    'duration_days' => (int) ($item['duration_days'] ?? 1),
                    'quantity_prescribed' => $qty,
                    'quantity_dispensed' => 0.00,
                    'remaining_quantity' => $qty,
                    'notes' => $item['notes'] ?? null,
                ]);
            }

            AuditLog::create([
                'pharmacy_id' => $pharmacyId,
                'branch_id' => $branchId,
                'user_id' => $user->id,
                'action' => 'dispensing.prescription_registered',
                'description' => "Registered prescription #{$prescriptionNumber} for patient {$patientName}",
                'ip_address' => request()->ip() ?? '127.0.0.1',
                'user_agent' => request()->userAgent() ?? 'CLI/System',
            ]);

            return $prescription->load(['items.product', 'customer', 'creator']);
        });
    }

    /**
     * Retrieve complete patient dispensing history.
     */
    public function getPatientDispensingHistory(int $pharmacyId, ?int $customerId, ?string $patientName)
    {
        $query = Dispensation::where('pharmacy_id', $pharmacyId)
            ->with(['items.product', 'dispenser:id,name', 'prescription', 'sale:id,receipt_number,total_amount']);

        if ($customerId) {
            $query->where('customer_id', $customerId);
        } elseif ($patientName) {
            $query->where('patient_name', 'like', "%{$patientName}%");
        }

        return $query->orderBy('dispensation_date', 'desc')->get();
    }
}
