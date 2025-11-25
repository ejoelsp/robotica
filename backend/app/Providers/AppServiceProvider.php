<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;


class AppServiceProvider extends ServiceProvider
{
    protected function configureRateLimiting(): void
    {
        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(5)
                ->by($request->ip())
                ->response(function () {
                    // En vez del 429 genérico, volvemos al login con un mensaje
                    return redirect()
                        ->route('login')
                        ->with('loginError', 'Has realizado demasiados intentos de inicio de sesión. Inténtalo de nuevo en 1 minuto.');
                });
        });
    }
}
