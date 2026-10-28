<?php

namespace App\Models;

use App\Enums\JenisPembayaran;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'nomor_struk',
    'id_pengguna',
    'tanggal_transaksi',
    'total_pembayaran',
    'jenis_pembayaran',
    'kembalian',
])]
class Transaksi extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'transaksi';

    protected $primaryKey = 'id_transaksi';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tanggal_transaksi' => 'datetime',
            'total_pembayaran' => 'decimal:2',
            'jenis_pembayaran' => JenisPembayaran::class,
            'kembalian' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function pengguna(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_pengguna', 'id');
    }

    /**
     * @return HasMany<DetailTransaksi, $this>
     */
    public function detail(): HasMany
    {
        return $this->hasMany(DetailTransaksi::class, 'id_transaksi', 'id_transaksi');
    }

    /**
     * Filter inklusif berdasarkan tanggal (YYYY-MM-DD).
     *
     * @param  Builder<Transaksi>  $query
     */
    public function scopeBetweenDates(Builder $query, ?string $from, ?string $to): void
    {
        $query->when($from !== null && $from !== '', fn (Builder $builder) => $builder
            ->where('tanggal_transaksi', '>=', $from.' 00:00:00'))
            ->when($to !== null && $to !== '', fn (Builder $builder) => $builder
                ->where('tanggal_transaksi', '<=', $to.' 23:59:59'));
    }
}
