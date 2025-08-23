<?php

namespace App\Policies;

use App\Models\Transaction;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class TransactionPolicy
{
    /**
     * Tentukan apakah pengguna dapat memperbarui model.
     */
    public function update(User $user, Transaction $transaction): bool
    {
        // Izinkan jika:
        // 1. Pengguna adalah pembuat transaksi.
        // 2. Transaksi dibuat dalam 15 menit terakhir.
        return $user->id === $transaction->created_by && 
               $transaction->created_at->gt(now()->subMinutes(15));
    }
}