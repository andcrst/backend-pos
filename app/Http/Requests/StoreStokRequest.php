<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStokRequest extends FormRequest
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
        $stok = $this->route('stok');
        $isUpdate = $stok !== null;

        return [
            'kode_sku' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('stok', 'kode_sku')->ignore($stok?->id_produk, 'id_produk'),
            ],
            'nama_produk' => [Rule::requiredIf(! $isUpdate), 'string', 'max:150'],
            'harga' => [Rule::requiredIf(! $isUpdate), 'integer', 'min:0'],
            'kuantitas' => ['sometimes', 'integer', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'kode_sku.unique' => 'Kode SKU sudah digunakan.',
            'kuantitas.min' => 'Kuantitas tidak boleh negatif.',
            'nama_produk.required' => 'Nama produk wajib diisi.',
            'harga.required' => 'Harga wajib diisi.',
            'harga.integer' => 'Harga harus berupa angka bulat (rupiah).',
            'harga.min' => 'Harga tidak boleh negatif.',
            'kuantitas.integer' => 'Kuantitas harus berupa angka bulat.',
            'kode_sku.max' => 'Kode SKU maksimal 50 karakter.',
            'nama_produk.max' => 'Nama produk maksimal 150 karakter.',
        ];
    }
}
