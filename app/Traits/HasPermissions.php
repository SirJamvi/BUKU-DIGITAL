<?php

namespace App\Traits;

use App\Models\Permission;

trait HasPermissions
{
    public function permissions()
    {
        return $this->belongsToMany(Permission::class, 'user_permissions');
    }

    public function hasPermission($permissionName)
    {
        // Cek dari field 'role' (admin punya semua akses)
        if ($this->role === 'admin') {
            return true;
        }
        
        // Cek dari permission yang terhubung langsung
        if ($this->permissions->contains('name', $permissionName)) {
            return true;
        }
        
        // Cek dari permission yang didapat dari role
        foreach ($this->roles as $role) {
            if ($role->permissions->contains('name', $permissionName)) {
                return true;
            }
        }

        // Cek dari kolom JSON 'permissions' di tabel user
        $userPermissions = json_decode($this->attributes['permissions'] ?? '[]', true);
        
        if (in_array($permissionName, $userPermissions)) {
            return true;
        }

        return false;
    }
}