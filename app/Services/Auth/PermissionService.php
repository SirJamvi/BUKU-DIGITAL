<?php

namespace App\Services\Auth;

use App\Models\Permission;
use Illuminate\Support\Collection;

class PermissionService
{
    /**
     * Mendapatkan semua izin, dikelompokkan berdasarkan modul.
     *
     * @return Collection
     */
    public function getAllPermissionsGroupedByModule(): Collection
    {
        return Permission::where('is_active', true)->get()->groupBy('module');
    }

    /**
     * Membuat izin baru.
     *
     * @param array $data
     * @return Permission
     */
    public function createPermission(array $data): Permission
    {
        return Permission::create($data);
    }

    /**
     * Memperbarui data izin.
     *
     * @param Permission $permission
     * @param array $data
     * @return Permission
     */
    public function updatePermission(Permission $permission, array $data): Permission
    {
        $permission->update($data);
        return $permission;
    }
}