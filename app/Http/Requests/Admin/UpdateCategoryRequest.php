<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCategoryRequest extends FormRequest
{
    /**
     * Tentukan apakah pengguna berwenang untuk membuat request ini.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        // Otorisasi ditangani oleh middleware, jadi kita set ke true.
        return true;
    }

    /**
     * Mendapatkan aturan validasi yang berlaku untuk request ini.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        // Mengambil ID kategori dari route parameter
        $categoryId = $this->route('category')->id;

        return [
            // Nama harus unik, kecuali untuk kategori itu sendiri
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('product_categories')->ignore($categoryId),
            ],
            'description' => ['nullable', 'string'],
            // Kategori tidak bisa menjadi parent dari dirinya sendiri
            'parent_id' => [
                'nullable',
                'integer',
                'exists:product_categories,id',
                'different:id'
            ],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}