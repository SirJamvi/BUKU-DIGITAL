<?php

namespace App\Services\Admin;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Pagination\LengthAwarePaginator;

class UserService
{
    /**
     * Mendapatkan semua user DARI BISNIS SAAT INI dengan paginasi.
     */
    public function getAllUsersWithPagination(int $perPage = 15): LengthAwarePaginator
    {
        // Mengambil business_id dari user (admin) yang sedang login
        $businessId = Auth::user()->business_id;

        // INI PERBAIKANNYA: Mengambil semua user yang memiliki business_id yang sama
        return User::where('business_id', $businessId)
                   ->latest()
                   ->paginate($perPage);
    }

    /**
     * Membuat user baru UNTUK BISNIS SAAT INI.
     */
    public function createUser(array $data): User
    {
        $data['password'] = Hash::make($data['password']);
        
        // INI PERBAIKANNYA: Otomatis mengaitkan user baru dengan bisnis admin saat ini
        $data['business_id'] = Auth::user()->business_id;
        // Set user yang membuat (jika perlu untuk audit)
        $data['created_by'] = Auth::id();

        return User::create($data);
    }

    /**
     * Memperbarui data user.
     */
    public function updateUser(User $user, array $data): User
    {
        // Keamanan: Pastikan admin hanya bisa mengedit user di bisnisnya sendiri
        if ($user->business_id !== Auth::user()->business_id) {
            abort(403, 'AKSES DITOLAK.');
        }

        if (!empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        $user->update($data);
        return $user;
    }

    /**
     * Menghapus user.
     */
    public function deleteUser(User $user): void
    {
        if ($user->id === Auth::id()) {
            throw new \Exception("Anda tidak dapat menghapus akun Anda sendiri.");
        }
        
        // Keamanan: Pastikan hanya bisa menghapus user dari bisnis yang sama
        if ($user->business_id !== Auth::user()->business_id) {
            abort(403, 'AKSES DITOLAK.');
        }

        $user->delete();
    }
}