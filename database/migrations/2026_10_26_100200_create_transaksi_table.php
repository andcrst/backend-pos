<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transaksi', function (Blueprint $table) {
            $table->id('id_transaksi');
            $table->string('nomor_struk', 30)->unique('transaksi_nomor_struk_unique');
            $table->foreignId('id_pengguna')->constrained('users', 'id')->restrictOnDelete();
            $table->timestamp('tanggal_transaksi');
            $table->decimal('total_pembayaran', 15, 2)->default(0);
            $table->enum('jenis_pembayaran', ['TUNAI', 'QRIS', 'TRANSFER']);
            $table->decimal('kembalian', 15, 2)->default(0);
            $table->timestamps();

            $table->index(['tanggal_transaksi', 'id_pengguna'], 'transaksi_tanggal_pengguna_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transaksi');
    }
};
