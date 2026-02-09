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
        $businessId = Auth::user()->business_id;

        return User::where('business_id', $businessId)
                   ->latest()
                   ->paginate($perPage);
    }

    /**
     * Membuat user baru.
     */
    public function createUser(array $data): User
    {
        // Hash password
        $data['password'] = Hash::make($data['password']);
        
        // Otomatis set business_id dari admin yang login
        $data['business_id'] = Auth::user()->business_id;

        // Validasi tambahan: Pastikan role valid (double check)
        // Default ke 'kasir' jika aneh-aneh, tapi harusnya sudah dihandle Request
        if (!in_array($data['role'], ['admin', 'kasir', 'driver'])) {
            $data['role'] = 'kasir';
        }

        // Create user
        // Note: Karena $data berasal dari validated(), 'slug' tidak akan ada di sini
        return User::create($data);
    }

    /**
     * Memperbarui user.
     */
    public function updateUser(User $user, array $data): User
    {
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

    public function deleteUser(User $user): void
    {
        if ($user->id === Auth::id()) {
            throw new \Exception("Anda tidak dapat menghapus akun Anda sendiri.");
        }
        
        if ($user->business_id !== Auth::user()->business_id) {
            abort(403, 'AKSES DITOLAK.');
        }

        $user->delete();
    }
}