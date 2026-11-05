<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\Pharmacy;
use App\Models\Prescription;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\ProductCategory;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\PharmacySeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SystemSettingSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Pharmacy $pharmacy;
    protected Branch $branch;
    protected ProductCategory $category;
    protected Unit $unit;
    protected Supplier $supplier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            RolesAndPermissionsSeeder::class,
            PharmacySeeder::class,
            UserSeeder::class,
            SystemSettingSeeder::class,
        ]);

        $this->user = User::where('email', 'owner@afyameds.co.ke')->firstOrFail();
        $this->pharmacy = Pharmacy::firstOrFail();
        $this->branch = Branch::firstOrFail();

        $this->category = ProductCategory::create([
            'pharmacy_id' => $this->pharmacy->id,
            'name' => 'Analgesics & Pain',
            'code' => 'AP',
        ]);

        $this->unit = Unit::create([
            'pharmacy_id' => $this->pharmacy->id,
            'name' => 'Tablet',
            'short_name' => 'tab',
            'operator' => '*',
            'operation_value' => 1,
        ]);

        $this->supplier = Supplier::create([
            'pharmacy_id' => $this->pharmacy->id,
            'name' => 'MedSource Pharma',
            'email' => 'orders@medsource.co.ke',
            'phone' => '+254711999888',
        ]);
    }

    protected function seedSampleSalesAndInventory(): void
    {
        $prod1 = Product::create([
            'pharmacy_id' => $this->pharmacy->id,
            'category_id' => $this->category->id,
            'unit_id' => $this->unit->id,
            'name' => 'Paracetamol 500mg',
            'generic_name' => 'Paracetamol',
            'sku' => 'PARA-500',
            'purchase_price' => 2.00,
            'selling_price' => 5.00,
            'reorder_level' => 10,
        ]);

        $prod2 = Product::create([
            'pharmacy_id' => $this->pharmacy->id,
            'category_id' => $this->category->id,
            'unit_id' => $this->unit->id,
            'name' => 'Amoxicillin 250mg',
            'generic_name' => 'Amoxicillin',
            'sku' => 'AMOX-250',
            'purchase_price' => 10.00,
            'selling_price' => 20.00,
            'reorder_level' => 5,
        ]);

        // Batches
        // 1. Safe batch
        $batch1 = ProductBatch::create([
            'branch_id' => $this->branch->id,
            'product_id' => $prod1->id,
            'supplier_id' => $this->supplier->id,
            'batch_number' => 'BCH-SAFE-01',
            'quantity_received' => 100,
            'quantity_remaining' => 80,
            'purchase_price_per_unit' => 2.00,
            'selling_price_per_unit' => 5.00,
            'expiry_date' => Carbon::today()->addDays(180)->toDateString(),
            'status' => 'active',
        ]);

        // 2. Critical expiring batch (20 days left)
        ProductBatch::create([
            'branch_id' => $this->branch->id,
            'product_id' => $prod2->id,
            'supplier_id' => $this->supplier->id,
            'batch_number' => 'BCH-CRIT-02',
            'quantity_received' => 50,
            'quantity_remaining' => 30,
            'purchase_price_per_unit' => 10.00,
            'selling_price_per_unit' => 20.00,
            'expiry_date' => Carbon::today()->addDays(20)->toDateString(),
            'status' => 'active',
        ]);

        // 3. Expired batch (-5 days)
        ProductBatch::create([
            'branch_id' => $this->branch->id,
            'product_id' => $prod1->id,
            'supplier_id' => $this->supplier->id,
            'batch_number' => 'BCH-EXP-03',
            'quantity_received' => 20,
            'quantity_remaining' => 10,
            'purchase_price_per_unit' => 2.00,
            'selling_price_per_unit' => 5.00,
            'expiry_date' => Carbon::today()->subDays(5)->toDateString(),
            'status' => 'expired',
        ]);

        // Sample Sale
        $sale = Sale::create([
            'pharmacy_id' => $this->pharmacy->id,
            'branch_id' => $this->branch->id,
            'user_id' => $this->user->id,
            'invoice_number' => 'INV-20261105-001',
            'receipt_number' => 'RCP-20261105-001',
            'subtotal' => 100.00,
            'discount_amount' => 0.00,
            'tax_amount' => 0.00,
            'total_amount' => 100.00,
            'paid_amount' => 100.00,
            'balance_due' => 0.00,
            'cost_of_goods_sold' => 40.00,
            'gross_profit' => 60.00,
            'payment_status' => 'paid',
            'sale_date' => Carbon::now(),
        ]);

        SaleItem::create([
            'sale_id' => $sale->id,
            'product_id' => $prod1->id,
            'product_batch_id' => $batch1->id,
            'batch_number' => $batch1->batch_number,
            'expiry_date' => $batch1->expiry_date,
            'quantity' => 20,
            'unit_purchase_price' => 2.00,
            'unit_selling_price' => 5.00,
            'discount_amount' => 0.00,
            'tax_amount' => 0.00,
            'subtotal' => 100.00,
            'total_cogs' => 40.00,
            'gross_profit' => 60.00,
        ]);

        Payment::create([
            'pharmacy_id' => $this->pharmacy->id,
            'branch_id' => $this->branch->id,
            'payable_type' => Sale::class,
            'payable_id' => $sale->id,
            'payment_method' => 'mpesa',
            'amount' => 100.00,
            'amount_tendered' => 100.00,
            'change_returned' => 0.00,
            'received_by' => $this->user->id,
            'payment_date' => Carbon::now(),
        ]);
    }

    public function test_dashboard_summary_returns_accurate_kpis(): void
    {
        $this->seedSampleSalesAndInventory();

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/reports/dashboard-summary');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'today' => ['revenue', 'transactions'],
                    'month_to_date' => ['revenue', 'cogs', 'gross_profit', 'gross_margin', 'transactions', 'average_basket_size'],
                    'inventory' => ['valuation_at_cost', 'valuation_at_retail', 'potential_gross_profit', 'critical_expiry_risk'],
                    'accounts_payable',
                ]
            ]);

        $data = $response->json('data');
        $this->assertEquals(100.00, $data['today']['revenue']);
        $this->assertEquals(1, $data['today']['transactions']);
        $this->assertEquals(60.00, $data['month_to_date']['gross_profit']); // 100 - 40
        $this->assertGreaterThan(0, $data['inventory']['valuation_at_cost']);
    }

    public function test_sales_analytics_with_date_range_filters_and_breakdowns(): void
    {
        $this->seedSampleSalesAndInventory();

        $today = Carbon::today()->toDateString();
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/reports/sales-analytics?start_date={$today}&end_date={$today}");

        $response->assertOk()
            ->assertJsonPath('success', true);

        $this->assertEquals(100.00, $response->json('data.summary.total_revenue'));
        $this->assertEquals(40.00, $response->json('data.summary.total_cogs'));
        $this->assertEquals(60.00, $response->json('data.summary.gross_profit'));
        $this->assertEquals(100.00, $response->json('data.payment_methods.mpesa'));
    }

    public function test_top_selling_products_endpoint(): void
    {
        $this->seedSampleSalesAndInventory();

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/reports/top-selling-products?limit=5');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    [
                        'product_id',
                        'name',
                        'sku',
                        'category',
                        'units_sold',
                        'revenue',
                        'gross_profit',
                        'margin_percent',
                    ]
                ]
            ]);

        $this->assertEquals('Paracetamol 500mg', $response->json('data.0.name'));
        $this->assertEquals(20, $response->json('data.0.units_sold'));
    }

    public function test_inventory_valuation_report(): void
    {
        $this->seedSampleSalesAndInventory();

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/reports/inventory-valuation');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'summary' => [
                        'total_valuation_cost',
                        'total_valuation_retail',
                        'potential_gross_profit',
                        'overall_markup_percent',
                        'total_batches_count',
                    ],
                    'by_category',
                    'items',
                ]
            ]);

        $summary = $response->json('data.summary');
        $this->assertGreaterThan(0, $summary['total_valuation_cost']);
        $this->assertGreaterThan(0, $summary['total_valuation_retail']);
    }

    public function test_expiry_risk_analysis_buckets(): void
    {
        $this->seedSampleSalesAndInventory();

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/reports/expiry-risk-analysis');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'total_value_at_risk',
                    'buckets' => [
                        'expired',
                        'critical_30',
                        'warning_60',
                        'caution_90',
                        'safe',
                    ]
                ]
            ]);

        $buckets = $response->json('data.buckets');
        // We created 1 expired batch (10 units * 2 KES = 20 KES)
        $this->assertEquals(1, $buckets['expired']['count']);
        $this->assertEquals(20.00, $buckets['expired']['cost_value']);

        // We created 1 critical 30d batch (30 units * 10 KES = 300 KES)
        $this->assertEquals(1, $buckets['critical_30']['count']);
        $this->assertEquals(300.00, $buckets['critical_30']['cost_value']);
    }

    public function test_dead_stock_detection(): void
    {
        $this->seedSampleSalesAndInventory();

        // Amoxicillin has 0 sales, so its batch should show up as dead stock
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/reports/dead-stock?days=30');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'days_inactive',
                    'total_items_count',
                    'total_tied_up_capital',
                    'items',
                ]
            ]);

        $items = $response->json('data.items');
        $productNames = array_column($items, 'product_name');
        $this->assertContains('Amoxicillin 250mg', $productNames);
    }

    public function test_clinical_dispensing_analytics(): void
    {
        $customer = Customer::create([
            'pharmacy_id' => $this->pharmacy->id,
            'name' => 'Wanjiku Kamau',
            'phone' => '+254700112233',
        ]);

        Prescription::create([
            'pharmacy_id' => $this->pharmacy->id,
            'branch_id' => $this->branch->id,
            'customer_id' => $customer->id,
            'patient_name' => 'Wanjiku Kamau',
            'prescription_number' => 'RX-20261105-001',
            'prescriber_name' => 'Dr. Omondi',
            'prescriber_facility' => 'Aga Khan Hospital',
            'prescription_date' => Carbon::today(),
            'status' => 'fully_dispensed',
            'created_by' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/reports/clinical-dispensing');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.summary.total_prescriptions', 1)
            ->assertJsonPath('data.summary.fully_dispensed', 1)
            ->assertJsonPath('data.summary.fulfillment_rate_percent', 100);

        $prescribers = $response->json('data.top_prescribers');
        $this->assertCount(1, $prescribers);
        $this->assertEquals('Dr. Omondi', $prescribers[0]['prescriber_name']);
    }

    public function test_export_sales_csv_returns_stream(): void
    {
        $this->seedSampleSalesAndInventory();

        $response = $this->actingAs($this->user, 'sanctum')
            ->get('/api/v1/reports/export/sales-csv');

        $response->assertOk();
        $this->assertTrue(str_contains($response->headers->get('content-type'), 'text/csv'));
    }

    public function test_export_inventory_csv_returns_stream(): void
    {
        $this->seedSampleSalesAndInventory();

        $response = $this->actingAs($this->user, 'sanctum')
            ->get('/api/v1/reports/export/inventory-csv');

        $response->assertOk();
        $this->assertTrue(str_contains($response->headers->get('content-type'), 'text/csv'));
    }

    public function test_export_expiry_csv_returns_stream(): void
    {
        $this->seedSampleSalesAndInventory();

        $response = $this->actingAs($this->user, 'sanctum')
            ->get('/api/v1/reports/export/expiry-csv');

        $response->assertOk();
        $this->assertTrue(str_contains($response->headers->get('content-type'), 'text/csv'));
    }

    public function test_unauthenticated_requests_are_rejected(): void
    {
        $this->getJson('/api/v1/reports/dashboard-summary')->assertUnauthorized();
        $this->getJson('/api/v1/reports/sales-analytics')->assertUnauthorized();
        $this->getJson('/api/v1/reports/inventory-valuation')->assertUnauthorized();
        $this->getJson('/api/v1/reports/expiry-risk-analysis')->assertUnauthorized();
        $this->getJson('/api/v1/reports/dead-stock')->assertUnauthorized();
        $this->getJson('/api/v1/reports/clinical-dispensing')->assertUnauthorized();
    }
}
