<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

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

    protected function failedValidation(Validator $validator): void
    {
        Log::warning('[DEPOSIT_DEBUG] ADMIN_CORRECT_VALIDATION_FAILED', [
            'user_id'        => Auth::id(),
            'business_id'    => Auth::user()?->business_id,
            'deposit_log_id' => $this->route('depositLog'),
            'input'          => $this->except(['_token']),
            'errors'         => $validator->errors()->toArray(),
        ]);

        parent::failedValidation($validator);
    }
}
