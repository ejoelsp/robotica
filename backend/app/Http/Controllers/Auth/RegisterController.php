<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterRequest;
use App\Models\User;
use Inertia\Inertia;

class RegisterController extends Controller
{
    public function create()
    {
        return Inertia::render('Auth/Register');
    }

    public function store(RegisterRequest $request)
    {
        $data = $request->validated();

        if (User::where('email', $data['email'])->exists()) {
            return Inertia::render('Auth/Register', [
                'registerError' => 'Este correo ya está registrado. Por favor usa otro o inicia sesión.',
                'old' => [
                    'name' => $data['name'],
                    'last_name' => $data['last_name'],
                    'email' => $data['email'],
                ],
            ]);
        }

        $user = User::create([
            'name' => $data['name'],
            'last_name' => $data['last_name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'role_id' => 3,
            'telefono' => $data['telefono'] ?? null,
        ]);

        $user->email_verified_at = now();
        $user->save();

        return redirect()
            ->route('login')
            ->with('registerSuccess', 'Tu cuenta se creó correctamente. Ahora puedes iniciar sesión.');
    }
}
