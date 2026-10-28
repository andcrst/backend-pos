<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('email', 150)->nullable()->change();
            $table->enum('role', ['KASIR', 'GUDANG', 'MANAGER'])->default('KASIR')->after('password');
            $table->unique('name', 'pengguna_nama_unique');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique('pengguna_nama_unique');
            $table->dropColumn('role');
        });
    }
};
