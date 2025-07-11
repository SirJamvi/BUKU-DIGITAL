<?php

namespace App\Services\Admin;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Pagination\LengthAwarePaginator;

class UserService
{
    /**
     * Mendapatkan semua user dengan paginasi.
     *
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getAllUsersWithPagination(int $perPage = 15): LengthAwarePaginator
    {
        return User::latest()->paginate($perPage);
    }

    /**
     * Membuat user baru.
     *
     * @param array $data
     * @return User
     */
    public function createUser(array $data): User
    {
        $data['password'] = Hash::make($data['password']);
        return User::create($data);
    }

    /**
     * Memperbarui data user.
     *
     * @param User $user
     * @param array $data
     * @return User
     */
    public function updateUser(User $user, array $data): User
    {
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
     *
     * @param User $user
     * @return void
     * @throws \Exception
     */
    public function deleteUser(User $user): void
    {
        // Pastikan tidak bisa menghapus diri sendiri
        $currentUserId = Auth::id();
        
        if ($user->id === $currentUserId) {
            throw new \Exception("Anda tidak dapat menghapus akun Anda sendiri.");
        }
        
        $user->delete();
    }

    /**
     * Mendapatkan user berdasarkan ID.
     *
     * @param int $id
     * @return User|null
     */
    public function getUserById(int $id): ?User
    {
        return User::find($id);
    }

    /**
     * Mendapatkan user berdasarkan email.
     *
     * @param string $email
     * @return User|null
     */
    public function getUserByEmail(string $email): ?User
    {
        return User::where('email', $email)->first();
    }

    /**
     * Mengubah status user (aktif/nonaktif).
     *
     * @param User $user
     * @param bool $status
     * @return User
     */
    public function toggleUserStatus(User $user, bool $status): User
    {
        $user->update(['is_active' => $status]);
        return $user;
    }

    /**
     * Mencari user berdasarkan kata kunci.
     *
     * @param string $keyword
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function searchUsers(string $keyword, int $perPage = 15): LengthAwarePaginator
    {
        return User::where('name', 'like', "%{$keyword}%")
                   ->orWhere('email', 'like', "%{$keyword}%")
                   ->latest()
                   ->paginate($perPage);
    }
}