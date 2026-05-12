<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\ActivateAccountRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

class ActivacionCuentaController extends Controller
{
    public function show(Request $request): Response|RedirectResponse
    {
        $plainToken = (string) $request->query('token', '');

        if ($plainToken === '') {
            return redirect()->route('login')->withErrors([
                'general' => 'El enlace de activación no es válido.',
            ]);
        }

        $activation = DB::table('seguridad.user_activation_tokens')
            ->where('token', hash('sha256', $plainToken))
            ->first();

        if (!$activation) {
            return redirect()->route('login')->withErrors([
                'general' => 'El enlace de activación no es válido.',
            ]);
        }

        if ($activation->used_at !== null) {
            return redirect()->route('login')->withErrors([
                'general' => 'Este enlace ya fue utilizado.',
            ]);
        }

        if (now()->greaterThan($activation->expires_at)) {
            return redirect()->route('login')->withErrors([
                'general' => 'El enlace de activación ha caducado.',
            ]);
        }

        return Inertia::render('Auth/ActivarCuenta', [
            'token' => $plainToken,
        ]);
    }

    public function store(ActivateAccountRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $activation = DB::table('seguridad.user_activation_tokens')
            ->where('token', hash('sha256', $data['token']))
            ->first();

        if (!$activation) {
            return back()->withErrors([
                'general' => 'El enlace de activación no es válido.',
            ]);
        }

        if ($activation->used_at !== null) {
            return back()->withErrors([
                'general' => 'Este enlace ya fue utilizado.',
            ]);
        }

        if (now()->greaterThan($activation->expires_at)) {
            return back()->withErrors([
                'general' => 'El enlace de activación ha caducado.',
            ]);
        }

        DB::transaction(function () use ($activation, $data) {
            $user = User::findOrFail($activation->user_id);

            $user->password = Hash::make($data['password']);
            $user->must_change_password = false;
            $user->email_verified_at = now();
            $user->save();

            DB::table('seguridad.user_activation_tokens')
                ->where('id', $activation->id)
                ->update([
                    'used_at' => now(),
                    'updated_at' => now(),
                ]);
        });

        return redirect()->route('login')->with([
            'success' => 'Cuenta activada correctamente. Ya puedes iniciar sesión.',
        ]);
    }
}