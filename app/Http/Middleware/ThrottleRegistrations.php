<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

/**
 * Fortify's registration route has no rate limit of its own: at most a few
 * new accounts a minute from one address.
 */
class ThrottleRegistrations
{
    private const PER_MINUTE = 5;

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->route()?->named('register.store')) {
            $key = 'register:'.$request->ip();

            if (RateLimiter::tooManyAttempts($key, self::PER_MINUTE)) {
                abort(429);
            }

            RateLimiter::hit($key, 60);
        }

        return $next($request);
    }
}
