<?php

namespace App\Enums;

enum UserRole: string
{
    case Kasir = 'KASIR';
    case Gudang = 'GUDANG';
    case Manager = 'MANAGER';

    public function label(): string
    {
        return match ($this) {
            self::Kasir => 'Kasir',
            self::Gudang => 'Petugas Gudang',
            self::Manager => 'Manager',
        };
    }

    /**
     * Nama role Spatie yang setara nilai ENUM ini.
     */
    public function spatieRole(): string
    {
        return match ($this) {
            self::Kasir => 'cashier',
            self::Gudang => 'warehouse',
            self::Manager => 'manager',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
