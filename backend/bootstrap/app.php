<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'juez.profile.completed' => \App\Http\Middleware\EnsureJuezProfileCompleted::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $mensajeSesionExpirada = 'Tu sesión ha expirado por inactividad. Inicia sesión nuevamente.';

        $exceptions->render(function (\Illuminate\Session\TokenMismatchException $exception, \Illuminate\Http\Request $request) use ($mensajeSesionExpirada) {
            $esInertia = $request->headers->get('X-Inertia') === 'true';

            if ($request->expectsJson() && ! $esInertia) {
                return response()->json([
                    'message' => $mensajeSesionExpirada,
                    'redirect' => route('login'),
                ], 419);
            }

            return redirect()
                ->guest(route('login'))
                ->with('loginError', $mensajeSesionExpirada);
        });

        $exceptions->render(function (\Illuminate\Auth\AuthenticationException $exception, \Illuminate\Http\Request $request) use ($mensajeSesionExpirada) {
            $esInertia = $request->headers->get('X-Inertia') === 'true';

            if ($request->expectsJson() && ! $esInertia) {
                return response()->json([
                    'message' => $mensajeSesionExpirada,
                    'redirect' => route('login'),
                ], 401);
            }

            return redirect()
                ->guest(route('login'))
                ->with('loginError', $mensajeSesionExpirada);
        });
    })->create();
