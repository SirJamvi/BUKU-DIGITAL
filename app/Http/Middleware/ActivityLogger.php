<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\UserActivityLog;
use Symfony\Component\HttpFoundation\Response;

class ActivityLogger
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (Auth::check() && $request->method() !== 'GET' && $response->isSuccessful()) {
            $user = Auth::user();
            if ($user->business_id) {
                UserActivityLog::create([
                    'business_id' => $user->business_id,
                    'user_id'     => $user->id,
                    'action'      => $request->route()->getActionName(),
                    'module'      => explode('/', $request->path())[0] ?? 'general',
                    'details'     => json_encode($request->except(['password', '_token', '_method'])),
                    'ip_address'  => $request->ip(),
                ]);
            }
        }

        return $response;
    }
}
