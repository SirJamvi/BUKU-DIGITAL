<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class PermissionMiddleware
{
    /**
     * Menangani request yang masuk berdasarkan izin.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string  $permission
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        if (!Auth::check()) {
            return redirect('/login');
        }
        
        // Menggunakan trait HasPermissions yang sudah dibuat sebelumnya
        if (! $request->user()->hasPermission($permission)) {
            abort(403, 'AKSES DITOLAK: ANDA TIDAK MEMILIKI IZIN YANG DIPERLUKAN.');
        }

        return $next($request);
    }
}