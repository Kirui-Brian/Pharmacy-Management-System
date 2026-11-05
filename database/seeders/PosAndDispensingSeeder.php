<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Pharmacy;
use App\Models\Prescription;
use App\Models\Product;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class PosAndDispensingSeeder extends Seeder
{
    public function run(): void
    {
        $pharmacy = Pharmacy::first();
        $branch = Branch::where('pharmacy_id', $pharmacy->id)->first();
        $owner = User::where('email', 'owner@afyameds.co.ke')->first();

        // 1. Customers
        $c1 = Customer::firstOrCreate([
            'pharmacy_id' => $pharmacy->id,
            'phone' => '0712345678',
        ], [
            'branch_id' => $branch->id,
            'name' => 'Faith Wanjiku',
            'email' => 'faith.wanjiku@gmail.com',
            'id_or_passport' => '28472910',
            'gender' => 'female',
            'date_of_birth' => '1992-05-14',
            'customer_category' => 'regular',
            'credit_limit' => 0.00,
            'outstanding_balance' => 0.00,
            'notes' => 'Regular outpatient customer.',
            'is_active' => true,
        ]);

        $c2 = Customer::firstOrCreate([
            'pharmacy_id' => $pharmacy->id,
            'phone' => '0722334455',
        ], [
            'branch_id' => $branch->id,
            'name' => 'David Omondi',
            'email' => 'david.omondi@yahoo.com',
            'id_or_passport' => '22119933',
            'gender' => 'male',
            'date_of_birth' => '1984-11-20',
            'customer_category' => 'credit',
            'credit_limit' => 20000.00,
            'outstanding_balance' => 0.00,
            'notes' => 'Chronic care credit account. 30 days terms.',
            'is_active' => true,
        ]);

        $c3 = Customer::firstOrCreate([
            'pharmacy_id' => $pharmacy->id,
            'phone' => '0733445566',
        ], [
            'branch_id' => $branch->id,
            'name' => 'Amani Medical Centre',
            'email' => 'procurement@amanimedical.co.ke',
            'id_or_passport' => 'CPR/2021/4982',
            'gender' => null,
            'date_of_birth' => null,
            'customer_category' => 'corporate',
            'credit_limit' => 100000.00,
            'outstanding_balance' => 0.00,
            'notes' => 'Clinic bulk purchase account.',
            'is_active' => true,
        ]);

        // 2. Prescriptions
        $augmentin = Product::where('name', 'like', '%Augmentin%')->first();
        $panadol = Product::where('name', 'like', '%Panadol%')->first();
        $metformin = Product::where('name', 'like', '%Metformin%')->first();

        if ($augmentin && $panadol) {
            $rx1 = Prescription::firstOrCreate([
                'pharmacy_id' => $pharmacy->id,
                'prescription_number' => 'RX-202611-001',
            ], [
                'branch_id' => $branch->id,
                'customer_id' => $c1->id,
                'patient_name' => 'Faith Wanjiku',
                'prescriber_name' => 'Dr. Samuel Koech',
                'prescriber_facility' => 'Nairobi Hospital',
                'prescriber_license' => 'KMPDC/2018/7421',
                'prescriber_phone' => '0722112233',
                'prescription_date' => Carbon::now()->subDays(1)->toDateString(),
                'diagnosis_notes' => 'Upper respiratory tract infection (URTI) with mild pyrexia.',
                'status' => 'pending',
                'created_by' => $owner->id,
            ]);

            $rx1->items()->firstOrCreate([
                'product_id' => $augmentin->id,
            ], [
                'dosage_instruction' => '1 tablet twice daily after meals',
                'frequency' => 'BD (12 hourly)',
                'duration_days' => 7,
                'quantity_prescribed' => 14,
                'quantity_dispensed' => 0,
                'remaining_quantity' => 14,
                'notes' => 'Complete full 7-day course.',
            ]);

            $rx1->items()->firstOrCreate([
                'product_id' => $panadol->id,
            ], [
                'dosage_instruction' => '2 tablets 3 times daily as needed for pain/fever',
                'frequency' => 'TDS (8 hourly PRN)',
                'duration_days' => 3,
                'quantity_prescribed' => 18,
                'quantity_dispensed' => 0,
                'remaining_quantity' => 18,
                'notes' => 'Do not exceed 8 tablets in 24 hours.',
            ]);
        }

        if ($metformin) {
            $rx2 = Prescription::firstOrCreate([
                'pharmacy_id' => $pharmacy->id,
                'prescription_number' => 'RX-202611-002',
            ], [
                'branch_id' => $branch->id,
                'customer_id' => $c2->id,
                'patient_name' => 'David Omondi',
                'prescriber_name' => 'Dr. Grace Ndungu',
                'prescriber_facility' => 'Aga Khan University Hospital',
                'prescriber_license' => 'KMPDC/2014/3990',
                'prescriber_phone' => '0733556677',
                'prescription_date' => Carbon::now()->toDateString(),
                'diagnosis_notes' => 'Type 2 Diabetes Mellitus glycemic management.',
                'status' => 'pending',
                'created_by' => $owner->id,
            ]);

            $rx2->items()->firstOrCreate([
                'product_id' => $metformin->id,
            ], [
                'dosage_instruction' => '1 tablet twice daily with breakfast and dinner',
                'frequency' => 'BD with meals',
                'duration_days' => 30,
                'quantity_prescribed' => 60,
                'quantity_dispensed' => 0,
                'remaining_quantity' => 60,
                'notes' => 'Monitor fasting blood sugar regularly.',
            ]);
        }
    }
}
