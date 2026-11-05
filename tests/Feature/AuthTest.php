<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Pharmacy;
use App\Models\User;
use Database\Seeders\PharmacySeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([
            RolesAndPermissionsSeeder::class,
            PharmacySeeder::class,
            UserSeeder::class,
        ]);
    }

    public function test_user_can_login_with_valid_credentials(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'owner@afyameds.co.ke',
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'token',
                'token_type',
                'user' => [
                    'id',
                    'name',
                    'email',
                    'roles',
                    'permissions',
                    'pharmacy',
                    'branch',
                    'accessible_branches',
                ],
            ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'user_login',
        ]);
    }

    public function test_user_cannot_login_with_invalid_password(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'owner@afyameds.co.ke',
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_deactivated_user_cannot_login(): void
    {
        $user = User::where('email', 'cashier@afyameds.co.ke')->first();
        $user->update(['is_active' => false]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'cashier@afyameds.co.ke',
            'password' => 'password123',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_authenticated_user_can_fetch_profile(): void
    {
        $user = User::where('email', 'pharmacist@afyameds.co.ke')->first();

        $response = $this->actingAs($user)
            ->getJson('/api/v1/auth/me');

        $response->assertStatus(200)
            ->assertJsonPath('user.email', 'pharmacist@afyameds.co.ke')
            ->assertJsonPath('user.license_number', 'PPB/REG/2018/8842');
    }

    public function test_authorized_user_can_switch_branches(): void
    {
        $owner = User::where('email', 'owner@afyameds.co.ke')->first();
        $westlands = Branch::where('code', 'WST-02')->first();

        $response = $this->actingAs($owner)
            ->postJson('/api/v1/auth/switch-branch', [
                'branch_id' => $westlands->id,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('user.branch.id', $westlands->id);

        $this->assertEquals($westlands->id, $owner->fresh()->branch_id);
    }

    public function test_user_can_logout(): void
    {
        $user = User::where('email', 'cashier@afyameds.co.ke')->first();
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/auth/logout');

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Logged out successfully');

        $this->assertCount(0, $user->tokens);
    }
}
