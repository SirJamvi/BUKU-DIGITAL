<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class ReverseDepositRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check();
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:3', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'reason.required' => 'Alasan pembatalan deposit wajib diisi untuk jejak audit finance.',
            'reason.min'      => 'Alasan pembatalan minimal 3 karakter.',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        Log::warning('[DEPOSIT_DEBUG] ADMIN_REVERSE_VALIDATION_FAILED', [
            'user_id'        => Auth::id(),
            'business_id'    => Auth::user()?->business_id,
            'deposit_log_id' => $this->route('depositLog'),
            'input'          => $this->except(['_token']),
            'errors'         => $validator->errors()->toArray(),
        ]);

        parent::failedValidation($validator);
    }
}
