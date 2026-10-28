<?php

namespace Tests\Feature;

use App\Models\Stok;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

abstract class PosTestCase extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    protected function actingAsRole(string $role = 'MANAGER'): User
    {
        $user = User::factory()->create([
            'role' => $role,
            'is_active' => true,
        ]);

        $user->syncRoleFromColumn();

        Sanctum::actingAs($user);

        return $user;
    }

    protected function makeStok(
        string $nama = 'Semen',
        int $harga = 65000,
        int $kuantitas = 10,
        ?string $sku = null,
    ): Stok {
        return Stok::create([
            'kode_sku' => $sku ?? strtoupper(str_replace(' ', '-', $nama)),
            'nama_produk' => $nama,
            'harga' => $harga,
            'kuantitas' => $kuantitas,
        ]);
    }

    protected function hashedPassword(): string
    {
        return Hash::make('secret123');
    }
}
