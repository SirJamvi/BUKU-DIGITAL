<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
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
     * Tentukan apakah pengguna dapat melihat daftar pengguna.
     */
    public function viewAny(User $user): bool
    {
        return $user->role === 'admin';
    }

    /**
     * Tentukan apakah pengguna dapat melihat detail pengguna lain.
     */
    public function view(User $user, User $model): bool
    {
        // Pengguna bisa melihat profilnya sendiri atau jika dia adalah admin.
        return $user->id === $model->id;
    }

    /**
     * Tentukan apakah pengguna dapat membuat pengguna baru.
     */
    public function create(User $user): bool
    {
        return $user->role === 'admin';
    }

    /**
     * Tentukan apakah pengguna dapat memperbarui data pengguna lain.
     */
    public function update(User $user, User $model): bool
    {
        // Pengguna hanya bisa mengedit profilnya sendiri. Admin ditangani oleh 'before'.
        return $user->id === $model->id;
    }

    /**
     * Tentukan apakah pengguna dapat menghapus pengguna lain.
     */
    public function delete(User $user, User $model): bool
    {
        // Pengguna tidak bisa menghapus dirinya sendiri.
        if ($user->id === $model->id) {
            return false;
        }
        return $user->role === 'admin';
    }
}