<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['kode_sku', 'nama_produk', 'id_kategori', 'harga', 'kuantitas'])]
class Stok extends Model
{
    use HasFactory;

    protected $table = 'stok';

    protected $primaryKey = 'id_produk';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'id_kategori' => 'integer',
            'harga' => 'integer',
            'kuantitas' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Kategori, $this>
     */
    public function kategori(): BelongsTo
    {
        return $this->belongsTo(Kategori::class, 'id_kategori', 'id_kategori');
    }

    /**
     * @return HasMany<DetailTransaksi, $this>
     */
    public function detailTransaksi(): HasMany
    {
        return $this->hasMany(DetailTransaksi::class, 'id_produk', 'id_produk');
    }

    /**
     * @param  Builder<Stok>  $query
     */
    public function scopeByKategori(Builder $query, ?int $idKategori): void
    {
        if ($idKategori === null) {
            return;
        }

        $query->where('id_kategori', $idKategori);
    }

    /**
     * @param  Builder<Stok>  $query
     */
    public function scopeSearch(Builder $query, ?string $term): void
    {
        if ($term === null || trim($term) === '') {
            return;
        }

        $like = '%'.trim($term).'%';

        $query->where(function (Builder $builder) use ($like): void {
            $builder->where('nama_produk', 'like', $like)
                ->orWhere('kode_sku', 'like', $like);
        });
    }

    /**
     * @param  Builder<Stok>  $query
     */
    public function scopeWithStock(Builder $query): void
    {
        $query->where('kuantitas', '>', 0);
    }
}
