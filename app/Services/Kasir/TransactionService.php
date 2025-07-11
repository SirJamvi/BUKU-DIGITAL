<?php

namespace App\Services\Kasir;

use App\Models\Transaction;
use Illuminate\Pagination\LengthAwarePaginator;

class TransactionService
{
    /**
     * Mendapatkan riwayat transaksi berdasarkan ID kasir dengan paginasi.
     *
     * @param int $kasirId
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getTransactionsByKasir(int $kasirId, int $perPage = 15): LengthAwarePaginator
    {
        return Transaction::where('created_by', $kasirId)
            ->with('customer')
            ->latest()
            ->paginate($perPage);
    }

    /**
     * Mendapatkan detail lengkap dari sebuah transaksi.
     *
     * @param Transaction $transaction
     * @return array
     */
    public function getTransactionDetails(Transaction $transaction): array
    {
        // Memuat relasi untuk ditampilkan di view
        $transaction->load('customer', 'createdBy', 'details.product');

        return [
            'transaction' => $transaction,
        ];
    }
}