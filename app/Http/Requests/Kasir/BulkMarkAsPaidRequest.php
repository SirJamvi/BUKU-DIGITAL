<?php

namespace App\Http\Requests\Kasir;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class BulkMarkAsPaidRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check();
    }

    public function rules(): array
    {
        $method = strtolower((string) $this->input('payment_method', ''));
        $isCash = $method === 'cash';
        $isDeposit = $method === 'deposit';

        return [
            'transaction_ids'      => ['required', 'array', 'min:1'],
            'transaction_ids.*'    => ['integer', 'exists:transactions,id'],
            'payment_method'       => ['required', 'string', 'max:50'],
            'settlement_date_mode' => ['nullable', 'string', 'in:today,nota_date'],
            'settlement_notes'     => ['nullable', 'string', 'max:255'],
            'deposit_amount'       => ['nullable', 'numeric', 'min:0'],
            'payment_proof'        => [($isCash || $isDeposit) ? 'nullable' : 'required', 'file', 'mimes:jpeg,png,jpg,webp', 'max:10240'],
        ];
    }

    public function messages(): array
    {
        return [
            'transaction_ids.required' => 'Anda belum memilih transaksi satupun untuk dilunasi.',
            'transaction_ids.min'      => 'Anda belum memilih transaksi satupun untuk dilunasi.',
            'payment_proof.required'   => 'Bukti pembayaran / transfer WhatsApp wajib diunggah untuk pelunasan non-tunai (' . strtoupper((string) $this->input('payment_method', '')) . ').',
            'deposit_amount.numeric'   => 'Nominal kelebihan bayar deposit tidak valid.',
            'deposit_amount.min'       => 'Nominal kelebihan bayar deposit tidak boleh negatif.',
        ];
    }
}
