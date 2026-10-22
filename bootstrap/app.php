<?php

declare(strict_types=1);

use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\NoIndexPrivateAreas;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web     : __DIR__.'/../routes/web.php',
        api     : __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health  : '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // The app sits behind nginx (https is ended there): take the scheme and the address of the client from its headers,
        // but only from the proxy on this host or in the docker network, so that a client cannot invent them.
        $middleware->trustProxies(at: ['127.0.0.1', '::1', '10.0.0.0/8', '172.16.0.0/12', '192.168.0.0/16']);
        // A changed password ends the other sessions of the account.
        $middleware->authenticateSessions();
        $middleware->append(NoIndexPrivateAreas::class);
        $middleware->web(append: [
            HandleInertiaRequests::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {})->create();
