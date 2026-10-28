<?php

namespace Tests\Feature;

use App\Models\Stok;
use App\Models\Transaksi;

class TransaksiTest extends PosTestCase
{
    public function test_it_creates_transaction_and_reduces_stock(): void
    {
        $kasir = $this->actingAsRole('KASIR');

        $semen = $this->makeStok('Semen', 65000, 10, 'SEMEN');
        $pasir = $this->makeStok('Pasir', 175000, 5, 'PASIR');

        $response = $this->postJson('/api/v1/transaksi', [
            'detail' => [
                ['id_produk' => $semen->id_produk, 'jumlah' => 2],
                ['id_produk' => $pasir->id_produk, 'jumlah' => 1],
            ],
            'jenis_pembayaran' => 'TUNAI',
            'jumlah_bayar' => 400000,
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id_pengguna', $kasir->id)
            ->assertJsonPath('data.total_pembayaran', 305000)
            ->assertJsonPath('data.jenis_pembayaran', 'TUNAI')
            ->assertJsonPath('data.kembalian', 95000)
            ->assertJsonCount(2, 'data.detail');

        $this->assertStringStartsWith('INV/', $response->json('data.nomor_struk'));

        // Otomatisasi pengurangan stok.
        $this->assertSame(8, $semen->fresh()->kuantitas);
        $this->assertSame(4, $pasir->fresh()->kuantitas);

        $this->assertDatabaseHas('detail_transaksi', [
            'id_produk' => $semen->id_produk,
            'jumlah' => 2,
            'sub_total' => 130000,
        ]);
    }

    public function test_it_rejects_transaction_when_stock_insufficient(): void
    {
        $this->actingAsRole('KASIR');
        $semen = $this->makeStok('Semen', 65000, 1);

        $this->postJson('/api/v1/transaksi', [
            'detail' => [['id_produk' => $semen->id_produk, 'jumlah' => 5]],
            'jenis_pembayaran' => 'TUNAI',
        ])
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'STOK_TIDAK_CUKUP');

        // Tidak ada transaksi maupun pengurangan stok yang tersimpan.
        $this->assertSame(0, Transaksi::query()->count());
        $this->assertSame(1, $semen->fresh()->kuantitas);
    }

    public function test_it_rejects_cash_payment_below_total(): void
    {
        $this->actingAsRole('KASIR');
        $semen = $this->makeStok('Semen', 65000, 10);

        $this->postJson('/api/v1/transaksi', [
            'detail' => [['id_produk' => $semen->id_produk, 'jumlah' => 1]],
            'jenis_pembayaran' => 'TUNAI',
            'jumlah_bayar' => 50000,
        ])
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'PEMBAYARAN_KURANG');

        $this->assertSame(0, Transaksi::query()->count());
    }

    public function test_non_cash_transaction_has_no_change(): void
    {
        $this->actingAsRole('KASIR');
        $semen = $this->makeStok('Semen', 65000, 10);

        $this->postJson('/api/v1/transaksi', [
            'detail' => [['id_produk' => $semen->id_produk, 'jumlah' => 2]],
            'jenis_pembayaran' => 'QRIS',
            'jumlah_bayar' => 999999,
        ])
            ->assertCreated()
            ->assertJsonPath('data.jenis_pembayaran', 'QRIS')
            ->assertJsonPath('data.kembalian', 0);
    }

