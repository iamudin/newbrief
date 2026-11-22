<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureLevel
{
    public function handle(Request $request, Closure $next, string ...$levels): Response
    {
        $user = $request->user();

        if (! $user || $user->is_admin || ! in_array($user->level()?->value, $levels, true)) {
            abort(403, 'Menu ini tidak tersedia untuk jabatan Anda.');
        }

        return $next($request);
    }
}
