<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Menghubungkan stok ke kategori beserta pengisian data lama.
 */
return new class extends Migration
{
    private const FALLBACK = 'Lain-lain';

    public function up(): void
    {
        Schema::table('stok', function (Blueprint $table) {
            $table->unsignedBigInteger('id_kategori')->nullable()->after('nama_produk');

            $table->foreign('id_kategori', 'stok_id_kategori_foreign')
                ->references('id_kategori')
                ->on('kategori')
                ->nullOnDelete();

            $table->index(['id_kategori', 'nama_produk'], 'stok_kategori_nama_index');
        });

        $this->backfill();
    }

    /**
     * Isi kategori produk lama dari CSV katalog.
     */
    private function backfill(): void
    {
        if (DB::table('stok')->count() === 0) {
            return;
        }

        $path = database_path('data/produk_dobrak_mandiri.csv');

        if (! is_file($path)) {
            return;
        }

        $handle = fopen($path, 'rb');

        if ($handle === false) {
            return;
        }

        fgetcsv($handle, null, ',', '"', '\\'); // lewati header
        $peta = [];

        while (($row = fgetcsv($handle, null, ',', '"', '\\')) !== false) {
            if (count($row) < 4) {
                continue;
            }

            $nama = trim((string) $row[2]);
            $namaKategori = trim((string) $row[1]);

            if ($nama === '' || $namaKategori === '') {
                continue;
            }

            $peta[$nama] = $namaKategori;
        }

        fclose($handle);

        $idPerNama = [];

        foreach (array_unique(array_values($peta)) as $namaKategori) {
            $idPerNama[$namaKategori] = DB::table('kategori')->insertGetId([
                'nama_kategori' => $namaKategori,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $fallback = DB::table('kategori')->where('nama_kategori', self::FALLBACK)->value('id_kategori')
            ?? DB::table('kategori')->insertGetId([
                'nama_kategori' => self::FALLBACK,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

        DB::table('stok')->orderBy('id_produk')->each(function (object $stok) use ($peta, $idPerNama, $fallback): void {
            $namaKategori = $peta[$stok->nama_produk] ?? null;

            DB::table('stok')
                ->where('id_produk', $stok->id_produk)
                ->update(['id_kategori' => $idPerNama[$namaKategori] ?? $fallback]);
        });
    }

    public function down(): void
    {
        Schema::table('stok', function (Blueprint $table) {
            $table->dropForeign('stok_id_kategori_foreign');
            $table->dropIndex('stok_kategori_nama_index');
            $table->dropColumn('id_kategori');
        });
    }
};
