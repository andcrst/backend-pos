<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\JenisPembayaran;
use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTransaksiRequest;
use App\Http\Resources\TransaksiResource;
use App\Models\DetailTransaksi;
use App\Models\Stok;
use App\Models\Transaksi;
use App\Services\NomorStrukService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TransaksiController extends Controller
{
    public function __construct(private readonly NomorStrukService $nomor) {}

    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) $request->query('per_page', 20), 100);

        $transaksi = Transaksi::query()
            ->with('pengguna:id,name,email')
            ->withCount('detail')
            ->betweenDates(
                $request->query('dari'),
                $request->query('sampai'),
            )
            ->when($request->query('id_pengguna'), fn ($query, $id) => $query
                ->where('id_pengguna', $id))
            ->when($request->query('jenis_pembayaran'), fn ($query, $jenis) => $query
                ->where('jenis_pembayaran', $jenis))
            ->orderByDesc('tanggal_transaksi')
            ->paginate($perPage)
            ->through(fn (Transaksi $item) => new TransaksiResource($item));

        return ApiResponse::paginated($transaksi, 'Daftar transaksi.');
    }

    public function store(StoreTransaksiRequest $request): JsonResponse
    {
        $data = $request->validated();
        $jenis = JenisPembayaran::from($data['jenis_pembayaran']);

        $transaksi = DB::transaction(function () use ($data, $jenis): Transaksi {
            // Urutkan id agar penguncian baris konsisten dan bebas deadlock.
            $ids = collect($data['detail'])->pluck('id_produk')->sort()->values()->all();

            /** @var array<int, Stok> $stok */
            $stok = Stok::query()
                ->whereIn('id_produk', $ids)
                ->lockForUpdate()
                ->get()
                ->keyBy('id_produk')
                ->all();

            $total = 0;
            $detail = [];

            foreach ($data['detail'] as $item) {
                /** @var Stok $produk */
                $produk = $stok[$item['id_produk']];
                $jumlah = (int) $item['jumlah'];

                $this->nomor->ensureStockAvailable($produk, $jumlah);

                $subTotal = $jumlah * (int) $produk->harga;
                $total += $subTotal;

                $detail[] = [
                    'id_produk' => $produk->id_produk,
                    'jumlah' => $jumlah,
                    'sub_total' => $subTotal,
                ];
            }

            $bayar = $data['jumlah_bayar'] ?? null;

            if ($jenis->hasChange() && $bayar !== null && (float) $bayar < $total) {
                throw new ApiException(
                    'Jumlah bayar kurang dari total pembayaran.',
                    'PEMBAYARAN_KURANG',
                    422,
                    ['jumlah_bayar' => ['Jumlah bayar kurang dari total.']],
                );
            }

            $kembalian = $jenis->hasChange() && $bayar !== null
                ? (float) $bayar - $total
                : 0;

            $transaksi = Transaksi::create([
                'nomor_struk' => $this->nomor->next(),
                'id_pengguna' => auth()->id(),
                'tanggal_transaksi' => now(),
                'total_pembayaran' => $total,
                'jenis_pembayaran' => $jenis->value,
                'kembalian' => $kembalian,
            ]);

            foreach ($detail as $row) {
                DetailTransaksi::create($row + ['id_transaksi' => $transaksi->id_transaksi]);

                Stok::query()
                    ->whereKey($row['id_produk'])
                    ->decrement('kuantitas', $row['jumlah']);
            }

            return $transaksi;
        });

        return ApiResponse::success(
            new TransaksiResource($transaksi->load('detail.produk', 'pengguna')),
            'Transaksi berhasil.',
            201,
        );
    }

    public function show(Transaksi $transaksi): JsonResponse
    {
        return ApiResponse::success(
            new TransaksiResource($transaksi->load('detail.produk', 'pengguna')),
        );
    }

    public function struk(Transaksi $transaksi): JsonResponse
    {
        return ApiResponse::success(
            new TransaksiResource($transaksi->load('detail.produk', 'pengguna')),
            'Data struk.',
        );
    }

    /**
     * Batalkan transaksi dan kembalikan stok. Baris di-soft delete agar
     * nomor struk tetap terpakai sebagai histori.
     */
    public function destroy(Transaksi $transaksi): JsonResponse
    {
        DB::transaction(function () use ($transaksi): void {
            foreach ($transaksi->detail as $detail) {
                Stok::query()
                    ->whereKey($detail->id_produk)
                    ->increment('kuantitas', $detail->jumlah);
            }

            $transaksi->delete();
        });

        return ApiResponse::success(null, 'Transaksi dibatalkan dan stok dikembalikan.');
    }
}
