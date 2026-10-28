<?php

namespace App\Http\Resources;

use App\Enums\JenisPembayaran;
use App\Models\DetailTransaksi;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin DetailTransaksi
 */
class DetailTransaksiResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id_detail' => $this->id_detail,
            'id_transaksi' => $this->id_transaksi,
            'id_produk' => $this->id_produk,
            'produk' => $this->whenLoaded('produk', fn () => [
                'kode_sku' => $this->produk->kode_sku,
                'nama_produk' => $this->produk->nama_produk,
                'harga' => (int) $this->produk->harga,
            ]),
            'jumlah' => (int) $this->jumlah,
            'sub_total' => (float) $this->sub_total,
            'jenis_pembayaran' => $this->when(
                $this->transaksi !== null,
                fn () => $this->transaksi->jenis_pembayaran instanceof JenisPembayaran
                    ? $this->transaksi->jenis_pembayaran->value
                    : null,
            ),
        ];
    }
}
