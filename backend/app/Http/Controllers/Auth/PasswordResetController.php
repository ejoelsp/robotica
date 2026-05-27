<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\BrevoMailService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class PasswordResetController extends Controller
{
    private const CODE_EXPIRATION_MINUTES = 3;
    private const MAX_CODE_ATTEMPTS = 3;
    private const MAX_SEND_ATTEMPTS = 3;
    private const SEND_DECAY_MINUTES = 3;

    public function request(): Response
    {
        return Inertia::render('Auth/ForgotPassword');
    }

    public function sendCode(Request $request, BrevoMailService $brevoMailService): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'string', 'email', 'max:150'],
        ], [
            'email.required' => 'Ingresa tu correo electrónico.',
            'email.email' => 'Ingresa un correo electrónico válido.',
        ]);

        $email = mb_strtolower(trim((string) $data['email']));
        $throttleKey = 'password-reset-v2:' . $request->ip() . '|' . $email;

        $request->session()->forget('password_reset');

        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_SEND_ATTEMPTS)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            $wait = $seconds >= 60
                ? ceil($seconds / 60) . ' minuto(s)'
                : $seconds . ' segundo(s)';

            return back()->withErrors([
                'email' => 'Has solicitado demasiados códigos. Intenta nuevamente en unos minutos.',
            ])->withInput(['email' => $email]);
        }

        RateLimiter::hit($throttleKey, self::SEND_DECAY_MINUTES * 60);

        $user = User::query()->where('email', $email)->first();

        if ($user) {
            try {
                $code = (string) random_int(100000, 999999);

                DB::transaction(function () use ($user, $email, $code) {
                    DB::table('seguridad.password_reset_codes')
                        ->where('user_id', $user->id)
                        ->whereNull('used_at')
                        ->delete();

                    DB::table('seguridad.password_reset_codes')->insert([
                        'user_id' => $user->id,
                        'email' => $email,
                        'code_hash' => Hash::make($code),
                        'expires_at' => now()->addMinutes(self::CODE_EXPIRATION_MINUTES),
                        'attempts' => 0,
                        'used_at' => null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                });

                $this->sendRecoveryEmail($brevoMailService, $user, $code);
            } catch (Throwable $exception) {
                Log::error('No se pudo enviar el codigo de recuperacion de contrasena.', [
                    'email' => $email,
                    'message' => $exception->getMessage(),
                ]);

                return back()->withErrors([
                    'email' => 'No se pudo enviar el código de recuperación. Intenta nuevamente.',
                ])->withInput(['email' => $email]);
            }
        }

        return redirect()
            ->route('password.reset.show', ['email' => $email])
            ->with('success', 'Si el correo está registrado, recibirás un código de recuperación.');
    }

    public function resetForm(Request $request): Response|RedirectResponse
    {
        $email = mb_strtolower(trim((string) $request->query('email', '')));

        if ($email === '') {
            return redirect()->route('password.request');
        }

        return Inertia::render('Auth/ResetPassword', [
            'email' => $email,
            'codeVerified' => $this->hasVerifiedResetSession($request, $email),
        ]);
    }

    public function verifyCode(Request $request): Response|RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'string', 'email', 'max:150'],
            'code' => ['required', 'digits:6'],
        ], [
            'email.required' => 'El correo electrónico es obligatorio.',
            'email.email' => 'Ingresa un correo electrónico válido.',
            'code.required' => 'Ingresa el código de recuperación.',
            'code.digits' => 'El código debe tener 6 dígitos.',
        ]);

        $email = mb_strtolower(trim((string) $data['email']));
        $code = (string) $data['code'];
        $reset = $this->latestValidResetCode($email);

        if (! $reset) {
            return back()->withErrors([
                'code' => 'El código no existe o ya caducó. Solicita uno nuevo.',
            ])->withInput(['email' => $email]);
        }

        if ((int) $reset->attempts >= self::MAX_CODE_ATTEMPTS) {
            return back()->withErrors([
                'code' => 'El código superó el número máximo de intentos. Solicita uno nuevo.',
            ])->withInput(['email' => $email]);
        }

        if (! Hash::check($code, $reset->code_hash)) {
            DB::table('seguridad.password_reset_codes')
                ->where('id', $reset->id)
                ->update([
                    'attempts' => DB::raw('attempts + 1'),
                    'updated_at' => now(),
                ]);

            return back()->withErrors([
                'code' => 'El código ingresado no es correcto.',
            ])->withInput(['email' => $email]);
        }

        $request->session()->put('password_reset', [
            'email' => $email,
            'code_id' => $reset->id,
            'verified_at' => now()->toISOString(),
        ]);

        return redirect()
            ->route('password.reset.show', ['email' => $email])
            ->with('success', 'Código verificado. Ahora define tu nueva contraseña.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'string', 'email', 'max:150'],
            'password' => [
                'required',
                'string',
                'confirmed',
                'min:8',
                'regex:/[A-Z]/',
                'regex:/[a-z]/',
                'regex:/[0-9]/',
                'regex:/[^A-Za-z0-9]/',
            ],
        ], [
            'email.required' => 'El correo electrónico es obligatorio.',
            'email.email' => 'Ingresa un correo electrónico válido.',
            'password.required' => 'Ingresa tu nueva contraseña.',
            'password.confirmed' => 'Las contraseñas no coinciden.',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres.',
            'password.regex' => 'La contraseña debe incluir mayúsculas, minúsculas, números y un carácter especial.',
        ]);

        $email = mb_strtolower(trim((string) $data['email']));
        $session = $request->session()->get('password_reset');

        if (! is_array($session) || ($session['email'] ?? null) !== $email) {
            return redirect()
                ->route('password.reset.show', ['email' => $email])
                ->withErrors(['general' => 'Primero verifica el código de recuperación.']);
        }

        $reset = DB::table('seguridad.password_reset_codes')
            ->where('id', $session['code_id'] ?? null)
            ->where('email', $email)
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->first();

        if (! $reset) {
            $request->session()->forget('password_reset');

            return redirect()
                ->route('password.request')
                ->withErrors(['email' => 'El código caducó. Solicita uno nuevo.']);
        }

        DB::transaction(function () use ($email, $data, $reset) {
            $user = User::query()->where('email', $email)->firstOrFail();

            if (Hash::check($data['password'], $user->password)) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'password' => 'La nueva contraseña debe ser diferente a la contraseña anterior.',
                ]);
            }

            $user->password = Hash::make($data['password']);
            $user->must_change_password = false;
            $user->save();

            DB::table('seguridad.password_reset_codes')
                ->where('id', $reset->id)
                ->update([
                    'used_at' => now(),
                    'updated_at' => now(),
                ]);

            DB::table('seguridad.password_reset_codes')
                ->where('user_id', $user->id)
                ->whereNull('used_at')
                ->delete();
        });

        $request->session()->forget('password_reset');

        return redirect()
            ->route('login')
            ->with('success', 'Contraseña actualizada correctamente. Inicia sesión con tu nueva contraseña.');
    }

    private function latestValidResetCode(string $email): ?object
    {
        return DB::table('seguridad.password_reset_codes')
            ->where('email', $email)
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->orderByDesc('id')
            ->first();
    }

    private function hasVerifiedResetSession(Request $request, string $email): bool
    {
        $session = $request->session()->get('password_reset');

        if (! is_array($session) || ($session['email'] ?? null) !== $email) {
            return false;
        }

        return DB::table('seguridad.password_reset_codes')
            ->where('id', $session['code_id'] ?? null)
            ->where('email', $email)
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->exists();
    }

    private function sendRecoveryEmail(BrevoMailService $brevoMailService, User $user, string $code): void
    {
        $name = trim((string) $user->name . ' ' . (string) $user->last_name) ?: 'Usuario';
        $minutes = self::CODE_EXPIRATION_MINUTES;

        $html = "
            <h2>Recuperación de contraseña</h2>
            <p>Hola " . e($name) . ",</p>
            <p>Recibimos una solicitud para restablecer tu contraseña en el sistema del Club de Robótica ESPOCH.</p>
            <p>Tu código de recuperación es:</p>
            <p style='font-size: 24px; font-weight: bold; letter-spacing: 4px;'>" . e($code) . "</p>
            <p>Este código caduca en {$minutes} minutos.</p>
            <p>Si no solicitaste este cambio, puedes ignorar este mensaje.</p>
        ";

        $brevoMailService->sendEmail(
            $user->email,
            $name,
            'Código de recuperación - Club de Robótica ESPOCH',
            $html
        );
    }
}
