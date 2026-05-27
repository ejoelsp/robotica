<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditoriaService;
use App\Services\EvaluacionJuezService;
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
        $decayMinutes = 5;
        $decaySeconds = $decayMinutes * 60;

        if (RateLimiter::tooManyAttempts($throttleKey, $maxAttempts)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            $minutes = (int) ceil($seconds / 60);

            $message = $minutes === 1
                ? 'Has realizado demasiados intentos. Por favor espera 1 minuto antes de volver a intentar.'
                : "Has realizado demasiados intentos. Por favor espera {$minutes} minutos antes de volver a intentar.";

            app(AuditoriaService::class)->registrar(
                accion: 'login_bloqueado',
                tabla: 'seguridad.users',
                modulo: 'autenticacion',
                descripcion: 'Intento de inicio de sesión bloqueado por límite de intentos.',
                payload: ['email' => $email],
                estado: 'fallido',
                userId: User::query()->where('email', mb_strtolower($email))->value('id'),
                request: $request
            );

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

            app(AuditoriaService::class)->registrar(
                accion: 'login_fallido',
                tabla: 'seguridad.users',
                modulo: 'autenticacion',
                descripcion: 'Credenciales inválidas en inicio de sesión.',
                payload: ['email' => $email],
                estado: 'fallido',
                userId: User::query()->where('email', mb_strtolower($email))->value('id'),
                request: $request
            );

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

        if (! (bool) $user->estado) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            app(AuditoriaService::class)->registrar(
                accion: 'login_usuario_inactivo',
                tabla: 'seguridad.users',
                modulo: 'autenticacion',
                descripcion: 'Inicio de sesión rechazado por usuario inactivo.',
                payload: ['email' => $email],
                estado: 'fallido',
                userId: (int) $user->id,
                request: $request
            );

            return redirect()->route('login')->with([
                'loginError' => 'Tu cuenta está inactiva. Contacta al administrador.',
                'old' => [
                    'email' => $email,
                    'remember' => $remember,
                ],
            ]);
        }

        app(AuditoriaService::class)->registrar(
            accion: 'login_exitoso',
            tabla: 'seguridad.users',
            modulo: 'autenticacion',
            descripcion: 'Inicio de sesión exitoso.',
            payload: ['email' => $email],
            userId: (int) $user->id,
            request: $request
        );

        switch ((int) $user->role_id) {
            case 1:
                return redirect()->route('admin.competencias.index');

            case 2:
                if (empty($user->photo_path)) {
                    return redirect()->route('juez.completar-perfil');
                }

                return redirect()->route('juez.dashboard');

            case 3:
                return redirect()->route('competidor.mis-inscripciones');

            case 4:
            default:
                return redirect()->route('competidor.mis-inscripciones');
        }
    }

    public function destroy(Request $request): RedirectResponse
    {
        $user = Auth::user();

        if ($user && (int) $user->role_id === 2) {
            app(EvaluacionJuezService::class)->liberarBloqueosRegistroDelJuez($user, 'logout');
        }

        if ($user) {
            app(AuditoriaService::class)->registrar(
                accion: 'logout',
                tabla: 'seguridad.users',
                modulo: 'autenticacion',
                descripcion: 'Cierre de sesión.',
                payload: ['email' => $user->email],
                userId: (int) $user->id,
                request: $request
            );
        }

        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
