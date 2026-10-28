<?php

namespace App\Enums;

enum JenisPembayaran: string
{
    case Tunai = 'TUNAI';
    case Qris = 'QRIS';
    case Transfer = 'TRANSFER';

    public function label(): string
    {
        return match ($this) {
            self::Tunai => 'Tunai',
            self::Qris => 'QRIS',
            self::Transfer => 'Transfer',
        };
    }

    /**
     * Hanya tunai yang menerima kembalian.
     */
    public function hasChange(): bool
    {
        return $this === self::Tunai;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
