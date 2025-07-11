<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class KasirMiddleware
{
    /**
     * Menangani request yang masuk.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check() && in_array(Auth::user()->role, ['kasir', 'admin'])) {
            // Admin juga memiliki akses ke fitur kasir
            return $next($request);
        }

        // Jika tidak, kembalikan ke halaman login atau tampilkan error 403
        return redirect('/login')->with('error', 'Akses ditolak. Anda bukan Kasir.');
    }
}