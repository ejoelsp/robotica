<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Inertia\Inertia;

class LoginController extends Controller
{
    public function store(Request $request)
    {
        $email    = trim($request->input('email', ''));
        $password = $request->input('password', '');
        $remember = $request->boolean('remember');

        // 1) Clave para el rate limit (correo + IP)
        $throttleKey   = Str::lower($email).'|'.$request->ip();
        $maxAttempts   = 5;   // máximo intentos
        $decayMinutes  = 3;   // ventana en MINUTOS
        $decaySeconds  = $decayMinutes * 60; // lo que usa RateLimiter internamente

        // 2) Si ya está bloqueado, mostrar mensaje en el login
        if (RateLimiter::tooManyAttempts($throttleKey, $maxAttempts)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            // convertir a minutos (redondeando hacia arriba)
            $minutes = (int) ceil($seconds / 60);

            $mensaje = $minutes === 1
                ? 'Has realizado demasiados intentos. Por favor espera 1 minuto antes de volver a intentar.'
                : "Has realizado demasiados intentos. Por favor espera {$minutes} minutos antes de volver a intentar.";

            return Inertia::render('Auth/Login', [
                'loginError' => $mensaje,
                'old' => [
                    'email'    => $email,
                    'remember' => $remember,
                ],
            ]);
        }

        // 3) Validación básica
        if ($email === '' || $password === '') {
            // (Opcional) no contamos esto como intento fallido
            return Inertia::render('Auth/Login', [
                'loginError' => 'Debes ingresar tu correo y contraseña.',
                'old' => [
                    'email'    => $email,
                    'remember' => $remember,
                ],
            ]);
        }

        // 4) Intentar autenticación
        if (! Auth::attempt(['email' => $email, 'password' => $password], $remember)) {
            // Contar este intento como fallido (con ventana en segundos)
            RateLimiter::hit($throttleKey, $decaySeconds);

            return Inertia::render('Auth/Login', [
                'loginError' => 'Las credenciales no son válidas. Verifica tu correo y contraseña.',
                'old' => [
                    'email'    => $email,
                    'remember' => $remember,
                ],
            ]);
        }

        // 5) Si todo OK, limpiar contador, regenerar sesión y redirigir
        RateLimiter::clear($throttleKey);

        $request->session()->regenerate();

        return redirect()->route('dashboard');
    }

    public function destroy(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
