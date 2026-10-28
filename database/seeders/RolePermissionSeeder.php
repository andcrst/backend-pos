<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Role Spatie dipetakan dari kolom ENUM `users.role`.
 */
class RolePermissionSeeder extends Seeder
{
    /**
     * Permission per modul.
     *
     * @var array<string, list<string>>
     */
    private array $permissions = [
        'user' => ['view', 'create', 'update', 'delete'],
        'stok' => ['view', 'create', 'update', 'delete'],
        'transaksi' => ['view', 'create', 'void'],
        'report' => ['view'],
    ];

    /**
     * Role bawaan beserta permission-nya.
     *
     * @var array<string, list<string>|string>
     */
    private array $roles = [
        'manager' => '*',
        'cashier' => [
            'stok.view',
            'transaksi.view', 'transaksi.create',
        ],
        'warehouse' => [
            'stok.view', 'stok.create', 'stok.update', 'stok.delete',
            'report.view',
        ],
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $allPermissions = $this->createPermissions();

        // Registrar menyimpan cache daftar permission; flush agar hasil
        // findOrCreate di atas langsung terlihat oleh syncPermissions().
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach ($this->roles as $roleName => $permissions) {
            $role = Role::findOrCreate($roleName, 'web');

            $role->syncPermissions(
                $permissions === '*' ? $allPermissions : $permissions,
            );
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * @return list<string>
     */
    private function createPermissions(): array
    {
        $names = [];

        foreach ($this->permissions as $module => $actions) {
            foreach ($actions as $action) {
                $names[] = sprintf('%s.%s', $module, $action);
            }
        }

        $existing = Permission::query()
            ->whereIn('name', $names)
            ->where('guard_name', 'web')
            ->pluck('name')
            ->all();

        $missing = array_values(array_diff($names, $existing));

        if ($missing !== []) {
            $now = now();

            Permission::insert(array_map(fn (string $name): array => [
                'name' => $name,
                'guard_name' => 'web',
                'created_at' => $now,
                'updated_at' => $now,
            ], $missing));
        }

        return $names;
    }
}
