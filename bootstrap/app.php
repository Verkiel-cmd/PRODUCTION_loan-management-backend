<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        /*
         * Render sits behind a proxy (browser -> Netlify -> Render). Trust it
         * so HTTPS detection, `secure` session cookies and generated URLs work.
         */
        $middleware->trustProxies(at: '*');
        /*
         * Our auth is COOKIE/SESSION based (see AuthController docblock). The
         * `api` middleware group does NOT include StartSession by default, so
         * without this, `Auth::attempt()` + `$request->session()->regenerate()`
         * throw "Session store not set on request". This boots the session for
         * every /api/* route so the cookie flow works end-to-end.
         */
        $middleware->appendToGroup('api', \Illuminate\Session\Middleware\StartSession::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
