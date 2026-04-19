<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RedirectIfDemoMode
{
    public function handle(Request $request, Closure $next): Response
    {
        if (config('demo.enabled') && in_array($request->path(), ['login', 'register'])) {
            return redirect()->route('demo.login');
        }

        return $next($request);
    }
}
