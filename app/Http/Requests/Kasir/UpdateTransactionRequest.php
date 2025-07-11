<?php

namespace App\Http\Requests\Kasir;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTransactionRequest extends FormRequest
{
    /**
     * Tentukan apakah pengguna berwenang untuk membuat request ini.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        // SOP mengizinkan kasir untuk 'update_own'.
        // Otorisasi yang lebih spesifik (misal: hanya dalam 5 menit setelah transaksi)
        // sebaiknya ditangani di dalam policy (misal: TransactionPolicy).
        $transaction = $this->route('transaction');
        return $this->user()->can('update', $transaction);
    }

    /**
     * Mendapatkan aturan validasi yang berlaku untuk request ini.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        // Kasir umumnya hanya diizinkan mengedit detail minor seperti catatan
        // atau mengubah pelanggan jika terjadi kesalahan input.
        // Mengubah detail finansial (item, harga) seharusnya tidak diizinkan.
        return [
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
