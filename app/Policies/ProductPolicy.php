<?php

namespace App\Policies;

use App\Models\Product;
use App\Models\User;

class ProductPolicy
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
     * Tentukan apakah pengguna dapat melihat daftar produk.
     */
    public function viewAny(User $user): bool
    {
        // Semua pengguna yang login (admin & kasir) bisa melihat daftar produk.
        return true;
    }

    /**
     * Tentukan apakah pengguna dapat melihat detail produk.
     */
    public function view(User $user, Product $product): bool
    {
        return true;
    }

    /**
     * Tentukan apakah pengguna dapat membuat produk baru.
     */
    public function create(User $user): bool
    {
        return false; // Hanya admin, ditangani oleh 'before'.
    }

    /**
     * Tentukan apakah pengguna dapat memperbarui data produk.
     */
    public function update(User $user, Product $product): bool
    {
        return false; // Hanya admin, ditangani oleh 'before'.
    }

    /**
     * Tentukan apakah pengguna dapat menghapus produk.
     */
    public function delete(User $user, Product $product): bool
    {
        return false; // Hanya admin, ditangani oleh 'before'.
    }
}