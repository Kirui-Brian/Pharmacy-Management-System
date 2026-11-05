<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Manufacturer;
use App\Models\Pharmacy;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\ProductCategory;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Seeder;

class InventorySeeder extends Seeder
{
    public function run(): void
    {
        $pharmacy = Pharmacy::first();
        $branch = Branch::where('is_main', true)->first();
        $adminUser = User::first();

        // 1. Categories
        $categories = [
            ['name' => 'Antibiotics', 'code' => 'ANTI', 'description' => 'Systemic antibiotic medicines'],
            ['name' => 'Analgesics & Pain Relief', 'code' => 'ANAL', 'description' => 'Pain relievers and antipyretics'],
            ['name' => 'Antimalarials', 'code' => 'MAL', 'description' => 'Malaria treatment combinations (ACTs)'],
            ['name' => 'Antihypertensives & Cardiac', 'code' => 'CARD', 'description' => 'Cardiovascular and blood pressure medicines'],
            ['name' => 'Antidiabetics', 'code' => 'DIAB', 'description' => 'Diabetes glucose management medicines'],
            ['name' => 'Cough, Cold & Allergy', 'code' => 'RESP', 'description' => 'Respiratory, antihistamines, and cough syrups'],
            ['name' => 'Gastrointestinal', 'code' => 'GAST', 'description' => 'Antacids, PPIs, anti-ulcer medications'],
            ['name' => 'Controlled & Scheduled Drugs', 'code' => 'CTRL', 'description' => 'Restricted narcotics requiring register audit'],
        ];

        $categoryMap = [];
        foreach ($categories as $cat) {
            $categoryMap[$cat['name']] = ProductCategory::firstOrCreate(
                ['pharmacy_id' => $pharmacy->id, 'name' => $cat['name']],
                $cat
            );
        }

        // 2. Manufacturers
        $manufacturers = [
            ['name' => 'GlaxoSmithKline (GSK)', 'country' => 'United Kingdom'],
            ['name' => 'Cosmos Limited', 'country' => 'Kenya'],
            ['name' => 'Dawa Life Sciences', 'country' => 'Kenya'],
            ['name' => 'Novartis Pharma AG', 'country' => 'Switzerland'],
            ['name' => 'Bayer HealthCare', 'country' => 'Germany'],
            ['name' => 'Sanofi Aventis', 'country' => 'France'],
        ];

        $manufMap = [];
        foreach ($manufacturers as $m) {
            $manufMap[$m['name']] = Manufacturer::firstOrCreate(['name' => $m['name']], $m);
        }

        // 3. Units
        $units = [
            ['name' => 'Box of 100 Tablets', 'short_name' => 'box-100', 'base_unit' => 'tablet', 'operator' => '*', 'operation_value' => 100],
            ['name' => 'Blister of 14 Tablets', 'short_name' => 'blist-14', 'base_unit' => 'tablet', 'operator' => '*', 'operation_value' => 14],
            ['name' => 'Box of 24 Tablets', 'short_name' => 'box-24', 'base_unit' => 'tablet', 'operator' => '*', 'operation_value' => 24],
            ['name' => 'Single Tablet / Capsule', 'short_name' => 'tab', 'base_unit' => 'tablet', 'operator' => '*', 'operation_value' => 1],
            ['name' => 'Bottle 100ml Syrup', 'short_name' => 'btl-100ml', 'base_unit' => 'bottle', 'operator' => '*', 'operation_value' => 1],
            ['name' => 'Tube 30g Cream', 'short_name' => 'tube-30g', 'base_unit' => 'tube', 'operator' => '*', 'operation_value' => 1],
        ];

        $unitMap = [];
        foreach ($units as $u) {
            $unitMap[$u['short_name']] = Unit::firstOrCreate(['short_name' => $u['short_name']], $u);
        }

        // 4. Suppliers
        $suppliers = [
            [
                'name' => 'Harleys Limited',
                'contact_person' => 'Nitin Shah',
                'phone' => '+254 722 202 033',
                'email' => 'sales@harleysltd.com',
                'address' => 'Harleys House, Central Business District, Nairobi',
                'tax_pin' => 'P051111222A',
                'payment_terms_days' => 30,
            ],
            [
                'name' => 'Laborex Kenya Ltd',
                'contact_person' => 'Catherine Murage',
                'phone' => '+254 733 445 566',
                'email' => 'orders@laborex-kenya.com',
                'address' => 'Enterprise Road, Industrial Area, Nairobi',
                'tax_pin' => 'P052222333B',
                'payment_terms_days' => 45,
            ],
            [
                'name' => 'Transchem Pharmaceuticals',
                'contact_person' => 'Samuel Kibet',
                'phone' => '+254 710 889 900',
                'email' => 'info@transchem.co.ke',
                'address' => 'Road A, Off Enterprise Road, Nairobi',
                'tax_pin' => 'P053333444C',
                'payment_terms_days' => 30,
            ],
        ];

        $supplierMap = [];
        foreach ($suppliers as $s) {
            $supplierMap[$s['name']] = Supplier::firstOrCreate(
                ['pharmacy_id' => $pharmacy->id, 'name' => $s['name']],
                $s
            );
        }

        // 5. Products
        $products = [
            [
                'name' => 'Panadol Extra 500mg/65mg',
                'generic_name' => 'Paracetamol + Caffeine',
                'brand_name' => 'Panadol',
                'sku' => 'PAN-EXT-100',
                'barcode' => '616110001001',
                'category_id' => $categoryMap['Analgesics & Pain Relief']->id,
                'manufacturer_id' => $manufMap['GlaxoSmithKline (GSK)']->id,
                'unit_id' => $unitMap['box-100']->id,
                'pack_size' => 100,
                'purchase_price' => 8.50,
                'selling_price' => 15.00,
                'wholesale_price' => 12.00,
                'reorder_level' => 30,
                'max_stock_level' => 500,
                'is_prescription_required' => false,
                'is_controlled' => false,
                'tax_rate' => 0.00,
            ],
            [
                'name' => 'Augmentin 625mg Tablets',
                'generic_name' => 'Amoxicillin + Clavulanic Acid',
                'brand_name' => 'Augmentin',
                'sku' => 'AUG-625-14',
                'barcode' => '616110001002',
                'category_id' => $categoryMap['Antibiotics']->id,
                'manufacturer_id' => $manufMap['GlaxoSmithKline (GSK)']->id,
                'unit_id' => $unitMap['blist-14']->id,
                'pack_size' => 14,
                'purchase_price' => 75.00,
                'selling_price' => 120.00,
                'wholesale_price' => 100.00,
                'reorder_level' => 20,
                'max_stock_level' => 200,
                'is_prescription_required' => true,
                'is_controlled' => false,
                'tax_rate' => 0.00,
            ],
            [
                'name' => 'Coartem 20/120mg Tablets',
                'generic_name' => 'Artemether + Lumefantrine',
                'brand_name' => 'Coartem',
                'sku' => 'COA-20120-24',
                'barcode' => '616110001003',
                'category_id' => $categoryMap['Antimalarials']->id,
                'manufacturer_id' => $manufMap['Novartis Pharma AG']->id,
                'unit_id' => $unitMap['box-24']->id,
                'pack_size' => 24,
                'purchase_price' => 350.00,
                'selling_price' => 550.00,
                'wholesale_price' => 480.00,
                'reorder_level' => 15,
                'max_stock_level' => 150,
                'is_prescription_required' => true,
                'is_controlled' => false,
                'tax_rate' => 0.00,
            ],
            [
                'name' => 'Amoxil 500mg Capsules',
                'generic_name' => 'Amoxicillin Trihydrate',
                'brand_name' => 'Amoxil',
                'sku' => 'AMX-500-100',
                'barcode' => '616110001004',
                'category_id' => $categoryMap['Antibiotics']->id,
                'manufacturer_id' => $manufMap['Dawa Life Sciences']->id,
                'unit_id' => $unitMap['box-100']->id,
                'pack_size' => 100,
                'purchase_price' => 6.00,
                'selling_price' => 10.00,
                'wholesale_price' => 8.50,
                'reorder_level' => 50,
                'max_stock_level' => 600,
                'is_prescription_required' => true,
                'is_controlled' => false,
                'tax_rate' => 0.00,
            ],
            [
                'name' => 'Metformin 500mg Tablets',
                'generic_name' => 'Metformin Hydrochloride',
                'brand_name' => 'Glucophage',
                'sku' => 'MET-500-100',
                'barcode' => '616110001005',
                'category_id' => $categoryMap['Antidiabetics']->id,
                'manufacturer_id' => $manufMap['Cosmos Limited']->id,
                'unit_id' => $unitMap['box-100']->id,
                'pack_size' => 100,
                'purchase_price' => 4.50,
                'selling_price' => 8.00,
                'wholesale_price' => 6.50,
                'reorder_level' => 40,
                'max_stock_level' => 400,
                'is_prescription_required' => true,
                'is_controlled' => false,
                'tax_rate' => 0.00,
            ],
            [
                'name' => 'Benylin 4Flu Liquid 100ml',
                'generic_name' => 'Diphenhydramine + Paracetamol + Pseudoephedrine',
                'brand_name' => 'Benylin',
                'sku' => 'BEN-4FL-100',
                'barcode' => '616110001006',
                'category_id' => $categoryMap['Cough, Cold & Allergy']->id,
                'manufacturer_id' => $manufMap['GlaxoSmithKline (GSK)']->id,
                'unit_id' => $unitMap['btl-100ml']->id,
                'pack_size' => 1,
                'purchase_price' => 280.00,
                'selling_price' => 420.00,
                'wholesale_price' => 360.00,
                'reorder_level' => 10,
                'max_stock_level' => 100,
                'is_prescription_required' => false,
                'is_controlled' => false,
                'tax_rate' => 0.00,
            ],
            [
                'name' => 'Morphine Sulfate 10mg Tablets',
                'generic_name' => 'Morphine Sulfate',
                'brand_name' => 'MST Continus',
                'sku' => 'MOR-010-50',
                'barcode' => '616110001007',
                'category_id' => $categoryMap['Controlled & Scheduled Drugs']->id,
                'manufacturer_id' => $manufMap['GlaxoSmithKline (GSK)']->id,
                'unit_id' => $unitMap['tab']->id,
                'pack_size' => 50,
                'purchase_price' => 80.00,
                'selling_price' => 140.00,
                'wholesale_price' => 110.00,
                'reorder_level' => 5,
                'max_stock_level' => 50,
                'is_prescription_required' => true,
                'is_controlled' => true, // Controlled substance
                'tax_rate' => 0.00,
            ],
        ];

        $productMap = [];
        foreach ($products as $p) {
            $productMap[$p['sku']] = Product::firstOrCreate(
                ['pharmacy_id' => $pharmacy->id, 'sku' => $p['sku']],
                $p
            );
        }

        // 6. Batches and Stock Movements
        $batches = [
            // Panadol: Batch 1 (Earlier Expiry - May 2027)
            [
                'product' => $productMap['PAN-EXT-100'],
                'supplier' => $supplierMap['Harleys Limited'],
                'batch_number' => 'PAN-2024-B1',
                'mfg_date' => '2024-05-01',
                'expiry_date' => '2027-05-31',
                'qty' => 150,
                'cost' => 8.50,
                'sell' => 15.00,
                'status' => 'active',
            ],
            // Panadol: Batch 2 (Later Expiry - Dec 2027)
            [
                'product' => $productMap['PAN-EXT-100'],
                'supplier' => $supplierMap['Harleys Limited'],
                'batch_number' => 'PAN-2024-B2',
                'mfg_date' => '2024-11-01',
                'expiry_date' => '2027-12-31',
                'qty' => 200,
                'cost' => 9.00,
                'sell' => 15.00,
                'status' => 'active',
            ],
            // Augmentin: Warning stage (Expiring in ~50 days)
            [
                'product' => $productMap['AUG-625-14'],
                'supplier' => $supplierMap['Laborex Kenya Ltd'],
                'batch_number' => 'AUG-WARN-01',
                'mfg_date' => '2024-01-10',
                'expiry_date' => now()->addDays(50)->toDateString(),
                'qty' => 40,
                'cost' => 75.00,
                'sell' => 120.00,
                'status' => 'active',
            ],
            // Coartem: Critical stage (Expiring in ~18 days)
            [
                'product' => $productMap['COA-20120-24'],
                'supplier' => $supplierMap['Transchem Pharmaceuticals'],
                'batch_number' => 'COA-CRIT-01',
                'mfg_date' => '2023-11-15',
                'expiry_date' => now()->addDays(18)->toDateString(),
                'qty' => 25,
                'cost' => 350.00,
                'sell' => 550.00,
                'status' => 'active',
            ],
            // Amoxil: Expired Batch (Expired 20 days ago) - Must be quarantined/blocked
            [
                'product' => $productMap['AMX-500-100'],
                'supplier' => $supplierMap['Harleys Limited'],
                'batch_number' => 'AMX-EXP-01',
                'mfg_date' => '2023-01-01',
                'expiry_date' => now()->subDays(20)->toDateString(),
                'qty' => 30,
                'cost' => 6.00,
                'sell' => 10.00,
                'status' => 'expired',
            ],
            // Metformin: Fresh Safe Batch
            [
                'product' => $productMap['MET-500-100'],
                'supplier' => $supplierMap['Laborex Kenya Ltd'],
                'batch_number' => 'MET-SAFE-01',
                'mfg_date' => '2024-06-01',
                'expiry_date' => now()->addYears(2)->toDateString(),
                'qty' => 120,
                'cost' => 4.50,
                'sell' => 8.00,
                'status' => 'active',
            ],
            // Benylin Syrup: Safe Batch
            [
                'product' => $productMap['BEN-4FL-100'],
                'supplier' => $supplierMap['Harleys Limited'],
                'batch_number' => 'BEN-2024-01',
                'mfg_date' => '2024-08-01',
                'expiry_date' => now()->addMonths(18)->toDateString(),
                'qty' => 35,
                'cost' => 280.00,
                'sell' => 420.00,
                'status' => 'active',
            ],
            // Morphine Controlled: Safe Batch
            [
                'product' => $productMap['MOR-010-50'],
                'supplier' => $supplierMap['Harleys Limited'],
                'batch_number' => 'MOR-CTRL-01',
                'mfg_date' => '2024-03-01',
                'expiry_date' => now()->addYears(3)->toDateString(),
                'qty' => 20,
                'cost' => 80.00,
                'sell' => 140.00,
                'status' => 'active',
            ],
        ];

        foreach ($batches as $b) {
            $batch = ProductBatch::firstOrCreate(
                [
                    'branch_id' => $branch->id,
                    'product_id' => $b['product']->id,
                    'batch_number' => $b['batch_number'],
                ],
                [
                    'supplier_id' => $b['supplier']->id,
                    'manufacturing_date' => $b['mfg_date'],
                    'expiry_date' => $b['expiry_date'],
                    'quantity_received' => $b['qty'],
                    'quantity_remaining' => $b['qty'],
                    'purchase_price_per_unit' => $b['cost'],
                    'selling_price_per_unit' => $b['sell'],
                    'status' => $b['status'],
                ]
            );

            // Create initial stock movement
            StockMovement::firstOrCreate(
                [
                    'branch_id' => $branch->id,
                    'product_id' => $b['product']->id,
                    'product_batch_id' => $batch->id,
                    'movement_type' => 'purchase_receipt',
                ],
                [
                    'user_id' => $adminUser->id,
                    'quantity_delta' => $b['qty'],
                    'balance_after' => $b['qty'],
                    'unit_cost' => $b['cost'],
                    'total_cost' => round($b['qty'] * $b['cost'], 4),
                    'reason' => 'Initial Stock Intake via Seeder',
                    'created_at' => now(),
                ]
            );
        }
    }
}
