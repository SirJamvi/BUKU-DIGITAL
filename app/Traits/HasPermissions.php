<?php

namespace App\Traits;

trait HasPermissions
{
    /**
     * Cek apakah user memiliki permission tertentu.
     *
     * @param string $permissionName (contoh: "products.read")
     * @return bool
     */
    public function hasPermission(string $permissionName): bool
    {
        // 1. Admin selalu memiliki semua akses.
        if ($this->hasRole('admin')) {
            return true;
        }

        // 2. Cek izin dari role yang dimiliki user.
        // Fungsi ini bergantung pada method `roles()` yang disediakan oleh trait `HasRoles`.
        if (method_exists($this, 'roles')) {
            // Memecah nama izin menjadi modul dan aksi.
            // contoh: "products.read" -> $module="products", $action="read"
            @list($module, $action) = explode('.', $permissionName, 2);

            if (!$module || !$action) {
                return false; // Format permission tidak valid.
            }

            foreach ($this->roles as $role) {
                $rolePermissions = $role->permissions ?? [];
                // Cek apakah modul ada dan aksi diizinkan di dalam array modul tersebut.
                if (isset($rolePermissions[$module]) && in_array($action, $rolePermissions[$module])) {
                    return true;
                }
            }
        }
        
        return false;
    }

    /**
     * Cek apakah user memiliki salah satu dari beberapa permission.
     */
    public function hasAnyPermission(array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if ($this->hasPermission($permission)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Cek apakah user memiliki semua permission yang diberikan.
     */
    public function hasAllPermissions(array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if (!$this->hasPermission($permission)) {
                return false;
            }
        }
        return true;
    }
}