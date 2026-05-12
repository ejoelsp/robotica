<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class LoginController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $email = trim($request->input('email', ''));
        $password = $request->input('password', '');
        $remember = $request->boolean('remember');

        $throttleKey = Str::lower($email) . '|' . $request->ip();
        $maxAttempts = 5;
        $decayMinutes = 3;
        $decaySeconds = $decayMinutes * 60;

        if (RateLimiter::tooManyAttempts($throttleKey, $maxAttempts)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            $minutes = (int) ceil($seconds / 60);

            $message = $minutes === 1
                ? 'Has realizado demasiados intentos. Por favor espera 1 minuto antes de volver a intentar.'
                : "Has realizado demasiados intentos. Por favor espera {$minutes} minutos antes de volver a intentar.";

            return redirect()->route('login')->with([
                'loginError' => $message,
                'old' => [
                    'email' => $email,
                    'remember' => $remember,
                ],
            ]);
        }

        if ($email === '' || $password === '') {
            return redirect()->route('login')->with([
                'loginError' => 'Debes ingresar tu correo y contraseña.',
                'old' => [
                    'email' => $email,
                    'remember' => $remember,
                ],
            ]);
        }

        if (!Auth::attempt(['email' => $email, 'password' => $password], $remember)) {
            RateLimiter::hit($throttleKey, $decaySeconds);

            return redirect()->route('login')->with([
                'loginError' => 'Las credenciales no son válidas. Verifica tu correo y contraseña.',
                'old' => [
                    'email' => $email,
                    'remember' => $remember,
                ],
            ]);
        }

        RateLimiter::clear($throttleKey);
        $request->session()->regenerate();

        $user = Auth::user();

        switch ((int) $user->role_id) {
            case 1:
                return redirect()->route('admin.dashboard');

            case 2:
                if (empty($user->photo_path)) {
                    return redirect()->route('juez.completar-perfil');
                }

                return redirect()->route('juez.dashboard');

            case 3:
                return redirect()->route('dashboard');

            case 4:
            default:
                return redirect()->route('dashboard');
        }
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
