<?php

namespace App\Services\Kasir;

use App\Models\Product;
use App\Models\Customer;
use App\Models\Transaction;
use App\Exceptions\InsufficientStockException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class PosService
{
    /**
     * Mengambil data yang diperlukan untuk antarmuka POS.
     *
     * @return array
     */
    public function getPosData(): array
    {
        // Hanya ambil produk yang aktif dan memiliki stok
        $products = Product::where('is_active', true)
            ->whereHas('inventory', function ($query) {
                $query->where('current_stock', '>', 0);
            })
            ->with('category', 'inventory')
            ->get();
            
        $customers = Customer::where('status', 'active')->get();

        return [
            'products' => $products,
            'customers' => $customers,
        ];
    }

    /**
     * Memproses dan menyimpan transaksi baru dari POS.
     *
     * @param array $data
     * @return Transaction
     * @throws InsufficientStockException|\Exception
     */
    public function processTransaction(array $data): Transaction
    {
        return DB::transaction(function () use ($data) {
            // 1. Validasi stok sebelum membuat transaksi
            foreach ($data['items'] as $item) {
                $product = Product::with('inventory')->find($item['product_id']);
                if ($product->inventory->current_stock < $item['quantity']) {
                    throw new InsufficientStockException(
                        "Stok untuk produk '{$product->name}' tidak mencukupi."
                    );
                }
            }
            
            // 2. Buat transaksi utama
            $transaction = Transaction::create([
                'type' => 'sale',
                'customer_id' => $data['customer_id'] ?? null,
                'total_amount' => $data['total_amount'],
                'payment_method' => $data['payment_method'],
                'payment_status' => 'paid', // Asumsi langsung lunas
                'status' => 'completed',
                'transaction_date' => now(),
                'notes' => $data['notes'] ?? null,
                'created_by' => Auth::id(),
            ]);

            // 3. Simpan detail transaksi dan kurangi stok
            foreach ($data['items'] as $item) {
                $product = Product::find($item['product_id']);
                
                $transaction->details()->create([
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'total_price' => $item['total_price'],
                ]);
                
                // Kurangi stok inventaris
                $product->inventory->decrement('current_stock', $item['quantity']);
            }

            return $transaction;
        });
    }
}