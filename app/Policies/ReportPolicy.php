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
        if ($user->hasRole('admin')) {
            return true;
        }
        return null;
    }

    /**
     * Tentukan apakah pengguna dapat melihat laporan penjualan.
     */
    public function viewSales(User $user): bool
    {
        // Kasir (termasuk yang merangkap kasir) boleh melihat laporan penjualan mereka sendiri.
        return $user->hasRole('kasir');
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