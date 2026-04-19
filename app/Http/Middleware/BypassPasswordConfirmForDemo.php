<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class BypassPasswordConfirmForDemo
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check() && Auth::user()->isDemoAccount()) {
            $request->session()->put('auth.password_confirmed_at', time());
        }

        return $next($request);
    }
}
