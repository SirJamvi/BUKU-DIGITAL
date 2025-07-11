<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class FundAllocationRequest extends FormRequest
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
     * Mendapatkan aturan validasi yang berlaku untuk request ini.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'settings' => ['required', 'array'],
            'settings.*.id' => ['required', 'integer', 'exists:fund_allocation_settings,id'],
            'settings.*.percentage' => ['required', 'numeric', 'min:0', 'max:100'],
            // Validasi kustom untuk memastikan total tidak lebih dari 100%
            'settings' => [
                function ($attribute, $value, $fail) {
                    $totalPercentage = collect($value)->sum('percentage');
                    if ($totalPercentage > 100) {
                        $fail('Total persentase alokasi tidak boleh melebihi 100%.');
                    }
                }
            ]
        ];
    }
}