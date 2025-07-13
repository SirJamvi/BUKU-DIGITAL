<?php

namespace App\Services\Kasir;

use App\Models\Transaction;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;

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
}