<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** The teacher portal (/guru) is for institution administrators and teachers only. */
class EnsureInstitutionStaff
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->isInstitutionStaff(), 403);

        return $next($request);
    }
}
