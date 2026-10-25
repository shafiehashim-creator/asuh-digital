<?php

namespace App\Http\Middleware;

use App\Services\ChildMode;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The reader and Mod Anak pages: a signed-in account, or a pupil who came in
 * with a class code (Phase D) and has no account of their own.
 */
class AuthenticateOrPupil
{
    public function __construct(private ChildMode $childMode) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() || $this->childMode->child(null)?->isPupil()) {
            return $next($request);
        }

        return redirect()->guest(route('login'));
    }
}
