<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Support\Facades\DB;

class PermissionSeeder extends Seeder
{
    /**
     * Menjalankan seeder untuk izin (permissions) dan menautkannya ke peran.
     */
    public function run(): void
    {
        // Kosongkan tabel permission dan tabel pivot
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        Permission::truncate();
        DB::table('role_permission')->truncate(); // Tabel pivot
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        $this->command->info('Membuat semua izin...');
        $permissions = $this->generatePermissions();
        Permission::insert($permissions);
        
        $this->command->info('Menautkan izin ke peran...');
        $this->assignPermissionsToRoles();
    }

    /**
     * Menghasilkan array semua kemungkinan izin dari config.
     */
    private function generatePermissions(): array
    {
        $permissions = [];
        $modules = config('permissions.modules', []);
        $actions = config('permissions.actions', []);
        $timestamp = now();

        foreach ($modules as $module) {
            foreach ($actions as $action) {
                $permissions[] = [
                    'name' => "{$module}.{$action}",
                    'display_name' => ucfirst($action) . ' ' . ucfirst($module),
                    'module' => $module,
                    'action' => $action,
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ];
            }
        }
        return $permissions;
    }

    /**
     * Menautkan izin ke peran berdasarkan config/roles.php.
     */
    private function assignPermissionsToRoles(): void
    {
        $rolesConfig = config('roles');
        $roles = Role::pluck('id', 'name');
        $permissions = Permission::pluck('id', 'name');

        foreach ($rolesConfig as $roleName => $roleData) {
            if (isset($roles[$roleName])) {
                $role = Role::find($roles[$roleName]);
                $permissionNames = $roleData['permissions'] ?? [];
                
                $permissionIds = collect($permissionNames)
                    ->map(fn ($name) => $permissions->get($name))
                    ->filter(); // Hapus nilai null jika ada izin yang tidak ditemukan

                $role->permissions()->sync($permissionIds);
            }
        }
    }
}