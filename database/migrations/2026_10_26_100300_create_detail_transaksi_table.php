<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('detail_transaksi', function (Blueprint $table) {
            $table->id('id_detail');
            $table->foreignId('id_transaksi')->constrained('transaksi', 'id_transaksi')->cascadeOnDelete();
            $table->foreignId('id_produk')->constrained('stok', 'id_produk')->restrictOnDelete();
            $table->integer('jumlah');
            $table->decimal('sub_total', 15, 2)->default(0);
            $table->timestamps();

            $table->index('id_produk', 'detail_transaksi_id_produk_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('detail_transaksi');
    }
};
