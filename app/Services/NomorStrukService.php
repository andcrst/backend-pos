<?php

namespace App\Services;

use App\Exceptions\ApiException;
use App\Models\Stok;
use App\Models\Transaksi;
use Illuminate\Support\Str;

class NomorStrukService
{
    /**
     * Nomor urut berikutnya untuk bulan berjalan, dihitung dari nomor
     * tertinggi yang pernah terbit (termasuk transaksi terhapus).
     */
    public function next(?string $prefix = null): string
    {
        $prefix = $prefix ?? (string) config('pos.invoice_prefix', 'INV');
        $period = now()->format('Y/m');

        $maxSequence = Transaksi::withTrashed()
            ->where('nomor_struk', 'like', $prefix.'/'.$period.'/%')
            ->pluck('nomor_struk')
            ->reduce(function (int $carry, string $number): int {
                if (preg_match('#/(\d+)$#', $number, $m) !== 1) {
                    return $carry;
                }

                return max($carry, (int) $m[1]);
            }, 0);

        $sequence = $maxSequence + 1;

        $candidate = sprintf('%s/%s/%04d', $prefix, $period, $sequence);

        while (Transaksi::withTrashed()->where('nomor_struk', $candidate)->exists()) {
            $candidate = sprintf('%s/%s/%04d', $prefix, $period, ++$sequence);
        }

        return $candidate;
    }

    public function generateSku(string $nama): string
    {
        $base = Str::upper(Str::slug($nama, '-'));
        $base = $base !== '' ? Str::limit($base, 40, '') : 'PRODUK';

        $candidate = $base;
        $counter = 1;

        while (Stok::query()->where('kode_sku', $candidate)->exists()) {
            $candidate = Str::limit($base, 40, '').'-'.(++$counter);
        }

        return $candidate;
    }

    public function ensureStockAvailable(Stok $stok, int $jumlah): void
    {
        if ($stok->kuantitas < $jumlah) {
            throw new ApiException(
                sprintf(
                    'Stok %s tidak mencukupi (tersedia %d, diminta %d).',
                    $stok->nama_produk,
                    $stok->kuantitas,
                    $jumlah,
                ),
                'STOK_TIDAK_CUKUP',
                422,
                ['jumlah' => ['Stok tidak mencukupi.']],
            );
        }
    }
}
