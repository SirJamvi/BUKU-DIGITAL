<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class CorrectDepositRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check();
    }

    public function rules(): array
    {
        return [
            'new_amount'         => ['required', 'numeric', 'min:1'],
            'new_payment_method' => ['required', 'string', 'in:cash,transfer-bank,dana,opening_balance'],
            'reason'             => ['required', 'string', 'min:3', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'new_amount.required'         => 'Nominal koreksi wajib diisi.',
            'new_amount.min'              => 'Nominal koreksi minimal Rp 1.',
            'new_payment_method.required' => 'Metode pembayaran koreksi wajib dipilih.',
            'new_payment_method.in'       => 'Metode pembayaran koreksi tidak valid.',
            'reason.required'             => 'Alasan koreksi wajib diisi untuk jejak audit finance.',
            'reason.min'                  => 'Alasan koreksi minimal 3 karakter.',
        ];
    }
}
