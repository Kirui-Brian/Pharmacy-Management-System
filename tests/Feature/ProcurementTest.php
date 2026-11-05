<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\GoodsReceivedNote;
use App\Models\Pharmacy;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\SupplierInvoice;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\InventorySeeder;
use Database\Seeders\PharmacySeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SystemSettingSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProcurementTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Pharmacy $pharmacy;
    protected Branch $branch;
    protected Supplier $supplier;
    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            RolesAndPermissionsSeeder::class,
            PharmacySeeder::class,
            UserSeeder::class,
            SystemSettingSeeder::class,
            InventorySeeder::class,
        ]);

        $this->pharmacy = Pharmacy::first();
        $this->branch = Branch::where('pharmacy_id', $this->pharmacy->id)->first();
        $this->user = User::where('email', 'owner@afyameds.co.ke')->first();
        $this->supplier = Supplier::where('pharmacy_id', $this->pharmacy->id)->first();
        $this->product = Product::where('pharmacy_id', $this->pharmacy->id)->first();
    }

    public function test_user_can_create_purchase_order_with_items(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/purchase-orders', [
                'supplier_id' => $this->supplier->id,
                'branch_id' => $this->branch->id,
                'order_date' => now()->toDateString(),
                'expected_delivery_date' => now()->addDays(7)->toDateString(),
                'shipping_cost' => 250.00,
                'notes' => 'Urgent replenishment order',
                'items' => [
                    [
                        'product_id' => $this->product->id,
                        'quantity_ordered' => 50,
                        'unit_cost' => 120.00,
                        'discount_amount' => 100.00,
                        'tax_amount' => 0.00,
                    ],
                ],
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.supplier_id', $this->supplier->id);

        $poId = $response->json('data.id');
        $this->assertDatabaseHas('purchase_orders', [
            'id' => $poId,
            'status' => 'ordered',
            'shipping_cost' => 250.00,
        ]);

        $this->assertDatabaseHas('purchase_order_items', [
            'purchase_order_id' => $poId,
            'product_id' => $this->product->id,
            'quantity_ordered' => 50,
            'unit_cost' => 120.00,
        ]);
    }

    public function test_user_can_update_purchase_order_status(): void
    {
        $po = PurchaseOrder::create([
            'pharmacy_id' => $this->pharmacy->id,
            'branch_id' => $this->branch->id,
            'supplier_id' => $this->supplier->id,
            'user_id' => $this->user->id,
            'po_number' => 'PO-TEST-001',
            'order_date' => now()->toDateString(),
            'subtotal' => 1000.00,
            'total_amount' => 1000.00,
            'status' => 'ordered',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->patchJson("/api/v1/purchase-orders/{$po->id}/status", [
                'status' => 'cancelled',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'cancelled');

        $this->assertDatabaseHas('purchase_orders', [
            'id' => $po->id,
            'status' => 'cancelled',
        ]);
    }

    public function test_stock_reception_via_grn_creates_batch_stock_movement_and_supplier_invoice(): void
    {
        $batchNumber = 'TEST-BATCH-7788';
        $expiryDate = now()->addMonths(18)->toDateString();
        $initialSupplierBalance = (float) $this->supplier->outstanding_balance;

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/goods-received-notes', [
                'supplier_id' => $this->supplier->id,
                'branch_id' => $this->branch->id,
                'delivery_note_number' => 'DN-998811',
                'supplier_invoice_number' => 'INV-SUP-998811',
                'received_date' => now()->toDateString(),
                'tax_amount' => 0.00,
                'notes' => 'Received in good condition',
                'items' => [
                    [
                        'product_id' => $this->product->id,
                        'batch_number' => $batchNumber,
                        'manufacturing_date' => now()->subMonth()->toDateString(),
                        'expiry_date' => $expiryDate,
                        'quantity_received' => 80,
                        'unit_purchase_price' => 75.00,
                        'unit_selling_price' => 110.00,
                    ],
                ],
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.total_received_value', '6000.00');

        $grnId = $response->json('data.id');

        // Check Batch created
        $this->assertDatabaseHas('product_batches', [
            'branch_id' => $this->branch->id,
            'product_id' => $this->product->id,
            'batch_number' => $batchNumber,
            'quantity_remaining' => 80,
            'status' => 'active',
        ]);

        // Check Stock Movement logged
        $this->assertDatabaseHas('stock_movements', [
            'branch_id' => $this->branch->id,
            'product_id' => $this->product->id,
            'movement_type' => 'purchase_receipt',
            'quantity_delta' => 80,
            'unit_cost' => 75.00,
            'reference_type' => GoodsReceivedNote::class,
            'reference_id' => $grnId,
        ]);

        // Check Supplier Invoice created
        $this->assertDatabaseHas('supplier_invoices', [
            'pharmacy_id' => $this->pharmacy->id,
            'supplier_id' => $this->supplier->id,
            'goods_received_note_id' => $grnId,
            'invoice_number' => 'INV-SUP-998811',
            'total_amount' => 6000.00,
            'balance_due' => 6000.00,
            'payment_status' => 'unpaid',
        ]);

        // Check Supplier Accounts Payable updated
        $this->supplier->refresh();
        $this->assertEquals($initialSupplierBalance + 6000.00, (float) $this->supplier->outstanding_balance);
    }

    public function test_cannot_receive_expired_stock_batch(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/goods-received-notes', [
                'supplier_id' => $this->supplier->id,
                'branch_id' => $this->branch->id,
                'items' => [
                    [
                        'product_id' => $this->product->id,
                        'batch_number' => 'EXPIRED-BATCH-001',
                        'expiry_date' => now()->subDay()->toDateString(), // Already expired!
                        'quantity_received' => 20,
                        'unit_purchase_price' => 50.00,
                        'unit_selling_price' => 80.00,
                    ],
                ],
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['items']);
    }

    public function test_partial_and_full_grn_reception_updates_po_status(): void
    {
        // 1. Create a PO with 2 items
        $product2 = Product::where('pharmacy_id', $this->pharmacy->id)->where('id', '!=', $this->product->id)->first();

        $po = PurchaseOrder::create([
            'pharmacy_id' => $this->pharmacy->id,
            'branch_id' => $this->branch->id,
            'supplier_id' => $this->supplier->id,
            'user_id' => $this->user->id,
            'po_number' => 'PO-PARTIAL-001',
            'order_date' => now()->toDateString(),
            'subtotal' => 5000.00,
            'total_amount' => 5000.00,
            'status' => 'ordered',
        ]);

        $poItem1 = $po->items()->create([
            'product_id' => $this->product->id,
            'quantity_ordered' => 20,
            'quantity_received' => 0,
            'unit_cost' => 100.00,
            'subtotal' => 2000.00,
        ]);

        $poItem2 = $po->items()->create([
            'product_id' => $product2->id,
            'quantity_ordered' => 30,
            'quantity_received' => 0,
            'unit_cost' => 100.00,
            'subtotal' => 3000.00,
        ]);

        // 2. Receive first item partially
        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/goods-received-notes', [
                'supplier_id' => $this->supplier->id,
                'branch_id' => $this->branch->id,
                'purchase_order_id' => $po->id,
                'items' => [
                    [
                        'product_id' => $this->product->id,
                        'purchase_order_item_id' => $poItem1->id,
                        'batch_number' => 'PARTIAL-BATCH-1',
                        'expiry_date' => now()->addYear()->toDateString(),
                        'quantity_received' => 20,
                        'unit_purchase_price' => 100.00,
                    ],
                ],
            ])
            ->assertStatus(201);

        $po->refresh();
        $this->assertEquals('partially_received', $po->status);

        // 3. Receive remaining item fully
        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/goods-received-notes', [
                'supplier_id' => $this->supplier->id,
                'branch_id' => $this->branch->id,
                'purchase_order_id' => $po->id,
                'items' => [
                    [
                        'product_id' => $product2->id,
                        'purchase_order_item_id' => $poItem2->id,
                        'batch_number' => 'PARTIAL-BATCH-2',
                        'expiry_date' => now()->addYear()->toDateString(),
                        'quantity_received' => 30,
                        'unit_purchase_price' => 100.00,
                    ],
                ],
            ])
            ->assertStatus(201);

        $po->refresh();
        $this->assertEquals('received', $po->status);
    }

    public function test_recording_supplier_payment_updates_invoice_and_supplier_balance(): void
    {
        // Setup invoice
        $invoice = SupplierInvoice::create([
            'pharmacy_id' => $this->pharmacy->id,
            'branch_id' => $this->branch->id,
            'supplier_id' => $this->supplier->id,
            'invoice_number' => 'INV-TEST-PAY-01',
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'subtotal' => 10000.00,
            'total_amount' => 10000.00,
            'paid_amount' => 0.00,
            'balance_due' => 10000.00,
            'payment_status' => 'unpaid',
        ]);
        $this->supplier->outstanding_balance = 10000.00;
        $this->supplier->save();

        // 1. Make partial payment of 4,000
        $response1 = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/supplier-payments', [
                'supplier_id' => $this->supplier->id,
                'branch_id' => $this->branch->id,
                'supplier_invoice_id' => $invoice->id,
                'amount' => 4000.00,
                'payment_method' => 'bank_transfer',
                'reference_number' => 'EFT-4000',
            ]);

        $response1->assertStatus(201);

        $invoice->refresh();
        $this->supplier->refresh();
        $this->assertEquals(4000.00, (float) $invoice->paid_amount);
        $this->assertEquals(6000.00, (float) $invoice->balance_due);
        $this->assertEquals('partial', $invoice->payment_status);
        $this->assertEquals(6000.00, (float) $this->supplier->outstanding_balance);

        // 2. Clear remaining balance of 6,000
        $response2 = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/supplier-payments', [
                'supplier_id' => $this->supplier->id,
                'branch_id' => $this->branch->id,
                'supplier_invoice_id' => $invoice->id,
                'amount' => 6000.00,
                'payment_method' => 'mpesa',
                'reference_number' => 'MPESA-REF-6000',
            ]);

        $response2->assertStatus(201);

        $invoice->refresh();
        $this->supplier->refresh();
        $this->assertEquals(10000.00, (float) $invoice->paid_amount);
        $this->assertEquals(0.00, (float) $invoice->balance_due);
        $this->assertEquals('paid', $invoice->payment_status);
        $this->assertEquals(0.00, (float) $this->supplier->outstanding_balance);
    }

    public function test_purchase_return_deducts_batch_stock_and_supplier_balance(): void
    {
        // Setup batch with 50 units
        $batch = ProductBatch::create([
            'branch_id' => $this->branch->id,
            'product_id' => $this->product->id,
            'supplier_id' => $this->supplier->id,
            'batch_number' => 'RET-BATCH-50',
            'expiry_date' => now()->addYear()->toDateString(),
            'quantity_received' => 50,
            'quantity_remaining' => 50,
            'purchase_price_per_unit' => 100.00,
            'selling_price_per_unit' => 140.00,
            'status' => 'active',
        ]);

        $this->supplier->outstanding_balance = 5000.00;
        $this->supplier->save();

        // Return 15 units due to packaging defect
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/purchase-returns', [
                'supplier_id' => $this->supplier->id,
                'branch_id' => $this->branch->id,
                'reason' => 'Packaging defective on arrival',
                'items' => [
                    [
                        'product_batch_id' => $batch->id,
                        'quantity' => 15,
                        'unit_cost' => 100.00,
                        'reason' => 'Broken seals',
                    ],
                ],
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.total_amount', '1500.00');

        $batch->refresh();
        $this->supplier->refresh();

        // 50 - 15 = 35 units remaining
        $this->assertEquals(35, (float) $batch->quantity_remaining);
        // 5000 - 1500 = 3500 balance remaining
        $this->assertEquals(3500.00, (float) $this->supplier->outstanding_balance);

        // Check stock movement
        $this->assertDatabaseHas('stock_movements', [
            'product_batch_id' => $batch->id,
            'movement_type' => 'purchase_return',
            'quantity_delta' => -15,
        ]);
    }
}
