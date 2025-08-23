<?php

namespace App\Services\Kasir;

use App\Models\Transaction;
use App\Models\Product;
use App\Services\Kasir\PosService;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TransactionService
{
    /**
     * Mendapatkan riwayat transaksi berdasarkan ID kasir untuk bisnis saat ini.
     */
    public function getTransactionsByKasir(int $kasirId, int $perPage = 15): LengthAwarePaginator
    {
        // Global Scope sudah otomatis memfilter berdasarkan business_id.
        // Query ini hanya menambahkan filter spesifik untuk kasir yang login.
        return Transaction::where('created_by', $kasirId)
            ->with('customer')
            ->latest('transaction_date')
            ->paginate($perPage);
    }

    /**
     * Mendapatkan detail lengkap dari sebuah transaksi.
     */
    public function getTransactionDetails(Transaction $transaction): array
    {
        // Global scope sudah memastikan transaksi ini milik bisnis yang benar.
        $transaction->load('customer', 'createdBy', 'details.product');

        return [
            'transaction' => $transaction,
        ];
    }

    /**
     * [BARU] Mengambil data yang diperlukan untuk halaman edit transaksi.
     */
    public function getEditTransactionData(Transaction $transaction): array
    {
        $posData = app(PosService::class)->getPosData();
        $posData['transaction'] = $transaction->load('details');
        return $posData;
    }

    /**
     * [BARU] Logika inti untuk memperbarui transaksi.
     */
    public function updateTransaction(Transaction $transaction, array $data): Transaction
    {
        return DB::transaction(function () use ($transaction, $data) {
            // 1. Kembalikan stok dari item-item lama ke inventaris
            foreach ($transaction->details as $oldDetail) {
                if ($oldDetail->product && $oldDetail->product->inventory) {
                    $oldDetail->product->inventory->increment('current_stock', $oldDetail->quantity);
                }
            }
            // Hapus detail transaksi yang lama
            $transaction->details()->delete();

            // 2. Proses item baru: kurangi stok & buat detail baru
            foreach ($data['items'] as $item) {
                $product = Product::findOrFail($item['product_id']);
                
                // Pastikan stok cukup untuk item baru
                if ($product->inventory->current_stock < $item['quantity']) {
                    throw new \Exception("Stok untuk produk '{$product->name}' tidak mencukupi.");
                }
                
                // Kurangi stok inventaris
                $product->inventory->decrement('current_stock', $item['quantity']);

                // Buat detail transaksi yang baru
                $transaction->details()->create($item);
            }

            // 3. Perbarui data transaksi utama
            $transaction->update([
                'total_amount' => $data['total_amount'],
                'customer_id' => $data['customer_id'] ?? null,
                'payment_method' => $data['payment_method'],
                'notes' => $data['notes'] ?? null,
            ]);

            return $transaction;
        });
    }
}