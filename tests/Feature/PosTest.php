<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Pharmacy;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\Sale;
use App\Models\User;
use Database\Seeders\InventorySeeder;
use Database\Seeders\PharmacySeeder;
use Database\Seeders\PosAndDispensingSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SystemSettingSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PosTest extends TestCase
{
    use RefreshDatabase;

    protected User $cashier;
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
        $this->cashier = User::where('email', 'cashier@afyameds.co.ke')->first();
    }

    public function test_cashier_can_search_products_with_stock_info(): void
    {
        $response = $this->actingAs($this->cashier, 'sanctum')
            ->getJson('/api/v1/pos/products?q=Panadol');

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success');

        $this->assertNotEmpty($response->json('data'));
        $this->assertNotNull($response->json('data.0.branch_stock'));
    }

    public function test_complete_pos_sale_deducts_batches_via_fefo_and_records_cogs(): void
    {
        $panadol = Product::where('name', 'like', '%Panadol%')->first();
        $batches = ProductBatch::where('product_id', $panadol->id)->orderBy('expiry_date', 'asc')->get();
        $earliestBatch = $batches->first();
        $initialBatchQty = (float) $earliestBatch->quantity_remaining;

        $salePayload = [
            'customer_id' => null, // Walk-in
            'items' => [
                [
                    'product_id' => $panadol->id,
                    'quantity' => 10,
                    'unit_selling_price' => 15.00,
                ],
            ],
            'payments' => [
                [
                    'payment_method' => 'cash',
                    'amount' => 150.00,
                    'amount_tendered' => 200.00,
                ],
            ],
        ];

        $response = $this->actingAs($this->cashier, 'sanctum')
            ->postJson('/api/v1/pos/checkout', $salePayload);

        $response->assertStatus(201)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.total_amount', '150.00')
            ->assertJsonPath('data.payment_status', 'paid');

        // Verify earliest batch was deducted
        $earliestBatch->refresh();
        $this->assertEquals($initialBatchQty - 10, (float) $earliestBatch->quantity_remaining);

        // Verify sale record exists with receipt number
        $this->assertDatabaseHas('sales', [
            'pharmacy_id' => $this->pharmacy->id,
            'total_amount' => 150.00,
        ]);

        // Verify stock movement ledger
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $panadol->id,
            'product_batch_id' => $earliestBatch->id,
            'movement_type' => 'pos_sale',
            'quantity_delta' => -10,
        ]);
    }

    public function test_pos_sale_supports_split_payments(): void
    {
        $panadol = Product::where('name', 'like', '%Panadol%')->first();

        $salePayload = [
            'items' => [
                [
                    'product_id' => $panadol->id,
                    'quantity' => 20,
                    'unit_selling_price' => 15.00,
                ],
            ],
            'payments' => [
                [
                    'payment_method' => 'cash',
                    'amount' => 100.00,
                    'amount_tendered' => 100.00,
                ],
                [
                    'payment_method' => 'mpesa',
                    'amount' => 200.00,
                    'reference_number' => 'QHJ994411',
                ],
            ],
        ];

        $response = $this->actingAs($this->cashier, 'sanctum')
            ->postJson('/api/v1/pos/checkout', $salePayload);

        $response->assertStatus(201)
            ->assertJsonPath('data.total_amount', '300.00')
            ->assertJsonPath('data.paid_amount', '300.00');

        $saleId = $response->json('data.id');

        $this->assertDatabaseHas('payments', [
            'payable_id' => $saleId,
            'payment_method' => 'cash',
            'amount' => 100.00,
        ]);

        $this->assertDatabaseHas('payments', [
            'payable_id' => $saleId,
            'payment_method' => 'mpesa',
            'amount' => 200.00,
            'reference_number' => 'QHJ994411',
        ]);
    }

    public function test_credit_sale_validates_customer_credit_limit(): void
    {
        $creditCustomer = Customer::where('customer_category', 'credit')->first();
        $panadol = Product::where('name', 'like', '%Panadol%')->first();

        // 1. Sale within credit limit
        $payloadValid = [
            'customer_id' => $creditCustomer->id,
            'items' => [
                [
                    'product_id' => $panadol->id,
                    'quantity' => 10,
                    'unit_selling_price' => 15.00,
                ],
            ],
            'payments' => [
                [
                    'payment_method' => 'credit',
                    'amount' => 150.00,
                ],
            ],
        ];

        $resValid = $this->actingAs($this->cashier, 'sanctum')
            ->postJson('/api/v1/pos/checkout', $payloadValid);

        $resValid->assertStatus(201)
            ->assertJsonPath('data.payment_status', 'credit');

        $creditCustomer->refresh();
        $this->assertEquals(150.00, (float) $creditCustomer->outstanding_balance);

        // 2. Sale exceeding remaining credit limit
        $payloadExcessive = [
            'customer_id' => $creditCustomer->id,
            'items' => [
                [
                    'product_id' => $panadol->id,
                    'quantity' => 10,
                    'unit_selling_price' => 30000.00,
                ],
            ],
            'payments' => [
                [
                    'payment_method' => 'credit',
                    'amount' => 30000.00,
                ],
            ],
        ];

        $resExcessive = $this->actingAs($this->cashier, 'sanctum')
            ->postJson('/api/v1/pos/checkout', $payloadExcessive);

        $resExcessive->assertStatus(422)
            ->assertJsonValidationErrors(['credit_amount']);
    }

    public function test_sale_return_restores_resellable_inventory(): void
    {
        $panadol = Product::where('name', 'like', '%Panadol%')->first();

        // Perform sale first
        $salePayload = [
            'items' => [
                [
                    'product_id' => $panadol->id,
                    'quantity' => 10,
                    'unit_selling_price' => 15.00,
                ],
            ],
            'payments' => [
                [
                    'payment_method' => 'cash',
                    'amount' => 150.00,
                ],
            ],
        ];

        $saleRes = $this->actingAs($this->cashier, 'sanctum')
            ->postJson('/api/v1/pos/checkout', $salePayload);
        $saleRes->assertStatus(201);

        $sale = Sale::with('items')->find($saleRes->json('data.id'));
        $saleItem = $sale->items->first();
        $batch = ProductBatch::find($saleItem->product_batch_id);
        $qtyBeforeReturn = (float) $batch->quantity_remaining;

        // Process Return of 5 items
        $returnPayload = [
            'reason' => 'Customer bought wrong pack size',
            'refund_method' => 'cash',
            'items' => [
                [
                    'sale_item_id' => $saleItem->id,
                    'quantity' => 5,
                    'condition' => 'resellable',
                ],
            ],
        ];

        $returnRes = $this->actingAs($this->cashier, 'sanctum')
            ->postJson("/api/v1/pos/return/{$sale->id}", $returnPayload);

        $returnRes->assertStatus(200)
            ->assertJsonPath('status', 'success');

        $batch->refresh();
        $this->assertEquals($qtyBeforeReturn + 5, (float) $batch->quantity_remaining);

        $this->assertDatabaseHas('sale_returns', [
            'sale_id' => $sale->id,
            'total_refund_amount' => 75.00,
        ]);
    }
}
