<?php

namespace App\Http\Resources;

use App\Enums\JenisPembayaran;
use App\Models\Transaksi;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Transaksi
 */
class TransaksiResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $jenis = $this->jenis_pembayaran;

        return [
            'id_transaksi' => $this->id_transaksi,
            'nomor_struk' => $this->nomor_struk,
            'id_pengguna' => $this->id_pengguna,
            'pengguna' => $this->whenLoaded('pengguna', fn () => [
                'nama' => $this->pengguna->name,
                'email' => $this->pengguna->email,
            ]),
            'tanggal_transaksi' => $this->tanggal_transaksi?->toIso8601String(),
            'total_pembayaran' => (float) $this->total_pembayaran,
            'jenis_pembayaran' => $jenis instanceof JenisPembayaran ? $jenis->value : $jenis,
            'jenis_pembayaran_label' => $jenis instanceof JenisPembayaran ? $jenis->label() : null,
            'kembalian' => (float) $this->kembalian,
            'detail' => DetailTransaksiResource::collection($this->whenLoaded('detail')),
            'detail_count' => $this->whenCounted('detail'),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
