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
                'orders.*.customer_id' => ['nullable'],
                'orders.*.customer_name' => ['nullable', 'string', 'max:255'],
                'orders.*.customer_phone' => ['nullable', 'string', 'max:50'],
                'orders.*.customer_address' => ['nullable', 'string'],
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
            'customer_id' => ['nullable'],
            'customer_name' => ['nullable', 'string', 'max:255'],
            'customer_phone' => ['nullable', 'string', 'max:50'],
            'customer_address' => ['nullable', 'string'],
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
                if (empty($order['customer_id'])) {
                    $orders[$index]['customer_id'] = null;
                }
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

        $mergeData = [
            'total_amount' => $totalAmount,
            'customer_id' => $this->filled('customer_id') ? $this->customer_id : null,
        ];

        // Jika single order ditugaskan ke driver, otomatis payment_method kasbon
        if ($this->filled('driver_id')) {
            $mergeData['payment_method'] = 'kasbon';
        }

        $this->merge($mergeData);
    }

    /**
     * Konfigurasi validator setelah aturan dasar dijalankan.
     *
     * @param \Illuminate\Validation\Validator $validator
     * @return void
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $isMulti = $this->has('orders');
            $hasDriver = !empty($this->driver_id);
            $isKasbon = $hasDriver || (($this->payment_method ?? '') === 'kasbon');

            if ($isMulti) {
                $orders = $this->orders ?? [];
                foreach ($orders as $index => $order) {
                    $cId = $order['customer_id'] ?? null;
                    $cName = trim($order['customer_name'] ?? '');

                    if (!empty($cId)) {
                        $cust = \App\Models\Customer::find($cId);
                        if ($cust) {
                            $cName = trim($cust->name);
                        }
                    }

                    $isInvalid = empty($cName) || in_array(strtolower($cName), ['pelanggan umum', 'umum']);
                    if ($isInvalid) {
                        $validator->errors()->add(
                            "orders.{$index}.customer_id",
                            "Pesanan " . ($index + 1) . " pengiriman driver wajib mencantumkan Nama Toko / Pelanggan spesifik (tidak boleh kosong atau Pelanggan Umum)."
                        );
                    }
                }
            } else {
                if ($isKasbon) {
                    $cId = $this->customer_id;
                    $cName = trim($this->customer_name ?? '');

                    if (!empty($cId)) {
                        $cust = \App\Models\Customer::find($cId);
                        if ($cust) {
                            $cName = trim($cust->name);
                        }
                    }

                    $isInvalid = empty($cName) || in_array(strtolower($cName), ['pelanggan umum', 'umum']);
                    if ($isInvalid) {
                        $validator->errors()->add(
                            'customer_id',
                            'Transaksi Kasbon / Pengiriman Driver wajib mencantumkan Nama Toko atau Pelanggan spesifik (tidak boleh kosong atau Pelanggan Umum).'
                        );
                    }
                }
            }
        });
    }
}

