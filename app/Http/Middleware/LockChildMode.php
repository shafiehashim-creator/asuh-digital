<?php

namespace App\Http\Middleware;

use App\Services\ChildMode;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * While Mod Anak is on, every page except the child's shelf, the reader and
 * the parent gate leads back to the shelf, so a child cannot reach prices,
 * purchases, account settings or log out (CLAUDE.md).
 */
class LockChildMode
{
    /** Route names a child may use. */
    // The app manifest is not a page: the installed app needs it in Mod Anak too.
    private const ALLOWED = ['child-mode.*', 'reader.*', 'pupil.*', 'manifest'];

    public function __construct(private ChildMode $childMode) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->childMode->isActive()) {
            return $next($request);
        }

        // Nothing to lock any more: logged out, profile deleted, licence ended or catalogue closed.
        if (! $this->childMode->child($request->user()) || ! EnsureCatalogOpen::isOpenFor($request->user())) {
            $pupil = $this->childMode->sessionPupil();
            $reason = $pupil ? ChildMode::pupilClosedReason($pupil) ?? 'pupil-ended' : null;
            $wasPupil = $this->childMode->isPupil();
            $this->childMode->leave();

            // A pupil has no account to fall back on: back to the class code page.
            if ($wasPupil && ! $request->route()?->named('pupil.*')) {
                return redirect()->route('pupil.join')->with('status', $reason ?? 'pupil-ended');
            }

            return $next($request);
        }

        // The class pages are for pupils: a family's child leaves only through the parent gate.
        if ($request->route()?->named('pupil.*') && ! $this->childMode->isPupil()) {
            return redirect()->route('child-mode.home');
        }

        if ($request->route()?->named(...self::ALLOWED)) {
            return $next($request);
        }

        return redirect()->route('child-mode.home');
    }
}
