<?php

namespace App\Services\Admin;

use App\Models\Transaction;
use Illuminate\Pagination\LengthAwarePaginator;

class TransactionService
{
    /**
     * Mendapatkan semua transaksi dengan paginasi untuk admin.
     */
    public function getAllTransactionsWithPagination(int $perPage = 15): LengthAwarePaginator
    {
        return Transaction::with(['customer', 'createdBy'])
            ->latest('transaction_date')
            ->paginate($perPage);
    }
}