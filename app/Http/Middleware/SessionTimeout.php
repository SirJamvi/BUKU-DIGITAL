<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Symfony\Component\HttpFoundation\Response;

class SessionTimeout
{
    /**
     * Menangani session timeout karena tidak ada aktivitas.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!Auth::check()) {
            return $next($request);
        }

        $sessionKey = 'last_activity_time';
        $timeout = config('session.lifetime') * 60; // dalam detik

        if (session()->has($sessionKey) && (time() - session($sessionKey) > $timeout)) {
            Auth::logout();
            session()->invalidate();
            session()->regenerateToken();

            return redirect('/login')->with('info', 'Sesi Anda telah berakhir karena tidak ada aktivitas.');
        }

        session([$sessionKey => time()]);

        return $next($request);
    }
}