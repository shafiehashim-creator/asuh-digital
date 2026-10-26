<?php

namespace App\Http\Middleware;

use App\Support\Locale;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\Response;

/**
 * Uses the language the visitor chose (session, then a long-lived cookie),
 * else Bahasa Melayu. Never guesses from the browser: BM is the site's
 * main language.
 */
class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->session()->get(Locale::SESSION_KEY) ?? $request->cookie(Locale::COOKIE);

        if (Locale::isSupported($locale)) {
            app()->setLocale($locale);
            Carbon::setLocale($locale);
        }

        return $next($request);
    }
}
