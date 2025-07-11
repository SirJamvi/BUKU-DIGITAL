<?php

namespace App\Policies;

use App\Models\Transaction;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class TransactionPolicy
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
     * Tentukan apakah pengguna dapat melihat daftar transaksi.
     */
    public function viewAny(User $user): bool
    {
        // Kasir hanya bisa melihat transaksinya sendiri, jadi 'viewAny' untuk semua data
        // hanya diizinkan untuk admin (sudah ditangani oleh 'before').
        return false;
    }

    /**
     * Tentukan apakah pengguna dapat melihat detail transaksi.
     */
    public function view(User $user, Transaction $transaction): bool
    {
        // Kasir hanya boleh melihat transaksi yang mereka buat.
        return $user->id === $transaction->created_by;
    }

    /**
     * Tentukan apakah pengguna dapat membuat transaksi baru.
     */
    public function create(User $user): bool
    {
        // Kasir diizinkan membuat transaksi.
        return $user->role === 'kasir';
    }

    /**
     * Tentukan apakah pengguna dapat memperbarui data transaksi.
     * Sesuai SOP, kasir memiliki akses 'update_own'.
     */
    public function update(User $user, Transaction $transaction): bool
    {
        // Izinkan update jika kasir adalah pemilik transaksi dan transaksi belum lama dibuat (misal: dalam 5 menit).
        if ($user->id === $transaction->created_by) {
            return $transaction->created_at->gt(now()->subMinutes(5));
        }
        return false;
    }

    /**
     * Tentukan apakah pengguna dapat menghapus (atau membatalkan) transaksi.
     */
    public function delete(User $user, Transaction $transaction): bool
    {
        return false; // Hanya admin yang boleh membatalkan/menghapus.
    }
}