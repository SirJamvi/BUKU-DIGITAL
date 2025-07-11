<?php

namespace App\Providers;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ServiceProvider;
use App\Models\User;

class PermissionServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Blade directive untuk memeriksa peran 'admin'
        Blade::if('admin', function () {
            /** @var User|null $user */
            $user = Auth::user();
            return $user && $user->role === 'admin';
        });

        // Blade directive untuk memeriksa peran 'kasir'
        Blade::if('kasir', function () {
            /** @var User|null $user */
            $user = Auth::user();
            return $user && $user->role === 'kasir';
        });

        // Blade directive untuk memeriksa peran secara umum
        Blade::if('role', function (string $role) {
            /** @var User|null $user */
            $user = Auth::user();
            return $user && $user->hasRole($role);
        });

        // Blade directive untuk memeriksa izin
        Blade::if('permission', function (string $permission) {
            /** @var User|null $user */
            $user = Auth::user();
            return $user && $user->hasPermission($permission);
        });
    }
}