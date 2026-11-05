<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Pharmacy;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $pharmacy = Pharmacy::first();
        $mainBranch = Branch::where('is_main', true)->first();
        $westlandsBranch = Branch::where('code', 'WST-02')->first();

        // 1. Owner (Super Admin permissions)
        $owner = User::firstOrCreate(
            ['email' => 'owner@afyameds.co.ke'],
            [
                'pharmacy_id' => $pharmacy->id,
                'branch_id' => $mainBranch?->id,
                'name' => 'Dr. Leonard Kirui (Owner)',
                'phone' => '+254 711 000 001',
                'password' => Hash::make('password123'),
                'is_active' => true,
            ]
        );
        $owner->syncRoles(['owner']);

        // 2. Pharmacy Manager
        $manager = User::firstOrCreate(
            ['email' => 'manager@afyameds.co.ke'],
            [
                'pharmacy_id' => $pharmacy->id,
                'branch_id' => $mainBranch?->id,
                'name' => 'Sarah Nduta (Pharmacy Manager)',
                'phone' => '+254 711 000 002',
                'password' => Hash::make('password123'),
                'is_active' => true,
            ]
        );
        $manager->syncRoles(['manager']);

        // 3. Registered Pharmacist
        $pharmacist = User::firstOrCreate(
            ['email' => 'pharmacist@afyameds.co.ke'],
            [
                'pharmacy_id' => $pharmacy->id,
                'branch_id' => $mainBranch?->id,
                'name' => 'Pharm. David Ochieng',
                'phone' => '+254 711 000 003',
                'license_number' => 'PPB/REG/2018/8842',
                'password' => Hash::make('password123'),
                'is_active' => true,
            ]
        );
        $pharmacist->syncRoles(['pharmacist']);

        // 4. Cashier
        $cashier = User::firstOrCreate(
            ['email' => 'cashier@afyameds.co.ke'],
            [
                'pharmacy_id' => $pharmacy->id,
                'branch_id' => $mainBranch?->id,
                'name' => 'Mercy Chebet (Cashier)',
                'phone' => '+254 711 000 004',
                'password' => Hash::make('password123'),
                'is_active' => true,
            ]
        );
        $cashier->syncRoles(['cashier']);

        // 5. Inventory Manager
        $invManager = User::firstOrCreate(
            ['email' => 'inventory@afyameds.co.ke'],
            [
                'pharmacy_id' => $pharmacy->id,
                'branch_id' => $mainBranch?->id,
                'name' => 'Brian Mwangi (Inventory Officer)',
                'phone' => '+254 711 000 005',
                'password' => Hash::make('password123'),
                'is_active' => true,
            ]
        );
        $invManager->syncRoles(['inventory-manager']);

        // 6. Accountant
        $accountant = User::firstOrCreate(
            ['email' => 'accountant@afyameds.co.ke'],
            [
                'pharmacy_id' => $pharmacy->id,
                'branch_id' => $mainBranch?->id,
                'name' => 'Eunice Wanjiku (Accountant)',
                'phone' => '+254 711 000 006',
                'password' => Hash::make('password123'),
                'is_active' => true,
            ]
        );
        $accountant->syncRoles(['accountant']);
    }
}
