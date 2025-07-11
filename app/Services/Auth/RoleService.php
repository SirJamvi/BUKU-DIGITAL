<?php

namespace App\Services\Auth;

use App\Models\Role;
use Illuminate\Support\Collection;

class RoleService
{
    /**
     * Mendapatkan semua role.
     *
     * @return Collection
     */
    public function getAllRoles(): Collection
    {
        return Role::where('is_active', true)->get();
    }

    /**
     * Membuat role baru.
     *
     * @param array $data
     * @return Role
     */
    public function createRole(array $data): Role
    {
        return Role::create($data);
    }

    /**
     * Memperbarui data role.
     *
     * @param Role $role
     * @param array $data
     * @return Role
     */
    public function updateRole(Role $role, array $data): Role
    {
        $role->update($data);

        if (isset($data['permissions'])) {
            $role->permissions()->sync($data['permissions']);
        }

        return $role;
    }

    /**
     * Menghapus role.
     *
     * @param Role $role
     * @return void
     * @throws \Exception
     */
    public function deleteRole(Role $role): void
    {
        // Cegah penghapusan role jika masih ada pengguna yang menggunakannya
        if ($role->users()->exists()) {
            throw new \Exception("Role tidak dapat dihapus karena masih digunakan oleh pengguna.");
        }

        $role->delete();
    }
}