<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['nama_kategori'])]
class Kategori extends Model
{
    use HasFactory;

    protected $table = 'kategori';

    protected $primaryKey = 'id_kategori';

    /**
     * @return HasMany<Stok, $this>
     */
    public function stok(): HasMany
    {
        return $this->hasMany(Stok::class, 'id_kategori', 'id_kategori');
    }

    /**
     * @param  Builder<Kategori>  $query
     */
    public function scopeSearch(Builder $query, ?string $term): void
    {
        if ($term === null || trim($term) === '') {
            return;
        }

        $query->where('nama_kategori', 'like', '%'.trim($term).'%');
    }
}
