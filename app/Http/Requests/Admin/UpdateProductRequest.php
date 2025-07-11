<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends FormRequest
{
    /**
     * Tentukan apakah pengguna berwenang untuk membuat request ini.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Mendapatkan aturan validasi yang berlaku untuk request ini.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $productId = $this->route('product')->id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'category_id' => ['required', 'integer', 'exists:product_categories,id'],
            'unit' => ['required', 'string', 'max:50'],
            'base_price' => ['required', 'numeric', 'min:0'],
            'cost_price' => ['required', 'numeric', 'min:0', 'lte:base_price'],
            'description' => ['nullable', 'string'],
            'sku' => ['required', 'string', 'max:100', Rule::unique('products')->ignore($productId)],
            'barcode' => ['nullable', 'string', 'max:100', Rule::unique('products')->ignore($productId)],
            'is_active' => ['sometimes', 'boolean'],
            'is_featured' => ['sometimes', 'boolean'],
        ];
    }
}