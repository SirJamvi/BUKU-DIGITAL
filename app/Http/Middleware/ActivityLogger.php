<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\UserActivityLog;
use Symfony\Component\HttpFoundation\Response;

class ActivityLogger
{
    /**
     * Mencatat aktivitas pengguna.
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

        $response = $next($request);

        // Hanya log request yang berhasil dan bukan GET
        if ($response->isSuccessful() && $request->method() !== 'GET') {
            UserActivityLog::create([
                'user_id' => Auth::id(),
                'action' => $request->route()->getActionName(),
                'module' => explode('/', $request->path())[0] ?? 'general',
                'details' => json_encode($request->except(['password', '_token'])),
                'ip_address' => $request->ip(),
            ]);
        }

        return $response;
    }
}