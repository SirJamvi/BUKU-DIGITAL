<?php

namespace App\Http\Requests\Kasir;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCustomerRequest extends FormRequest
{
    /**
     * Tentukan apakah pengguna berwenang untuk membuat request ini.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        // Kasir diizinkan menambah pelanggan sesuai SOP.
        return true;
    }

    /**
     * Mendapatkan aturan validasi yang berlaku untuk request ini.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $businessId = auth()->user()->business_id ?? null;

        return [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20', Rule::unique('customers', 'phone')->where('business_id', $businessId)],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('customers', 'email')->where('business_id', $businessId)],
            'address' => ['nullable', 'string'],
        ];
    }
}
