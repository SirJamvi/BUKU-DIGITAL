<?php

namespace App\Traits;

use App\Models\Role;

trait HasRoles
{
    public function roles()
    {
        return $this->belongsToMany(Role::class, 'user_roles');
    }

    /**
     * Cek apakah user memiliki role tertentu
     *
     * @param string|array $roleName
     * @return bool
     */
    public function hasRole($roleName): bool
    {
        if (is_string($roleName)) {
            // Cek dari field 'role' langsung
            if ($this->role === $roleName) {
                return true;
            }
            
            // Cek dari relasi roles
            return $this->roles()->where('name', $roleName)->exists();
        }

        // Jika array, cek salah satu
        if (is_array($roleName)) {
            foreach ($roleName as $r) {
                if ($this->hasRole($r)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Cek apakah user memiliki semua role yang diberikan
     *
     * @param array $roles
     * @return bool
     */
    public function hasAllRoles(array $roles): bool
    {
        foreach ($roles as $role) {
            if (!$this->hasRole($role)) {
                return false;
            }
        }
        
        return true;
    }

    /**
     * Mendapatkan semua permission dari role yang dimiliki
     *
     * @return \Illuminate\Support\Collection
     */
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