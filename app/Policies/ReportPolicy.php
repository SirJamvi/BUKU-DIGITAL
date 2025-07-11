<?php

namespace App\Policies;

use App\Models\User;

class ReportPolicy
{
    /**
     * Memberikan semua akses laporan kepada admin.
     */
    public function before(User $user, string $ability): bool|null
    {
        if ($user->role === 'admin') {
            return true;
        }
        return null;
    }

    /**
     * Tentukan apakah pengguna dapat melihat laporan penjualan.
     */
    public function viewSales(User $user): bool
    {
        // Kasir boleh melihat laporan penjualan mereka sendiri.
        return $user->role === 'kasir';
    }

    /**
     * Tentukan apakah pengguna dapat melihat laporan keuangan.
     */
    public function viewFinancial(User $user): bool
    {
        return false; // Hanya admin.
    }

    /**
     * Tentukan apakah pengguna dapat melihat laporan inventaris.
     */
    public function viewInventory(User $user): bool
    {
        return false; // Hanya admin.
    }
}