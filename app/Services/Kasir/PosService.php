<?php

namespace App\Services\Kasir;

use App\Models\Product;
use App\Models\Customer;
use App\Models\Transaction;
use App\Models\CashFlow;
use App\Exceptions\InsufficientStockException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class PosService
{
    /**
     * Mengambil data yang diperlukan untuk antarmuka POS.
     */
    public function getPosData(): array
    {
        // Global Scope sudah otomatis memfilter produk dan pelanggan
        // berdasarkan business_id dari kasir yang login.
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
                'business_id' => Auth::user()->business_id,
                'type' => 'sale',
                'customer_id' => $data['customer_id'] ?? null,
                'total_amount' => $data['total_amount'],
                'payment_method' => $data['payment_method'],
                'payment_status' => 'paid',
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
                
                $product->inventory->decrement('current_stock', $item['quantity']);
            }

            // 4. Catat transaksi sebagai Pemasukan (Income)
            CashFlow::create([
                'business_id' => $transaction->business_id,
                'type' => 'income',
                'category_id' => 1, // Ganti dengan ID kategori "Penjualan Produk" Anda
                'amount' => $transaction->total_amount,
                'description' => 'Pendapatan dari penjualan #' . $transaction->id,
                'date' => $transaction->transaction_date,
                'reference_id' => $transaction->id,
                'created_by' => Auth::id(),
            ]);

            // ====================================================================
            // PERBAIKAN BARU: Update total belanja pelanggan jika ada
            // ====================================================================
            if ($transaction->customer_id) {
                $customer = Customer::find($transaction->customer_id);
                if ($customer) {
                    $customer->increment('total_purchases', $transaction->total_amount);
                }
            }
            // ====================================================================

            return $transaction;
        });
    }

    /**
     * Get transaction with all related data for receipt.
     */
    public function getTransactionWithDetails(int $transactionId): Transaction
    {
        // findOrFail sudah otomatis di-scope oleh trait BelongsToBusiness
        return Transaction::with([
            'details.product',
            'customer',
            'createdBy'
        ])->findOrFail($transactionId);
    }
}