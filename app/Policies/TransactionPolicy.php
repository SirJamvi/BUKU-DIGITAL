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
        // PERBAIKAN: Jika pengguna adalah admin, selalu izinkan.
        if ($user->role === 'admin') {
            return true;
        }

        // Jika bukan admin (kasir), terapkan aturan lama.
        return $user->id === $transaction->created_by && 
               $transaction->created_at->gt(now()->subMinutes(15));
    }
}