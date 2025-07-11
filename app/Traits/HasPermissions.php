<?php

namespace App\Traits;

use App\Models\Permission;

trait HasPermissions
{
    public function permissions()
    {
        return $this->belongsToMany(Permission::class, 'user_permissions');
    }

    /**
     * Cek apakah user memiliki permission tertentu
     *
     * @param string $permissionName
     * @return bool
     */
    public function hasPermission(string $permissionName): bool
    {
        // Cek dari field 'role' (admin punya semua akses)
        if ($this->role === 'admin') {
            return true;
        }
        
        // Cek dari permission yang terhubung langsung
        if ($this->permissions()->where('name', $permissionName)->exists()) {
            return true;
        }
        
        // Cek dari permission yang didapat dari role
        if (method_exists($this, 'roles')) {
            foreach ($this->roles as $role) {
                if ($role->permissions()->where('name', $permissionName)->exists()) {
                    return true;
                }
            }
        }

        // Cek dari kolom JSON 'permissions' di tabel user
        $userPermissions = json_decode($this->attributes['permissions'] ?? '[]', true);
        
        if (is_array($userPermissions) && in_array($permissionName, $userPermissions)) {
            return true;
        }

        return false;
    }

    /**
     * Cek apakah user memiliki salah satu dari beberapa permission
     *
     * @param array $permissions
     * @return bool
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
     * Cek apakah user memiliki semua permission yang diberikan
     *
     * @param array $permissions
     * @return bool
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