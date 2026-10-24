<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps the Phase B catalogue and reader hidden (404) until the admin turns
 * on "catalog_open". Staff can always see them, to prepare and review books.
 */
class EnsureCatalogOpen
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Setting::get('catalog_open') && ! $request->user()?->isStaff()) {
            abort(404);
        }

        return $next($request);
    }

    public static function isOpenFor(?User $user): bool
    {
        return (bool) Setting::get('catalog_open') || (bool) $user?->isStaff();
    }
}
