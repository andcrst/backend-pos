<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stok', function (Blueprint $table) {
            $table->id('id_produk');
            $table->string('kode_sku', 50)->unique('stok_kode_sku_unique');
            $table->string('nama_produk', 150);
            $table->integer('harga')->default(0);
            $table->integer('kuantitas')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stok');
    }
};
