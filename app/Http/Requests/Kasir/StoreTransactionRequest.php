<?php

namespace App\Http\Requests\Kasir;

use Illuminate\Foundation\Http\FormRequest;

class StoreTransactionRequest extends FormRequest
{
    /**
     * Tentukan apakah pengguna berwenang untuk membuat request ini.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        // Kasir diizinkan membuat transaksi sesuai SOP.
        return true;
    }

    /**
     * Mendapatkan aturan validasi yang berlaku untuk request ini.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'payment_method' => ['required', 'string', 'max:50'],
            'total_amount' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.total_price' => ['required', 'numeric', 'min:0'],
        ];
    }

    /**
     * Menyiapkan data untuk validasi.
     *
     * @return void
     */
    protected function prepareForValidation()
    {
        // Menghitung ulang total_amount di backend untuk memastikan integritas data.
        $totalAmount = 0;

        if ($this->has('items')) {
            $totalAmount = collect($this->items)->sum(function ($item) {
                return ($item['unit_price'] ?? 0) * ($item['quantity'] ?? 0);
            });
        }

        $this->merge([
            'total_amount' => $totalAmount,
        ]);
    }
}
