<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Pharmacy;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\ProductCategory;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\PharmacySeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SystemSettingSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class SystemMaintenanceTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
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
        ]);

        $this->user = User::where('email', 'owner@afyameds.co.ke')->firstOrFail();
        $this->pharmacy = Pharmacy::firstOrFail();
        $this->branch = Branch::firstOrFail();
    }

    public function test_health_status_endpoint_returns_diagnostics(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/system/health');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'status',
                    'database' => ['connected', 'latency_ms', 'driver', 'name'],
                    'storage' => ['app_writable', 'logs_writable'],
                    'backups' => ['count'],
                    'environment' => ['app_env', 'debug_mode', 'php_version', 'server_time', 'server_timezone'],
                ],
            ]);

        $this->assertTrue($response->json('data.database.connected'));
    }

    public function test_backups_list_endpoint(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/system/backups');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data']);
    }

    public function test_trigger_backup_endpoint_and_download(): void
    {
        $backupDir = storage_path('app/backups');
        if (!File::exists($backupDir)) {
            File::makeDirectory($backupDir, 0755, true);
        }

        // Trigger backup
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/system/backups');

        $response->assertOk()
            ->assertJsonPath('success', true);

        // Fetch backups list
        $listResponse = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/system/backups');

        $listResponse->assertOk();
        $backups = $listResponse->json('data');
        $this->assertNotEmpty($backups);

        $latestFilename = $backups[0]['filename'];

        // Download the backup
        $downloadResponse = $this->actingAs($this->user, 'sanctum')
            ->get("/api/v1/system/backups/{$latestFilename}/download");

        $downloadResponse->assertOk();
    }

    public function test_pms_backup_artisan_command_executes_successfully(): void
    {
        $exitCode = Artisan::call('pms:backup', ['--retention' => 7]);
        $this->assertEquals(0, $exitCode);
    }

    public function test_pms_check_expiries_artisan_command_executes_and_auto_expires_batches(): void
    {
        $category = ProductCategory::create([
            'pharmacy_id' => $this->pharmacy->id,
            'name' => 'General Meds',
            'code' => 'GEN',
        ]);

        $unit = Unit::create([
            'pharmacy_id' => $this->pharmacy->id,
            'name' => 'Box',
            'short_name' => 'bx',
            'operator' => '*',
            'operation_value' => 1,
        ]);

        $supplier = Supplier::create([
            'pharmacy_id' => $this->pharmacy->id,
            'name' => 'Kenya Medical Supplies',
            'phone' => '+254700000000',
            'email' => 'info@kemsa.co.ke',
        ]);

        $product = Product::create([
            'pharmacy_id' => $this->pharmacy->id,
            'category_id' => $category->id,
            'unit_id' => $unit->id,
            'name' => 'Cough Syrup 100ml',
            'generic_name' => 'Cough Formula',
            'sku' => 'CS-100',
            'purchase_price' => 50.00,
            'selling_price' => 80.00,
            'reorder_level' => 10,
            'is_active' => true,
        ]);

        // Create an expired active batch
        $batch = ProductBatch::create([
            'branch_id' => $this->branch->id,
            'product_id' => $product->id,
            'supplier_id' => $supplier->id,
            'batch_number' => 'BCH-CRON-EXP',
            'quantity_received' => 10,
            'quantity_remaining' => 10,
            'purchase_price_per_unit' => 50.00,
            'selling_price_per_unit' => 80.00,
            'expiry_date' => Carbon::yesterday()->toDateString(),
            'status' => 'active',
        ]);

        // Run check-expiries
        $exitCode = Artisan::call('pms:check-expiries');
        $this->assertEquals(0, $exitCode);

        // Batch should now be marked 'expired'
        $batch->refresh();
        $this->assertEquals('expired', $batch->status);
    }
}
