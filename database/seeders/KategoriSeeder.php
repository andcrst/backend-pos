<?php

namespace Database\Seeders;

use App\Models\Kategori;
use Illuminate\Database\Seeder;

/**
 * Kategori produk dari kolom "Kategori" pada Daftar_Produk_DobrakMandiri.xlsx.
 */
class KategoriSeeder extends Seeder
{
    /**
     * Urutan mengikuti kemunculan pertama pada katalog.
     *
     * @var list<string>
     */
    public const DAFTAR = [
        'Semen',
        'Pasir',
        'Batu dan agregat',
        'Tangki dan drum',
        'Triplek dan papan',
        'Besi dan baja',
        'GRC',
        'Pipa',
        'Lain-lain',
        'Keramik dan granit',
        'Kayu',
        'Sanitasi',
        'Pintu dan kusen',
        'Peralatan',
        'Atap dan baja ringan',
        'Cat dan pelarut',
    ];

    public function run(): void
    {
        foreach (self::DAFTAR as $nama) {
            Kategori::query()->firstOrCreate(['nama_kategori' => $nama]);
        }
    }
}
