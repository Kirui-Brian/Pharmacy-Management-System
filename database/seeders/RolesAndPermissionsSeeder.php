<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // 1. Define all granular permissions
        $permissions = [
            // POS
            'pos.access',
            'pos.checkout',
            'pos.apply_discount',
            'pos.process_return',
            'pos.view_history',

            // Dispensing & Prescriptions
            'dispensing.view',
            'dispensing.create',
            'dispensing.verify_controlled',
            'prescriptions.view',
            'prescriptions.create',
            'prescriptions.edit',

            // Inventory & Batches
            'inventory.view',
            'inventory.manage_products',
            'inventory.manage_batches',
            'inventory.adjust_stock',
            'inventory.view_movements',
            'inventory.expiry_alerts',

            // Procurement
            'procurement.view_orders',
            'procurement.create_order',
            'procurement.receive_grn',
            'suppliers.manage',
            'suppliers.pay',

            // Customers & Credit
            'customers.view',
            'customers.create',
            'customers.approve_credit',
            'customers.receive_payment',

            // Accounting & Finance
            'finance.view_expenses',
            'finance.create_expense',
            'finance.chart_of_accounts',
            'finance.journal_entries',
            'finance.view_pnl',
            'finance.view_cashflow',

            // Reporting
            'reports.sales',
            'reports.inventory',
            'reports.financial',
            'reports.dispensing',
            'reports.export',

            // Management & Settings
            'users.manage',
            'roles.manage',
            'settings.manage',
            'audit_logs.view',
        ];

        foreach ($permissions as $permissionName) {
            Permission::findOrCreate($permissionName, 'web');
        }

        // 2. Super Admin Role
        $superAdmin = Role::findOrCreate('super-admin', 'web');
        $superAdmin->givePermissionTo(Permission::all());

        // 3. Pharmacy Owner Role
        $owner = Role::findOrCreate('owner', 'web');
        $owner->givePermissionTo(Permission::all());

        // 4. Pharmacy Manager Role
        $manager = Role::findOrCreate('manager', 'web');
        $manager->givePermissionTo([
            'pos.access', 'pos.checkout', 'pos.apply_discount', 'pos.process_return', 'pos.view_history',
            'dispensing.view', 'dispensing.create', 'dispensing.verify_controlled',
            'prescriptions.view', 'prescriptions.create', 'prescriptions.edit',
            'inventory.view', 'inventory.manage_products', 'inventory.manage_batches', 'inventory.adjust_stock', 'inventory.view_movements', 'inventory.expiry_alerts',
            'procurement.view_orders', 'procurement.create_order', 'procurement.receive_grn', 'suppliers.manage', 'suppliers.pay',
            'customers.view', 'customers.create', 'customers.approve_credit', 'customers.receive_payment',
            'finance.view_expenses', 'finance.create_expense', 'finance.view_pnl', 'finance.view_cashflow',
            'reports.sales', 'reports.inventory', 'reports.financial', 'reports.dispensing', 'reports.export',
            'users.manage', 'audit_logs.view'
        ]);

        // 5. Pharmacist Role
        $pharmacist = Role::findOrCreate('pharmacist', 'web');
        $pharmacist->givePermissionTo([
            'pos.access', 'pos.checkout', 'pos.view_history',
            'dispensing.view', 'dispensing.create', 'dispensing.verify_controlled',
            'prescriptions.view', 'prescriptions.create', 'prescriptions.edit',
            'inventory.view', 'inventory.manage_batches', 'inventory.expiry_alerts',
            'customers.view', 'customers.create',
            'reports.dispensing'
        ]);

        // 6. Cashier Role
        $cashier = Role::findOrCreate('cashier', 'web');
        $cashier->givePermissionTo([
            'pos.access', 'pos.checkout', 'pos.view_history',
            'customers.view', 'customers.create', 'customers.receive_payment',
            'reports.sales'
        ]);

        // 7. Inventory Manager Role
        $inventoryManager = Role::findOrCreate('inventory-manager', 'web');
        $inventoryManager->givePermissionTo([
            'inventory.view', 'inventory.manage_products', 'inventory.manage_batches', 'inventory.adjust_stock', 'inventory.view_movements', 'inventory.expiry_alerts',
            'procurement.view_orders', 'procurement.create_order', 'procurement.receive_grn',
            'suppliers.manage',
            'reports.inventory'
        ]);

        // 8. Accountant Role
        $accountant = Role::findOrCreate('accountant', 'web');
        $accountant->givePermissionTo([
            'finance.view_expenses', 'finance.create_expense', 'finance.chart_of_accounts', 'finance.journal_entries', 'finance.view_pnl', 'finance.view_cashflow',
            'suppliers.manage', 'suppliers.pay',
            'customers.view', 'customers.receive_payment',
            'reports.sales', 'reports.financial', 'reports.export'
        ]);
    }
}
