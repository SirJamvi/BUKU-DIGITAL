<?php

namespace App\Traits;

use App\Models\Role;
use App\Models\Permission;

trait HasRoles
{
    public function roles()
    {
        return $this->belongsToMany(Role::class, 'user_roles');
    }

    public function hasRole($roleName)
    {
        if (is_string($roleName)) {
            return $this->roles->contains('name', $roleName) || $this->role === $roleName;
        }

        foreach ($roleName as $r) {
            if ($this->hasRole($r)) {
                return true;
            }
        }

        return false;
    }

    protected function getPermissions()
    {
        // Mendapatkan izin dari peran
        $permissions = $this->roles->flatMap(function ($role) {
            return is_array($role->permissions) ? $role->permissions : [];
        })->unique();

        // Menggabungkan dengan izin langsung pengguna (jika ada)
        if (is_array($this->permissions)) {
            $permissions = $permissions->merge($this->permissions)->unique();
        }

        return $permissions;
    }
}
