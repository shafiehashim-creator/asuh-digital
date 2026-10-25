<?php

use App\Http\Middleware\LockChildMode;
use App\Http\Middleware\ThrottleRegistrations;
use App\Http\Middleware\TrackVisits;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // CHIP posts here server-to-server; requests are verified by RSA signature instead.
        $middleware->validateCsrfTokens(except: ['webhooks/chip']);

        // Mod Anak keeps a child on their shelf until the parent gate is passed.
        $middleware->web(append: [LockChildMode::class, ThrottleRegistrations::class, TrackVisits::class]);
        // Before the login check, so a pupil (no account) is sent back to the shelf, not to the login page.
        $middleware->prependToPriorityList(before: AuthenticatesRequests::class, prepend: LockChildMode::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