    public function test_it_rejects_unknown_payment_type(): void
    {
        $this->actingAsRole('KASIR');
        $semen = $this->makeStok('Semen', 65000, 10);

        $this->postJson('/api/v1/transaksi', [
            'detail' => [['id_produk' => $semen->id_produk, 'jumlah' => 1]],
            'jenis_pembayaran' => 'KARTU_KREDIT',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('jenis_pembayaran');
    }

    public function test_it_rejects_empty_detail(): void
    {
        $this->actingAsRole('KASIR');

        $this->postJson('/api/v1/transaksi', [
            'detail' => [],
            'jenis_pembayaran' => 'TUNAI',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('detail');
    }

    public function test_it_rejects_duplicate_product_in_detail(): void
    {
        $this->actingAsRole('KASIR');
        $semen = $this->makeStok('Semen', 65000, 10);

        $this->postJson('/api/v1/transaksi', [
            'detail' => [
                ['id_produk' => $semen->id_produk, 'jumlah' => 1],
                ['id_produk' => $semen->id_produk, 'jumlah' => 2],
            ],
            'jenis_pembayaran' => 'TUNAI',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('detail.1.id_produk');
    }

    public function test_struk_returns_detail_with_product_snapshot(): void
    {
        $this->actingAsRole('KASIR');
        $semen = $this->makeStok('Semen', 65000, 10, 'SEMEN');

        $created = $this->postJson('/api/v1/transaksi', [
            'detail' => [['id_produk' => $semen->id_produk, 'jumlah' => 3]],
            'jenis_pembayaran' => 'TRANSFER',
        ])->assertCreated();

        $id = $created->json('data.id_transaksi');

        $this->getJson("/api/v1/transaksi/{$id}/struk")
            ->assertOk()
            ->assertJsonPath('data.nomor_struk', $created->json('data.nomor_struk'))
            ->assertJsonPath('data.detail.0.produk.kode_sku', 'SEMEN')
            ->assertJsonPath('data.detail.0.sub_total', 195000);
    }

    public function test_cancelling_transaction_restores_stock(): void
    {
        $this->actingAsRole('MANAGER');
        $semen = $this->makeStok('Semen', 65000, 10);

        $created = $this->postJson('/api/v1/transaksi', [
            'detail' => [['id_produk' => $semen->id_produk, 'jumlah' => 4]],
            'jenis_pembayaran' => 'TUNAI',
            'jumlah_bayar' => 300000,
        ])->assertCreated();

        $this->assertSame(6, $semen->fresh()->kuantitas);

        $this->deleteJson('/api/v1/transaksi/'.$created->json('data.id_transaksi'))
            ->assertOk();

        $this->assertSame(10, $semen->fresh()->kuantitas);

        // Transaksi dibatalkan secara permanen (soft delete), bukan dihapus fisik.
        $this->assertSoftDeleted('transaksi', ['id_transaksi' => $created->json('data.id_transaksi')]);
        $this->assertSame(0, Transaksi::query()->count());
        $this->assertSame(1, Transaksi::withTrashed()->count());
    }

    public function test_it_lists_transactions_with_date_filter(): void
    {
        $this->actingAsRole('KASIR');
        $semen = $this->makeStok('Semen', 65000, 50);

        $this->postJson('/api/v1/transaksi', [
            'detail' => [['id_produk' => $semen->id_produk, 'jumlah' => 1]],
            'jenis_pembayaran' => 'TUNAI',
            'jumlah_bayar' => 65000,
        ])->assertCreated();

        $today = now()->toDateString();

        $this->getJson('/api/v1/transaksi?dari='.$today.'&sampai='.$today)
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->getJson('/api/v1/transaksi?dari=2000-01-01&sampai=2000-01-02')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_nomor_struk_increments(): void
    {
        $this->actingAsRole('KASIR');
        $semen = $this->makeStok('Semen', 1000, 100);

        $first = $this->postJson('/api/v1/transaksi', [
            'detail' => [['id_produk' => $semen->id_produk, 'jumlah' => 1]],
            'jenis_pembayaran' => 'TUNAI',
            'jumlah_bayar' => 1000,
        ])->assertCreated()->json('data.nomor_struk');

        $second = $this->postJson('/api/v1/transaksi', [
            'detail' => [['id_produk' => $semen->id_produk, 'jumlah' => 1]],
            'jenis_pembayaran' => 'TUNAI',
            'jumlah_bayar' => 1000,
        ])->assertCreated()->json('data.nomor_struk');

        $this->assertNotSame($first, $second);
        $this->assertStringEndsWith('/0001', $first);
        $this->assertStringEndsWith('/0002', $second);
    }

    public function test_stock_becomes_zero_after_selling_all(): void
    {
        $this->actingAsRole('KASIR');
        $semen = $this->makeStok('Semen', 65000, 2);

        $this->postJson('/api/v1/transaksi', [
            'detail' => [['id_produk' => $semen->id_produk, 'jumlah' => 2]],
            'jenis_pembayaran' => 'TUNAI',
            'jumlah_bayar' => 130000,
        ])->assertCreated();

        $this->assertSame(0, Stok::query()->find($semen->id_produk)->kuantitas);
    }

    public function test_nomor_struk_does_not_repeat_after_cancel(): void
    {
        $this->actingAsRole('MANAGER');
        $semen = $this->makeStok('Semen', 65000, 20);

        $first = $this->postJson('/api/v1/transaksi', [
            'detail' => [['id_produk' => $semen->id_produk, 'jumlah' => 1]],
            'jenis_pembayaran' => 'TUNAI',
            'jumlah_bayar' => 65000,
        ])->assertCreated();

        $firstNumber = $first->json('data.nomor_struk');

        $this->deleteJson('/api/v1/transaksi/'.$first->json('data.id_transaksi'))->assertOk();

        $second = $this->postJson('/api/v1/transaksi', [
            'detail' => [['id_produk' => $semen->id_produk, 'jumlah' => 1]],
            'jenis_pembayaran' => 'TUNAI',
            'jumlah_bayar' => 65000,
        ])->assertCreated();

        $secondNumber = $second->json('data.nomor_struk');

        $this->assertNotSame($firstNumber, $secondNumber, "Nomor struk terulang: {$firstNumber}");
        $this->assertStringEndsWith('/0001', $firstNumber);
        $this->assertStringEndsWith('/0002', $secondNumber);
    }

    public function test_guest_cannot_create_transaction(): void
    {
        $this->postJson('/api/v1/transaksi', [
            'detail' => [],
            'jenis_pembayaran' => 'TUNAI',
        ])->assertStatus(401);
    }
}
