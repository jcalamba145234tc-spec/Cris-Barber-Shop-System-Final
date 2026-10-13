<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PreventStaleAuthPages
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Do not let cached login or account pages stand in for a fresh auth check.
        $response->headers->set('Cache-Control', 'private, no-store, no-cache, max-age=0, must-revalidate');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');

        if ($request->user() && $request->isMethod('GET') && ! $request->expectsJson()
            && $response->getStatusCode() === 200
            && str_contains($response->headers->get('Content-Type', ''), 'text/html')
            && in_array('auth', $request->route()?->gatherMiddleware() ?? [], true)) {
            $path = $request->getRequestUri();
            if (str_starts_with($path, '/') && ! str_starts_with($path, '//') && ! str_contains($path, '\\')) {
                $request->session()->put('auth.last_page', $path);
            }
        }

        return $response;
    }
}
