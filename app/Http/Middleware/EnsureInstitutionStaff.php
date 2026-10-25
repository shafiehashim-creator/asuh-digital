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
        if (! $request->user()?->isInstitutionStaff()) {
            return redirect()->route('switch-account', ['ke' => 'guru']);
        }

        return $next($request);
    }
}
