<?php

namespace App\Http\Requests\Kasir;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Validator;

class CompleteDeliveryTripRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check();
    }

    public function rules(): array
    {
        return [
            'trip_code'               => ['required', 'string', 'max:100'],
            'payments'                => ['required', 'array'],
            'payments.*'              => ['required', 'string', 'max:50'],
            'order_settled'           => ['nullable', 'array'],
            'order_settled.*'         => ['nullable', 'in:0,1'],
            'settled_kasbons'         => ['nullable', 'array'],
            'settled_kasbons.*'       => ['nullable', 'array'],
            'settled_kasbons.*.*'     => ['integer', 'exists:transactions,id'],
            'deposits'                => ['nullable', 'array'],
            'deposits.*'              => ['nullable', 'numeric', 'min:0'],
            'settlement_date_modes'   => ['nullable', 'array'],
            'settlement_date_modes.*' => ['nullable', 'string', 'in:today,nota_date'],
            'notes'                   => ['nullable', 'array'],
            'notes.*'                 => ['nullable', 'string', 'max:255'],
            'payment_proofs'          => ['nullable', 'array'],
            'payment_proofs.*'        => ['nullable', 'file', 'mimes:jpeg,png,jpg,webp', 'max:10240'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                $payments = $this->input('payments', []);
                if (!is_array($payments)) {
                    return;
                }

                foreach ($payments as $trxId => $rawMethod) {
                    $slug = Str::slug((string) $rawMethod);
                    if (in_array($slug, ['cash', 'kasbon', 'deposit'], true)) {
                        continue;
                    }

                    $hasOrderPaid = ((string) $this->input("order_settled.{$trxId}", '1')) === '1';
                    $oldKasbons = $this->input("settled_kasbons.{$trxId}", []);
                    $hasOldKb = is_array($oldKasbons) && count(array_filter($oldKasbons)) > 0;
                    $hasDep = (float) $this->input("deposits.{$trxId}", 0) > 0;

                    if (($hasOrderPaid || $hasOldKb || $hasDep) && !$this->hasFile("payment_proofs.{$trxId}")) {
                        $validator->errors()->add(
                            "payment_proofs.{$trxId}",
                            'Bukti pembayaran / transfer WhatsApp wajib diunggah untuk metode non-tunai (' . strtoupper($slug) . ').'
                        );
                    }
                }
            },
        ];
    }
}
