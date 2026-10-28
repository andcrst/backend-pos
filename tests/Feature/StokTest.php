<?php

namespace Tests\Feature;

use App\Models\Stok;

class StokTest extends PosTestCase
{
    public function test_it_lists_stok_with_pagination(): void
    {
        $this->actingAsRole('KASIR');
        $this->makeStok('Semen');
        $this->makeStok('Pasir', 175000, 5);

        $this->getJson('/api/v1/stok')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total', 2);
    }

    public function test_it_creates_stok_with_generated_sku(): void
    {
        $this->actingAsRole('GUDANG');

        $this->postJson('/api/v1/stok', [
            'nama_produk' => 'Besi 10 mm',
            'harga' => 73000,
            'kuantitas' => 25,
        ])
            ->assertCreated()
            ->assertJsonPath('data.kode_sku', 'BESI-10-MM')
            ->assertJsonPath('data.nama_produk', 'Besi 10 mm')
            ->assertJsonPath('data.kuantitas', 25);

        $this->assertDatabaseHas('stok', ['nama_produk' => 'Besi 10 mm', 'kuantitas' => 25]);
    }

    public function test_it_rejects_duplicate_sku(): void
    {
        $this->actingAsRole('GUDANG');
        $this->makeStok('Semen', 65000, 10, 'SEMEN');

        $this->postJson('/api/v1/stok', [
            'kode_sku' => 'SEMEN',
            'nama_produk' => 'Semen Baru',
            'harga' => 70000,
        ])
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'VALIDATION_ERROR')
            ->assertJsonValidationErrors('kode_sku');
    }

    public function test_it_rejects_negative_kuantitas(): void
    {
        $this->actingAsRole('GUDANG');

        $this->postJson('/api/v1/stok', [
            'nama_produk' => 'Semen',
            'harga' => 65000,
            'kuantitas' => -1,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('kuantitas');
    }

    public function test_it_updates_kuantitas_and_harga(): void
    {
        $this->actingAsRole('GUDANG');
        $stok = $this->makeStok('Semen');

        $this->putJson("/api/v1/stok/{$stok->id_produk}", [
            'harga' => 68000,
            'kuantitas' => 40,
        ])
            ->assertOk()
            ->assertJsonPath('data.harga', 68000)
            ->assertJsonPath('data.kuantitas', 40);
    }

    public function test_it_scans_by_sku(): void
    {
        $this->actingAsRole('KASIR');
        $this->makeStok('Semen', 65000, 10, 'SMN-01');

        $this->getJson('/api/v1/stok/scan?kode_sku=SMN-01')
            ->assertOk()
            ->assertJsonPath('data.nama_produk', 'Semen')
            ->assertJsonPath('data.harga', 65000);
    }

    public function test_it_returns_404_when_scan_not_found(): void
    {
        $this->actingAsRole('KASIR');

        $this->getJson('/api/v1/stok/scan?kode_sku=TIDAK-ADA')
            ->assertStatus(404)
            ->assertJsonPath('error_code', 'NOT_FOUND');
    }

    public function test_it_deletes_stok_without_transactions(): void
    {
        $this->actingAsRole('GUDANG');
        $stok = $this->makeStok('Semen');

        $this->deleteJson("/api/v1/stok/{$stok->id_produk}")
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('stok', ['id_produk' => $stok->id_produk]);
    }

    public function test_cashier_cannot_create_stok(): void
    {
        $this->actingAsRole('KASIR');

        $this->postJson('/api/v1/stok', [
            'nama_produk' => 'Semen',
            'harga' => 65000,
        ])->assertStatus(403);
    }

    public function test_guest_cannot_access_stok(): void
    {
        $this->getJson('/api/v1/stok')
            ->assertStatus(401)
            ->assertJsonPath('error_code', 'UNAUTHENTICATED');
    }

    public function test_it_filters_only_available_stock(): void
    {
        $this->actingAsRole('KASIR');
        $this->makeStok('Semen', 65000, 10);
        $this->makeStok('Pasir', 175000, 0);

        $this->getJson('/api/v1/stok?tersedia=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.nama_produk', 'Semen');

        $this->assertSame(2, Stok::query()->count());
    }
}
