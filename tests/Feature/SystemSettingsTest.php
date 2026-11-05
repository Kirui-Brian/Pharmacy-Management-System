<?php

namespace Tests\Feature;

use App\Models\SystemSetting;
use App\Models\User;
use Database\Seeders\PharmacySeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SystemSettingSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SystemSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([
            RolesAndPermissionsSeeder::class,
            PharmacySeeder::class,
            UserSeeder::class,
            SystemSettingSeeder::class,
        ]);
    }

    public function test_user_can_fetch_system_settings(): void
    {
        $user = User::where('email', 'owner@afyameds.co.ke')->first();

        $response = $this->actingAs($user)
            ->getJson('/api/v1/settings');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'data' => [
                    'general',
                    'inventory',
                    'pos',
                ],
            ]);
    }

    public function test_owner_can_update_system_settings(): void
    {
        $owner = User::where('email', 'owner@afyameds.co.ke')->first();

        $response = $this->actingAs($owner)
            ->postJson('/api/v1/settings', [
                'settings' => [
                    [
                        'key' => 'low_stock_threshold',
                        'value' => 25,
                        'group' => 'inventory',
                    ],
                    [
                        'key' => 'company_name',
                        'value' => 'Afya Meds SuperCare Ltd',
                        'group' => 'general',
                    ],
                ],
            ]);

        $response->assertStatus(200);

        $this->assertEquals(25, SystemSetting::get('low_stock_threshold'));
        $this->assertEquals('Afya Meds SuperCare Ltd', SystemSetting::get('company_name'));
    }

    public function test_unauthorized_user_cannot_update_settings(): void
    {
        $cashier = User::where('email', 'cashier@afyameds.co.ke')->first();

        $response = $this->actingAs($cashier)
            ->postJson('/api/v1/settings', [
                'settings' => [
                    ['key' => 'low_stock_threshold', 'value' => 50],
                ],
            ]);

        $response->assertStatus(403);
    }
}
