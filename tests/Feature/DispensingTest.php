<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Pharmacy;
use App\Models\Prescription;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\InventorySeeder;
use Database\Seeders\PharmacySeeder;
use Database\Seeders\PosAndDispensingSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SystemSettingSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DispensingTest extends TestCase
{
    use RefreshDatabase;

    protected User $pharmacist;
    protected Pharmacy $pharmacy;
    protected Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            RolesAndPermissionsSeeder::class,
            PharmacySeeder::class,
            UserSeeder::class,
            SystemSettingSeeder::class,
            InventorySeeder::class,
            PosAndDispensingSeeder::class,
        ]);

        $this->pharmacy = Pharmacy::first();
        $this->branch = Branch::where('pharmacy_id', $this->pharmacy->id)->first();
        $this->pharmacist = User::where('email', 'pharmacist@afyameds.co.ke')->first();
    }

    public function test_can_register_new_prescription(): void
    {
        $augmentin = Product::where('name', 'like', '%Augmentin%')->first();
        $customer = Customer::first();

        $payload = [
            'customer_id' => $customer->id,
            'patient_name' => 'Lucy Mwangi',
            'prescription_number' => 'RX-TEST-0099',
            'prescriber_name' => 'Dr. James Kariuki',
            'prescriber_facility' => 'Mater Hospital',
            'prescription_date' => now()->toDateString(),
            'diagnosis_notes' => 'Dental abscess',
            'items' => [
                [
                    'product_id' => $augmentin->id,
                    'dosage_instruction' => '1 tablet 12 hourly',
                    'frequency' => 'BD',
                    'duration_days' => 5,
                    'quantity_prescribed' => 10,
                ],
            ],
        ];

        $response = $this->actingAs($this->pharmacist, 'sanctum')
            ->postJson('/api/v1/prescriptions', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.prescription_number', 'RX-TEST-0099');

        $this->assertDatabaseHas('prescriptions', [
            'prescription_number' => 'RX-TEST-0099',
            'patient_name' => 'Lucy Mwangi',
            'status' => 'pending',
        ]);
    }

    public function test_dispensing_against_prescription_updates_remaining_quantities(): void
    {
        $prescription = Prescription::with('items')->first();
        $rxItem = $prescription->items->first();
        $dispenseQty = 5;

        $dispensePayload = [
            'prescription_id' => $prescription->id,
            'customer_id' => $prescription->customer_id,
            'pharmacist_notes' => 'Patient advised on finishing antibiotic course.',
            'items' => [
                [
                    'product_id' => $rxItem->product_id,
                    'quantity' => $dispenseQty,
                    'unit_selling_price' => 100.00,
                ],
            ],
            'payments' => [
                [
                    'payment_method' => 'cash',
                    'amount' => 500.00,
                ],
            ],
        ];

        $response = $this->actingAs($this->pharmacist, 'sanctum')
            ->postJson('/api/v1/dispensations', $dispensePayload);

        $response->assertStatus(201)
            ->assertJsonPath('status', 'success');

        // Check prescription item remaining quantity
        $rxItem->refresh();
        $this->assertEquals(5, (float) $rxItem->quantity_dispensed);
        $this->assertEquals((float) $rxItem->quantity_prescribed - 5, (float) $rxItem->remaining_quantity);

        // Check prescription status is now partially_dispensed or fully_dispensed
        $prescription->refresh();
        $this->assertContains($prescription->status, ['partially_dispensed', 'fully_dispensed']);

        // Check dispensation audit trail was logged
        $this->assertDatabaseHas('dispensations', [
            'prescription_id' => $prescription->id,
            'dispensed_by' => $this->pharmacist->id,
        ]);
    }

    public function test_can_retrieve_patient_dispensing_history(): void
    {
        $customer = Customer::first();

        $response = $this->actingAs($this->pharmacist, 'sanctum')
            ->getJson("/api/v1/dispensations/patient-history?customer_id={$customer->id}");

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success');
    }
}
