<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Pharmacy;
use Illuminate\Database\Seeder;

class PharmacySeeder extends Seeder
{
    public function run(): void
    {
        $pharmacy = Pharmacy::firstOrCreate(
            ['tax_pin' => 'P051234567Z'],
            [
                'name' => 'Afya Meds Pharmacy Ltd',
                'registration_number' => 'PPB/PH/2026/0411',
                'email' => 'info@afyameds.co.ke',
                'phone' => '+254 700 123 456',
                'address' => 'Afya Centre, Tom Mboya Street, Nairobi, Kenya',
                'currency_code' => 'KES',
                'currency_symbol' => 'KES',
            ]
        );

        Branch::firstOrCreate(
            ['code' => 'KUR-01'],
            [
                'pharmacy_id' => $pharmacy->id,
                'name' => 'Kuresoi Chemist',
                'phone' => '+254 700 123 457',
                'email' => 'kuresoi@afyameds.co.ke',
                'address' => 'Ground Floor, Afya Centre, Tom Mboya St',
                'is_main' => true,
                'is_active' => true,
            ]
        );

        Branch::firstOrCreate(
            ['code' => 'WST-02'],
            [
                'pharmacy_id' => $pharmacy->id,
                'name' => 'Westlands Medical Branch',
                'phone' => '+254 700 123 458',
                'email' => 'westlands@afyameds.co.ke',
                'address' => 'Sarit Centre Wing B, Westlands, Nairobi',
                'is_main' => false,
                'is_active' => true,
            ]
        );
    }
}
