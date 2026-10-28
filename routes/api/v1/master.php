<?php

use App\Http\Controllers\Api\V1\StokController;
use App\Http\Controllers\Api\V1\TransaksiController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'active'])->group(function (): void {
    Route::get('stok', [StokController::class, 'index'])
        ->middleware('permission:stok.view')->name('api.v1.stok.index');
    Route::post('stok', [StokController::class, 'store'])
        ->middleware('permission:stok.create')->name('api.v1.stok.store');
    Route::get('stok/scan', [StokController::class, 'scan'])
        ->middleware('permission:stok.view')->name('api.v1.stok.scan');
    Route::get('stok/{stok}', [StokController::class, 'show'])
        ->middleware('permission:stok.view')->name('api.v1.stok.show');
    Route::put('stok/{stok}', [StokController::class, 'update'])
        ->middleware('permission:stok.update')->name('api.v1.stok.update');
    Route::delete('stok/{stok}', [StokController::class, 'destroy'])
        ->middleware('permission:stok.delete')->name('api.v1.stok.destroy');

    Route::get('transaksi', [TransaksiController::class, 'index'])
        ->middleware('permission:transaksi.view')->name('api.v1.transaksi.index');
    Route::post('transaksi', [TransaksiController::class, 'store'])
        ->middleware('permission:transaksi.create')->name('api.v1.transaksi.store');
    Route::get('transaksi/{transaksi}', [TransaksiController::class, 'show'])
        ->middleware('permission:transaksi.view')->name('api.v1.transaksi.show');
    Route::get('transaksi/{transaksi}/struk', [TransaksiController::class, 'struk'])
        ->middleware('permission:transaksi.view')->name('api.v1.transaksi.struk');
    Route::delete('transaksi/{transaksi}', [TransaksiController::class, 'destroy'])
        ->middleware('permission:transaksi.void')->name('api.v1.transaksi.destroy');
});
