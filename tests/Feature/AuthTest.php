<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_login_success_returns_token_and_profile(): void
    {
        $user = User::factory()->create([
            'email' => 'kasir@pos.test',
            'password' => 'secret123',
        ]);
        $user->assignRole('cashier');

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'kasir@pos.test',
            'password' => 'secret123',
            'device_name' => 'phpunit',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'token',
                    'token_type',
                    'user' => ['id', 'name', 'email', 'roles', 'permissions'],
                ],
            ])
            ->assertJsonPath('data.user.email', 'kasir@pos.test')
            ->assertJsonPath('data.token_type', 'Bearer');

        $this->assertDatabaseCount('personal_access_tokens', 1);
        $this->assertNotNull($user->fresh()->last_login_at);
        $this->assertTrue($user->fresh()->hasRole('cashier'));
    }

    public function test_login_with_wrong_password_returns_invalid_credentials(): void
    {
        User::factory()->create([
            'email' => 'kasir@pos.test',
            'password' => 'secret123',
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'kasir@pos.test',
            'password' => 'password-salah',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error_code', 'INVALID_CREDENTIALS');

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_login_with_inactive_account_is_rejected(): void
    {
        User::factory()->create([
            'email' => 'nonaktif@pos.test',
            'password' => 'secret123',
            'is_active' => false,
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'nonaktif@pos.test',
            'password' => 'secret123',
        ]);

        $response->assertStatus(403)
            ->assertJsonPath('error_code', 'ACCOUNT_INACTIVE');

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_login_validation_error_uses_envelope(): void
    {
        $response = $this->postJson('/api/v1/auth/login', []);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error_code', 'VALIDATION_ERROR')
            ->assertJsonStructure(['errors' => ['email', 'password']]);
    }

    public function test_me_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/auth/me');

        $response->assertStatus(401)
            ->assertJsonPath('error_code', 'UNAUTHENTICATED');
    }

    public function test_me_returns_roles_and_permissions(): void
    {
        $user = User::factory()->create();
        $user->assignRole('manager');

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/auth/me');

        $response->assertOk()
            ->assertJsonPath('data.email', $user->email)
            ->assertJsonPath('data.roles.0', 'manager');

        $permissions = $response->json('data.permissions');

        $this->assertContains('stok.view', $permissions);
        $this->assertContains('transaksi.create', $permissions);
        $this->assertContains('transaksi.void', $permissions);
        // Manager memiliki seluruh permission yang tersedia.
        $this->assertContains('user.create', $permissions);
    }

    public function test_logout_revokes_current_token(): void
    {
        User::factory()->create([
            'email' => 'kasir@pos.test',
            'password' => 'secret123',
        ]);

        $login = $this->postJson('/api/v1/auth/login', [
            'email' => 'kasir@pos.test',
            'password' => 'secret123',
        ]);

        $token = $login->json('data.token');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/auth/logout')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_change_password_requires_correct_current_password(): void
    {
        $user = User::factory()->create([
            'password' => 'secret123',
        ]);

        Sanctum::actingAs($user);

        $response = $this->patchJson('/api/v1/auth/password', [
            'current_password' => 'salah',
            'password' => 'password-baru',
            'password_confirmation' => 'password-baru',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error_code', 'INVALID_CURRENT_PASSWORD');
    }

    public function test_change_password_updates_hash_and_revokes_other_tokens(): void
    {
        $user = User::factory()->create([
            'password' => 'secret123',
        ]);

        $current = $user->createToken('device-ini')->plainTextToken;
        $user->createToken('device-lain');

        $response = $this->withHeader('Authorization', 'Bearer '.$current)
            ->patchJson('/api/v1/auth/password', [
                'current_password' => 'secret123',
                'password' => 'password-baru',
                'password_confirmation' => 'password-baru',
            ]);

        $response->assertOk()->assertJsonPath('success', true);

        $this->assertTrue(Hash::check('password-baru', $user->fresh()->password));
        $this->assertSame(1, $user->tokens()->count());
        $this->assertDatabaseMissing('personal_access_tokens', ['name' => 'device-lain']);
    }

    public function test_health_endpoint_reports_ok(): void
    {
        $this->getJson('/api/v1/health')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.database', 'ok');
    }
}
