<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateUserRequest extends FormRequest
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
     * Menyiapkan dan memanipulasi data sebelum proses validasi berjalan.
     * * @return void
     */
    protected function prepareForValidation(): void
    {
        // Memastikan is_active selalu memiliki nilai (1 atau 0) sebelum divalidasi
        $this->merge([
            'is_active' => $this->has('is_active') ? 1 : 0,
        ]);
    }

    /**
     * Mendapatkan aturan validasi yang berlaku untuk request ini.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $userId = $this->route('user')->id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($userId)],
            'password' => ['nullable', 'confirmed', Password::defaults()], // Password opsional saat update
            'role' => ['required', 'in:admin,kasir,driver'],
            'phone' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string'],
            // Kata 'sometimes' bisa dihapus karena nilainya sudah pasti ada berkat prepareForValidation
            'is_active' => ['boolean'],
            'transaction_limit' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
