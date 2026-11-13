<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class StoreBranchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check() && Auth::user()->isAdmin();
    }

    public function rules(): array
    {
        $businessId = Auth::user()->business_id;

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('branches', 'name')->where('business_id', $businessId),
            ],
            'code' => [
                'required',
                'string',
                'regex:/^[A-Z0-9-]{2,20}$/',
                Rule::unique('branches', 'code')->where('business_id', $businessId),
            ],
            'address' => [
                'nullable',
                'string',
                'max:1000',
            ],
            'phone' => [
                'nullable',
                'string',
                'max:25',
            ],
            'manager_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where(function ($query) use ($businessId) {
                    $query->where('business_id', $businessId);
                }),
            ],
            'is_active' => [
                'nullable',
                'boolean',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Nama cabang wajib diisi.',
            'name.unique' => 'Nama cabang sudah digunakan di bisnis ini.',
            'code.required' => 'Kode cabang wajib diisi.',
            'code.regex' => 'Kode cabang harus berupa 2-20 karakter alfanumerik kapital atau tanda hubung (contoh: CB-01, UTAMA, JKT-02).',
            'code.unique' => 'Kode cabang sudah digunakan di bisnis ini.',
            'manager_id.exists' => 'Manajer yang dipilih tidak valid untuk bisnis ini.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('code')) {
            $this->merge([
                'code' => strtoupper(trim((string) $this->code)),
            ]);
        }
        if ($this->has('is_active')) {
            $this->merge([
                'is_active' => filter_var($this->is_active, FILTER_VALIDATE_BOOLEAN),
            ]);
        }
    }
}

