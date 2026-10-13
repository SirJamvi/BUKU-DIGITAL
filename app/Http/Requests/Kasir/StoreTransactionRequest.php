<?php

namespace App\Http\Requests\Kasir;

use Illuminate\Foundation\Http\FormRequest;

class StoreTransactionRequest extends FormRequest
{
    /**
     * Tentukan apakah pengguna berwenang untuk membuat request ini.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        // Kasir diizinkan membuat transaksi sesuai SOP.
        return true;
    }

    /**
     * Mendapatkan aturan validasi yang berlaku untuk request ini.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        if ($this->has('orders')) {
            return [
                'driver_id' => ['required', 'integer', 'exists:users,id'],
                'orders' => ['required', 'array', 'min:1'],
                'orders.*.customer_id' => ['nullable', 'integer', 'exists:customers,id'],
                'orders.*.total_amount' => ['required', 'numeric', 'min:0'],
                'orders.*.notes' => ['nullable', 'string'],
                'orders.*.items' => ['required', 'array', 'min:1'],
                'orders.*.items.*.product_id' => ['required', 'integer', 'exists:products,id'],
                'orders.*.items.*.product_variant_id' => ['nullable'],
                'orders.*.items.*.quantity' => ['required', 'integer', 'min:1'],
                'orders.*.items.*.unit_price' => ['required', 'numeric', 'min:0'],
                'orders.*.items.*.total_price' => ['required', 'numeric', 'min:0'],
            ];
        }

        return [
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'driver_id' => ['nullable', 'integer', 'exists:users,id'],
            'payment_method' => ['required', 'string', 'max:50'],
            'total_amount' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.product_variant_id' => ['nullable'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.total_price' => ['required', 'numeric', 'min:0'],
        ];
    }

    /**
     * Menyiapkan data untuk validasi.
     *
     * @return void
     */
    protected function prepareForValidation()
    {
        if ($this->has('orders')) {
            $orders = $this->orders;
            foreach ($orders as $index => $order) {
                $orderTotal = 0;
                if (!empty($order['items']) && is_array($order['items'])) {
                    $orderTotal = collect($order['items'])->sum(function ($item) {
                        return ($item['unit_price'] ?? 0) * ($item['quantity'] ?? 0);
                    });
                }
                $orders[$index]['total_amount'] = $orderTotal;
            }
            $this->merge([
                'orders' => $orders,
                'payment_method' => 'kasbon',
            ]);
            return;
        }

        // Single order
        $totalAmount = 0;
        if ($this->has('items')) {
            $totalAmount = collect($this->items)->sum(function ($item) {
                return ($item['unit_price'] ?? 0) * ($item['quantity'] ?? 0);
            });
        }

        $this->merge([
            'total_amount' => $totalAmount,
        ]);
    }
}
