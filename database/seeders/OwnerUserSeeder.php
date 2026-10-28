<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Akun awal untuk tiap nilai ENUM role.
 */
class OwnerUserSeeder extends Seeder
{
    public function run(): void
    {
        /** @var array{name: string, email: string, password: string} $owner */
        $owner = config('pos.owner');

        $accounts = [
            ['name' => $owner['name'], 'email' => $owner['email'], 'role' => UserRole::Manager],
            ['name' => 'Kasir Toko', 'email' => 'kasir@pos.local', 'role' => UserRole::Kasir],
            ['name' => 'Petugas Gudang', 'email' => 'gudang@pos.local', 'role' => UserRole::Gudang],
        ];

        foreach ($accounts as $account) {
            /** @var User $user */
            $user = User::query()->firstOrNew(['name' => $account['name']]);

            $user->fill([
                'email' => $account['email'],
                'password' => $owner['password'],
                'role' => $account['role']->value,
                'is_active' => true,
            ]);

            $user->save();
            $user->syncRoleFromColumn();
        }
    }
}
