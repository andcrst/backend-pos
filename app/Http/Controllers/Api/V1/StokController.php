<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreStokRequest;
use App\Http\Resources\StokResource;
use App\Models\Stok;
use App\Services\NomorStrukService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StokController extends Controller
{
    public function __construct(private readonly NomorStrukService $nomor) {}

    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) $request->query('per_page', 20), 100);

        $stok = Stok::query()
            ->search($request->query('search'))
            ->when($request->boolean('tersedia'), fn ($query) => $query->withStock())
            ->orderBy('nama_produk')
            ->paginate($perPage)
            ->through(fn (Stok $item) => new StokResource($item));

        return ApiResponse::paginated($stok, 'Daftar stok.');
    }

    public function store(StoreStokRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['kode_sku'] = $data['kode_sku'] ?? $this->nomor->generateSku($data['nama_produk']);
        $data['kuantitas'] = $data['kuantitas'] ?? 0;

        $stok = Stok::create($data);

        return ApiResponse::success(new StokResource($stok), 'Produk stok berhasil dibuat.', 201);
    }

    public function show(Stok $stok): JsonResponse
    {
        return ApiResponse::success(new StokResource($stok));
    }

    public function update(StoreStokRequest $request, Stok $stok): JsonResponse
    {
        $stok->fill($request->validated())->save();

        return ApiResponse::success(new StokResource($stok->refresh()), 'Stok berhasil diperbarui.');
    }

    public function destroy(Stok $stok): JsonResponse
    {
        if ($stok->detailTransaksi()->exists()) {
            return ApiResponse::error(
                'Produk sudah dipakai pada transaksi sehingga tidak dapat dihapus.',
                'RESOURCE_IN_USE',
                409,
            );
        }

        $stok->delete();

        return ApiResponse::success(null, 'Produk stok berhasil dihapus.');
    }

    public function scan(Request $request): JsonResponse
    {
        $kode = trim((string) $request->query('kode_sku', ''));

        if ($kode === '') {
            return ApiResponse::error('Parameter kode_sku wajib diisi.', 'VALIDATION_ERROR', 422, [
                'kode_sku' => ['Parameter kode_sku wajib diisi.'],
            ]);
        }

        $stok = Stok::query()->where('kode_sku', $kode)->first();

        if ($stok === null) {
            return ApiResponse::error('Produk tidak ditemukan.', 'NOT_FOUND', 404);
        }

        return ApiResponse::success(new StokResource($stok), 'Produk ditemukan.');
    }
}
