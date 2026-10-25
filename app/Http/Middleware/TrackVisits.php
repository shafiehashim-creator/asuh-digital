<?php

namespace App\Http\Middleware;

use App\Services\VisitTracker;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/** Counts successful public page views for the admin traffic report. */
class TrackVisits
{
    public function __construct(private VisitTracker $tracker) {}

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($response->getStatusCode() === 200 && str_contains((string) $response->headers->get('Content-Type'), 'text/html')) {
            try {
                $this->tracker->record($request);
            } catch (Throwable $e) {
                // A report must never break a page.
                report($e);
            }
        }

        return $response;
    }
}
