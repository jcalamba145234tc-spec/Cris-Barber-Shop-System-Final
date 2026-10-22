<?php

namespace App\Http\Middleware;

use App\Models\Barber;
use Closure;
use Illuminate\Http\Request;

class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles)
    {
        abort_unless($request->user() && in_array($request->user()->role, $roles, true), 403);

        if ($request->user()->role === 'barber' && ! $request->routeIs('logout')) {
            abort_unless(Barber::current()->where('user_id', $request->user()->id)->where('status', '!=', 'inactive')->exists(), 403);
        }

        return $next($request);
    }
}
