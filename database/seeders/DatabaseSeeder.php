<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            PharmacySeeder::class,
            UserSeeder::class,
            SystemSettingSeeder::class,
            InventorySeeder::class,
            PosAndDispensingSeeder::class,
            ProcurementSeeder::class,
            AccountingSeeder::class,
        ]);
    }
}
