<?php

namespace Database\Seeders;

use App\Models\Stok;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Impor katalog dari database/data/produk_dobrak_mandiri.csv.
 */
class ProductCatalogSeeder extends Seeder
{
    private const INITIAL_QTY = 10;

    public function run(): void
    {
        $path = database_path('data/produk_dobrak_mandiri.csv');

        if (! is_file($path)) {
            $this->command?->warn("Katalog tidak ditemukan: {$path}");

            return;
        }

        $handle = fopen($path, 'rb');

        if ($handle === false) {
            return;
        }

        fgetcsv($handle, null, ',', '"', '\\'); // lewati header

        DB::transaction(function () use ($handle): void {
            while (($row = fgetcsv($handle, null, ',', '"', '\\')) !== false) {
                if (count($row) < 4) {
                    continue;
                }

                $nama = trim((string) $row[2]);
                $harga = (int) $row[3];

                if ($nama === '') {
                    continue;
                }

                Stok::query()->firstOrCreate(
                    ['nama_produk' => $nama],
                    [
                        'kode_sku' => $this->uniqueSku($nama),
                        'harga' => $harga,
                        'kuantitas' => self::INITIAL_QTY,
                    ],
                );
            }
        });

        fclose($handle);
    }

    private function uniqueSku(string $nama): string
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
}
