<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\InventoryService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class InventoryTest extends TestCase
{
    use RefreshDatabase;

    protected InventoryService $inventoryService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->inventoryService = app(InventoryService::class);
    }

    public function test_receiving_stock_increases_inventory_and_records_movement(): void
    {
        $branch = Branch::where('is_main', true)->first();
        $product = Product::where('sku', 'PAN-EXT-100')->first();
        $initialStock = $product->batches()->where('branch_id', $branch->id)->sum('quantity_remaining');

        $batch = $this->inventoryService->receiveBatch(
            branchId: $branch->id,
            productId: $product->id,
            batchNumber: 'TEST-REC-100',
            quantity: 100.0,
            purchasePricePerUnit: 10.00,
            sellingPricePerUnit: 18.00,
            manufacturingDate: now()->subMonths(1)->toDateString(),
            expiryDate: now()->addYears(2)->toDateString(),
            reason: 'Test Stock Delivery'
        );

        $this->assertEquals(100.0, $batch->quantity_remaining);
        $this->assertEquals(10.00, $batch->purchase_price_per_unit);

        // Verify total inventory increased by 100
        $newStock = $product->batches()->where('branch_id', $branch->id)->sum('quantity_remaining');
        $this->assertEquals($initialStock + 100.0, $newStock);

        // Verify stock movement ledger entry
        $this->assertDatabaseHas('stock_movements', [
            'product_batch_id' => $batch->id,
            'movement_type' => 'purchase_receipt',
            'quantity_delta' => 100.0,
            'unit_cost' => 10.00,
        ]);
    }

    public function test_fefo_allocation_selects_earliest_expiring_batch_first(): void
    {
        $branch = Branch::where('is_main', true)->first();
        $product = Product::where('sku', 'PAN-EXT-100')->first();

        // Clear existing batches for clean FEFO test
        ProductBatch::where('product_id', $product->id)->delete();

        // Batch A: Expiring in 6 months (Earlier)
        $batchA = $this->inventoryService->receiveBatch(
            branchId: $branch->id,
            productId: $product->id,
            batchNumber: 'BATCH-EARLIER',
            quantity: 20.0,
            purchasePricePerUnit: 8.00,
            sellingPricePerUnit: 15.00,
            manufacturingDate: now()->subMonths(2)->toDateString(),
            expiryDate: now()->addMonths(6)->toDateString()
        );

        // Batch B: Expiring in 18 months (Later)
        $batchB = $this->inventoryService->receiveBatch(
            branchId: $branch->id,
            productId: $product->id,
            batchNumber: 'BATCH-LATER',
            quantity: 50.0,
            purchasePricePerUnit: 9.00,
            sellingPricePerUnit: 15.00,
            manufacturingDate: now()->subMonths(1)->toDateString(),
            expiryDate: now()->addMonths(18)->toDateString()
        );

        // Request 25 units: should take all 20 from Batch A, and 5 from Batch B
        $allocations = $this->inventoryService->allocateFefoStock($branch->id, $product->id, 25.0);

        $this->assertCount(2, $allocations);
        $this->assertEquals($batchA->id, $allocations[0]['batch_id']);
        $this->assertEquals(20.0, $allocations[0]['quantity']);
        $this->assertEquals(8.00, $allocations[0]['unit_cost']);

        $this->assertEquals($batchB->id, $allocations[1]['batch_id']);
        $this->assertEquals(5.0, $allocations[1]['quantity']);
        $this->assertEquals(9.00, $allocations[1]['unit_cost']);

        // Total COGS = (20 * 8.00) + (5 * 9.00) = 160 + 45 = 205.00
        $totalCogs = $allocations[0]['cogs_total'] + $allocations[1]['cogs_total'];
        $this->assertEquals(205.00, $totalCogs);
    }

    public function test_system_rejects_sale_or_allocation_of_expired_stock(): void
    {
        $branch = Branch::where('is_main', true)->first();
        $product = Product::where('sku', 'AMX-500-100')->first();

        // Clear existing batches
        ProductBatch::where('product_id', $product->id)->delete();

        // Create an expired batch
        ProductBatch::create([
            'branch_id' => $branch->id,
            'product_id' => $product->id,
            'batch_number' => 'EXPIRED-BATCH',
            'expiry_date' => now()->subDays(10)->toDateString(),
            'quantity_received' => 50.0,
            'quantity_remaining' => 50.0,
            'purchase_price_per_unit' => 6.00,
            'selling_price_per_unit' => 10.00,
            'status' => 'expired',
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Insufficient unexpired stock");

        // Attempting to allocate from expired stock must fail
        $this->inventoryService->allocateFefoStock($branch->id, $product->id, 5.0);
    }

    public function test_stock_adjustment_updates_batch_and_creates_audit_trail(): void
    {
        $branch = Branch::where('is_main', true)->first();
        $owner = User::where('email', 'owner@afyameds.co.ke')->first();
        $batch = ProductBatch::where('branch_id', $branch->id)
            ->where('quantity_remaining', '>', 10)
            ->first();

        $originalQty = $batch->quantity_remaining;
        $newQty = $originalQty - 5.0; // 5 units damaged

        $adjustment = $this->inventoryService->adjustStock(
            branchId: $branch->id,
            userId: $owner->id,
            reasonType: 'damaged',
            items: [
                [
                    'product_batch_id' => $batch->id,
                    'new_quantity' => $newQty,
                    'notes' => 'Bottle crushed in transit',
                ],
            ],
            notes: 'Monthly physical stock inspection'
        );

        $this->assertEquals('approved', $adjustment->status);
        $this->assertEquals($newQty, $batch->fresh()->quantity_remaining);

        // Verify movement ledger
        $this->assertDatabaseHas('stock_movements', [
            'product_batch_id' => $batch->id,
            'movement_type' => 'damaged_scrap',
            'quantity_delta' => -5.0,
        ]);

        // Verify audit log
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'stock_adjusted',
        ]);
    }

    public function test_expiry_risk_categorization_and_value_at_risk(): void
    {
        $branch = Branch::where('is_main', true)->first();
        $report = $this->inventoryService->getExpiryRiskReport($branch->id);

        $this->assertArrayHasKey('summary', $report);
        $this->assertArrayHasKey('batches', $report);

        $summary = $report['summary'];
        $this->assertArrayHasKey('expired', $summary);
        $this->assertArrayHasKey('critical_30', $summary);
        $this->assertArrayHasKey('warning_60', $summary);
        $this->assertArrayHasKey('alert_90', $summary);
        $this->assertArrayHasKey('safe', $summary);

        // Pre-seeded Coartem is within 18 days -> critical_30
        $this->assertGreaterThan(0, $summary['critical_30']['count']);
        $this->assertGreaterThan(0, $summary['critical_30']['value_at_risk']);

        // Pre-seeded Amoxil is expired -> expired
        $this->assertGreaterThan(0, $summary['expired']['count']);
    }

    public function test_api_can_list_products_with_branch_stock(): void
    {
        $owner = User::where('email', 'owner@afyameds.co.ke')->first();

        $response = $this->actingAs($owner)
            ->getJson('/api/v1/products');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'data' => [
                    'data' => [
                        '*' => [
                            'id',
                            'name',
                            'generic_name',
                            'sku',
                            'selling_price',
                            'category',
                            'unit',
                            'branch_stock',
                        ],
                    ],
                ],
            ]);
    }
}
