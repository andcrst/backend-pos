<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreKategoriRequest;
use App\Http\Resources\KategoriResource;
use App\Models\Kategori;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class KategoriController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) $request->query('per_page', 50), 100);

        $kategori = Kategori::query()
            ->search($request->query('search'))
            ->withCount('stok')
            ->orderBy('nama_kategori')
            ->paginate($perPage)
            ->through(fn (Kategori $item) => new KategoriResource($item));

        return ApiResponse::paginated($kategori, 'Daftar kategori.');
    }

    public function store(StoreKategoriRequest $request): JsonResponse
    {
        $kategori = Kategori::create($request->validated());

        return ApiResponse::success(
            new KategoriResource($kategori->loadCount('stok')),
            'Kategori berhasil dibuat.',
            201,
        );
    }

    public function show(Kategori $kategori): JsonResponse
    {
        return ApiResponse::success(
            new KategoriResource($kategori->loadCount('stok')),
        );
    }

    public function update(StoreKategoriRequest $request, Kategori $kategori): JsonResponse
    {
        $kategori->fill($request->validated())->save();

        return ApiResponse::success(
            new KategoriResource($kategori->refresh()->loadCount('stok')),
            'Kategori berhasil diperbarui.',
        );
    }

    public function destroy(Kategori $kategori): JsonResponse
    {
        if ($kategori->stok()->exists()) {
            return ApiResponse::error(
                'Kategori masih dipakai produk sehingga tidak dapat dihapus.',
                'KATEGORI_MASIH_DIPAKAI',
                409,
            );
        }

        $kategori->delete();

        return ApiResponse::success(null, 'Kategori berhasil dihapus.');
    }
}
