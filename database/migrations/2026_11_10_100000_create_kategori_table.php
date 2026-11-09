<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel kategori produk.
 *
 * Penambahan di luar skema PDF — disetujui untuk pengelompokan produk
 * (sumber: kolom "Kategori" pada Daftar_Produk_DobrakMandiri.xlsx).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kategori', function (Blueprint $table) {
            $table->id('id_kategori');
            $table->string('nama_kategori', 100)->unique('kategori_nama_unique');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kategori');
    }
};
