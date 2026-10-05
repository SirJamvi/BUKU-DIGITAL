<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreSupplierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string'],
            'payment_terms' => ['nullable', 'string', 'max:255'],
            
            // Validasi Input Array Produk & Harga
            'products' => ['nullable', 'array'],
            'products.*.id' => ['required', 'exists:products,id'],
            'products.*.price' => ['required', 'numeric', 'min:0'],
        ];
    }
}