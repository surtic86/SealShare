<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSetupComplete
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! User::query()->where('is_admin', true)->exists() && ! $request->is('setup', 'setup/*', 'livewire*')) {
            return redirect()->route('setup');
        }

        return $next($request);
    }
}
