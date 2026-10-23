<?php

use App\Http\Middleware\EnsureCanSeeBoard;
use App\Http\Middleware\EnsureOperator;
use App\Http\Middleware\EnsureService;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Laravel\Sanctum\Http\Middleware\CheckAbilities;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'board' => EnsureCanSeeBoard::class,
            'operator' => EnsureOperator::class,
            'service' => EnsureService::class,
            'abilities' => CheckAbilities::class,
        ]);

        // The API never redirects: a missing or bad token is a 401.
        $middleware->redirectGuestsTo(fn () => null);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
