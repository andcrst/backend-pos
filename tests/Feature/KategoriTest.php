<?php

namespace Tests\Feature;

use App\Models\Kategori;
use App\Models\Stok;
use Illuminate\Database\QueryException;

class KategoriTest extends PosTestCase
{
    public function test_daftar_kategori_dengan_jumlah_produk(): void
    {
        $this->actingAsRole('MANAGER');

        $pipa = Kategori::create(['nama_kategori' => 'Pipa']);
        Kategori::create(['nama_kategori' => 'Kayu']);

        Stok::create(['kode_sku' => 'PIPA-1', 'nama_produk' => 'Pipa 1', 'id_kategori' => $pipa->id_kategori, 'harga' => 1000, 'kuantitas' => 5]);
        Stok::create(['kode_sku' => 'PIPA-2', 'nama_produk' => 'Pipa 2', 'id_kategori' => $pipa->id_kategori, 'harga' => 2000, 'kuantitas' => 5]);

        $this->getJson('/api/v1/kategori')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.nama_kategori', 'Kayu')
            ->assertJsonPath('data.0.jumlah_produk', 0)
            ->assertJsonPath('data.1.nama_kategori', 'Pipa')
            ->assertJsonPath('data.1.jumlah_produk', 2);
    }

    public function test_kategori_bisa_dicari(): void
    {
        $this->actingAsRole('MANAGER');

        Kategori::create(['nama_kategori' => 'Besi dan baja']);
        Kategori::create(['nama_kategori' => 'Cat dan pelarut']);

        $this->getJson('/api/v1/kategori?search=besi')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.nama_kategori', 'Besi dan baja');
    }

    public function test_buat_kategori(): void
    {
        $this->actingAsRole('MANAGER');

        $this->postJson('/api/v1/kategori', ['nama_kategori' => 'Sanitasi'])
            ->assertCreated()
            ->assertJsonPath('data.nama_kategori', 'Sanitasi')
            ->assertJsonPath('data.jumlah_produk', 0);

        $this->assertDatabaseHas('kategori', ['nama_kategori' => 'Sanitasi']);
    }

    public function test_nama_kategori_wajib_diisi(): void
    {
        $this->actingAsRole('MANAGER');

        $this->postJson('/api/v1/kategori', ['nama_kategori' => ''])
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'VALIDATION_ERROR')
            ->assertJsonValidationErrors('nama_kategori')
            ->assertJsonPath('errors.nama_kategori.0', 'Nama kategori wajib diisi.');
    }

    public function test_nama_kategori_harus_unik(): void
    {
        $this->actingAsRole('MANAGER');
        Kategori::create(['nama_kategori' => 'Pipa']);

        $this->postJson('/api/v1/kategori', ['nama_kategori' => 'Pipa'])
            ->assertStatus(422)
            ->assertJsonPath('errors.nama_kategori.0', 'Nama kategori sudah digunakan.');
    }

    public function test_nama_kategori_maksimal_100_karakter(): void
    {
        $this->actingAsRole('MANAGER');

        $this->postJson('/api/v1/kategori', ['nama_kategori' => str_repeat('a', 101)])
            ->assertStatus(422)
            ->assertJsonPath('errors.nama_kategori.0', 'Nama kategori maksimal 100 karakter.');
    }

    public function test_ubah_kategori(): void
    {
        $this->actingAsRole('MANAGER');
        $kategori = Kategori::create(['nama_kategori' => 'Pipa']);

        $this->putJson("/api/v1/kategori/{$kategori->id_kategori}", ['nama_kategori' => 'Pipa PVC'])
            ->assertOk()
            ->assertJsonPath('data.nama_kategori', 'Pipa PVC');
    }

    public function test_hapus_kategori_tanpa_produk(): void
    {
        $this->actingAsRole('MANAGER');
        $kategori = Kategori::create(['nama_kategori' => 'Kosong']);

        $this->deleteJson("/api/v1/kategori/{$kategori->id_kategori}")
            ->assertOk()
            ->assertJsonPath('message', 'Kategori berhasil dihapus.');

        $this->assertDatabaseMissing('kategori', ['id_kategori' => $kategori->id_kategori]);
    }

    public function test_kategori_dengan_produk_tidak_bisa_dihapus(): void
    {
        $this->actingAsRole('MANAGER');
        $kategori = Kategori::create(['nama_kategori' => 'Pipa']);

        Stok::create(['kode_sku' => 'PIPA-1', 'nama_produk' => 'Pipa 1', 'id_kategori' => $kategori->id_kategori, 'harga' => 1000, 'kuantitas' => 1]);

        $this->deleteJson("/api/v1/kategori/{$kategori->id_kategori}")
            ->assertStatus(409)
            ->assertJsonPath('error_code', 'KATEGORI_MASIH_DIPAKAI');

        $this->assertDatabaseHas('kategori', ['id_kategori' => $kategori->id_kategori]);
    }

    public function test_hapus_kategori_membuat_produk_jadi_null(): void
    {
        $kategori = Kategori::create(['nama_kategori' => 'Sementara']);
        $stok = Stok::create(['kode_sku' => 'X-1', 'nama_produk' => 'X', 'id_kategori' => $kategori->id_kategori, 'harga' => 1000, 'kuantitas' => 1]);

        $kategori->delete();

        $this->assertNull($stok->refresh()->id_kategori);
    }

    public function test_kategori_tidak_kembali_404(): void
    {
        $this->actingAsRole('MANAGER');

        $this->getJson('/api/v1/kategori/999999')->assertNotFound();
    }

    public function test_kasir_hanya_boleh_melihat(): void
    {
        $this->actingAsRole('KASIR');

        $this->getJson('/api/v1/kategori')->assertOk();
        $this->postJson('/api/v1/kategori', ['nama_kategori' => 'Baru'])->assertStatus(403);
    }

    public function test_gudang_boleh_kelola_kategori(): void
    {
        $this->actingAsRole('GUDANG');

        $id = $this->postJson('/api/v1/kategori', ['nama_kategori' => 'Baru'])
            ->assertCreated()
            ->json('data.id_kategori');

        $this->deleteJson("/api/v1/kategori/{$id}")->assertOk();
    }

    public function test_tanpa_token_ditolak(): void
    {
        $this->getJson('/api/v1/kategori')->assertStatus(401);
    }

    public function test_nama_kategori_unik_di_level_database(): void
    {
        Kategori::create(['nama_kategori' => 'Pipa']);

        $this->expectException(QueryException::class);

        Kategori::create(['nama_kategori' => 'Pipa']);
    }
}
