<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class StoreAdminDepositRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check();
    }

    public function rules(): array
    {
        $businessId = (int) (Auth::user()?->business_id ?? 0);

        return [
            'customer_id' => [
                'required',
                'integer',
                Rule::exists('customers', 'id')->where(fn ($q) => $q->where('business_id', $businessId)),
            ],
            'branch_id' => [
                'nullable',
                'integer',
                Rule::exists('branches', 'id')->where(fn ($q) => $q->where('business_id', $businessId)),
            ],
            'deposit_mode' => ['required', 'string', 'in:opening_balance,cash_owner,cash_drawer,transfer-bank,dana'],
            'cashier_id'   => [
                'nullable',
                'required_if:deposit_mode,cash_drawer',
                'integer',
                Rule::exists('users', 'id')->where(fn ($q) => $q->where('business_id', $businessId)),
            ],
            'amount' => ['required', 'numeric', 'min:1'],
            'date'   => ['nullable', 'date'],
            'notes'  => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'customer_id.required'    => 'Pilih pelanggan terlebih dahulu.',
            'customer_id.exists'      => 'Pelanggan tidak ditemukan dalam bisnis ini.',
            'deposit_mode.required'   => 'Pilih mode penerimaan deposit.',
            'deposit_mode.in'         => 'Mode penerimaan deposit tidak valid.',
            'cashier_id.required_if'  => 'Pilih kasir tujuan jika setoran tunai dimasukkan ke laci kasir.',
            'amount.required'         => 'Nominal deposit wajib diisi.',
            'amount.min'              => 'Nominal deposit minimal Rp 1.',
        ];
    }
}
