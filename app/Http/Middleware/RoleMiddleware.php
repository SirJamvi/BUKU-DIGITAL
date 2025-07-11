<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Menangani request yang masuk berdasarkan peran.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string  ...$roles
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        if (!Auth::check()) {
            return redirect('/login');
        }

        /** @var \App\Models\User $user */
        $user = Auth::user();

        // Solusi 1: Jika menggunakan Spatie Laravel Permission
        // Admin memiliki akses ke semua role
        if ($user->hasRole('admin')) {
            return $next($request);
        }

        foreach ($roles as $role) {
            if ($user->hasRole($role)) {
                return $next($request);
            }
        }

        // Tampilkan halaman error jika tidak memiliki peran yang sesuai
        abort(403, 'AKSES DITOLAK: ANDA TIDAK MEMILIKI PERAN YANG SESUAI.');
    }

    /**
     * Alternative handle method jika menggunakan field 'role' di database.
     * 
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string  ...$roles
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handleWithRoleField(Request $request, Closure $next, ...$roles): Response
    {
        if (!Auth::check()) {
            return redirect('/login');
        }

        $user = Auth::user();

        // Admin memiliki akses ke semua role
        if ($user->role === 'admin') {
            return $next($request);
        }

        // Cek apakah user memiliki role yang diperlukan
        if (in_array($user->role, $roles)) {
            return $next($request);
        }

        // Tampilkan halaman error jika tidak memiliki peran yang sesuai
        abort(403, 'AKSES DITOLAK: ANDA TIDAK MEMILIKI PERAN YANG SESUAI.');
    }

    /**
     * Alternative handle method jika menggunakan relasi roles.
     * 
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string  ...$roles
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handleWithRoleRelation(Request $request, Closure $next, ...$roles): Response
    {
        if (!Auth::check()) {
            return redirect('/login');
        }

        $user = Auth::user();

        // Admin memiliki akses ke semua role
        $userRoles = $user->roles->pluck('name')->toArray();
        
        if (in_array('admin', $userRoles)) {
            return $next($request);
        }

        // Cek apakah user memiliki role yang diperlukan
        foreach ($roles as $role) {
            if (in_array($role, $userRoles)) {
                return $next($request);
            }
        }

        // Tampilkan halaman error jika tidak memiliki peran yang sesuai
        abort(403, 'AKSES DITOLAK: ANDA TIDAK MEMILIKI PERAN YANG SESUAI.');
    }
}