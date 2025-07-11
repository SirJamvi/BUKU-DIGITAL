<?php

namespace App\Policies;

use App\Models\Inventory;
use App\Models\User;

class InventoryPolicy
{
    /**
     * Memberikan semua akses kepada admin.
     */
    public function before(User $user, string $ability): bool|null
    {
        if ($user->role === 'admin') {
            return true;
        }
        return null;
    }

    /**
     * Tentukan apakah pengguna dapat melihat data inventaris.
     */
    public function viewAny(User $user): bool
    {
        // Kasir juga boleh melihat daftar stok (view only).
        return true;
    }

    /**
     * Tentukan apakah pengguna dapat melakukan stock opname atau penyesuaian.
     */
    public function opname(User $user): bool
    {
        return false; // Hanya admin.
    }

    /**
     * Tentukan apakah pengguna dapat memperbarui data stok.
     */
    public function update(User $user, Inventory $inventory): bool
    {
        return false; // Hanya admin.
    }
}