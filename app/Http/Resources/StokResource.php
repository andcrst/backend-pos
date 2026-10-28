<?php

namespace App\Http\Resources;

use App\Models\Stok;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Stok
 */
class StokResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id_produk' => $this->id_produk,
            'kode_sku' => $this->kode_sku,
            'nama_produk' => $this->nama_produk,
            'harga' => (int) $this->harga,
            'kuantitas' => (int) $this->kuantitas,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
