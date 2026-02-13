<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SystemPasswordGate
{
    public function handle(Request $request, Closure $next): Response
    {
        $systemPassword = Setting::get('system_password');

        if (! $systemPassword) {
            return $next($request);
        }

        if ($request->session()->get('system_password_verified') === true) {
            return $next($request);
        }

        return redirect()->route('system-password');
    }
}
