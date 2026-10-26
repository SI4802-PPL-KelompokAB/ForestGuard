<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    public function handle(Request $request, Closure $next, string $roles): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }

        $allowed = explode('|', $roles);
        $roleName = $user->role?->name;

        if (! in_array($roleName, $allowed, true)) {
            abort(403);
        }

        return $next($request);
    }
}
