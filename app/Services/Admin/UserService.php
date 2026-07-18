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
        $data['password'] = Hash::make($data['password']);
        $data['business_id'] = Auth::user()->business_id;

        if (!in_array($data['role'], ['admin', 'kasir', 'driver'])) {
            $data['role'] = 'kasir';
        }

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

    /**
     * Membalikkan (toggle) status aktif/nonaktif user.
     */
    public function toggleStatus(User $user): User
    {
        // Pastikan admin hanya bisa mengubah user dari bisnisnya sendiri
        if ($user->business_id !== Auth::user()->business_id) {
            abort(403, 'AKSES DITOLAK.');
        }

        // Mencegah user menonaktifkan dirinya sendiri (double protection, selain di view)
        if ($user->id === Auth::id()) {
            abort(403, 'Anda tidak dapat mengubah status akun Anda sendiri.');
        }

        $user->update(['is_active' => !$user->is_active]);

        return $user;
    }
}