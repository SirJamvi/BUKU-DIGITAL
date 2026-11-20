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
    public function hasRole(string|array $roleName): bool
    {
        $requested = is_array($roleName) ? $roleName : [$roleName];

        if (method_exists($this, 'allRoles')) {
            $userRoles = $this->allRoles();
            foreach ($requested as $role) {
                if (is_string($role) && in_array(strtolower(trim($role)), $userRoles, true)) {
                    return true;
                }
            }
            return false;
        }

        foreach ($requested as $role) {
            if (!is_string($role) || trim($role) === '') {
                continue;
            }

            $normalized = strtolower(trim($role));

            if (is_string($this->role) && strtolower(trim($this->role)) === $normalized) {
                return true;
            }

            if (is_array($this->secondary_roles)) {
                $secondaryNormalized = array_map(
                    fn ($r) => is_string($r) ? strtolower(trim($r)) : '',
                    $this->secondary_roles
                );
                if (in_array($normalized, $secondaryNormalized, true)) {
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