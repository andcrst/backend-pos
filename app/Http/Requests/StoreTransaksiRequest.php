<?php

namespace App\Http\Requests;

use App\Enums\JenisPembayaran;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validasi pembuatan transaksi (tabel `transaksi` + `detail_transaksi`).
 */
class StoreTransaksiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'detail' => ['required', 'array', 'min:1'],
            'detail.*.id_produk' => ['required', 'integer', 'exists:stok,id_produk', 'distinct'],
            'detail.*.jumlah' => ['required', 'integer', 'min:1'],
            'jenis_pembayaran' => ['required', 'string', Rule::in(JenisPembayaran::values())],
            // Uang dibayar pelanggan; wajib untuk tunai agar kembalian bisa dihitung.
            'jumlah_bayar' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'detail.required' => 'Transaksi harus memiliki minimal satu produk.',
            'detail.*.id_produk.required' => 'Setiap baris harus menyertakan id_produk.',
            'detail.*.id_produk.exists' => 'Produk tidak ditemukan pada tabel stok.',
            'detail.*.id_produk.distinct' => 'Produk tidak boleh muncul lebih dari sekali.',
            'detail.*.jumlah.required' => 'Jumlah produk wajib diisi.',
            'detail.*.jumlah.min' => 'Jumlah produk minimal 1.',
            'detail.*.jumlah.integer' => 'Jumlah produk harus berupa angka bulat.',
            'jenis_pembayaran.required' => 'Jenis pembayaran wajib diisi.',
            'jenis_pembayaran.in' => 'Jenis pembayaran harus TUNAI, QRIS, atau TRANSFER.',
            'jumlah_bayar.numeric' => 'Jumlah bayar harus berupa angka.',
            'jumlah_bayar.min' => 'Jumlah bayar tidak boleh negatif.',
        ];
    }
}
