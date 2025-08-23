<?php

namespace App\Policies;

use App\Models\CashFlow;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class CashFlowPolicy
{
    /**
     * Tentukan apakah pengguna dapat memperbarui model.
     */
    public function update(User $user, CashFlow $cashFlow): bool
    {
        // Izinkan jika business_id pada data pengeluaran sama dengan business_id pengguna
        return $user->business_id === $cashFlow->business_id;
    }

    /**
     * Tentukan apakah pengguna dapat menghapus model.
     */
    public function delete(User $user, CashFlow $cashFlow): bool
    {
        // Gunakan logika yang sama dengan update
        return $user->business_id === $cashFlow->business_id;
    }
}