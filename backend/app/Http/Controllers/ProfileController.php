<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Mostrar la vista de perfil.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('Profile', [
            // En Vue lo lees como page.props.user o page.props.auth.user
            'user' => $request->user(),
        ]);
    }

    /**
     * Actualizar datos básicos del perfil.
     */
    public function update(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:30',                // 
                'regex:/^[A-ZÑ\s]+$/',   // SOLO MAYÚSCULAS, Ñ y espacios
            ],
            'last_name' => [
                'required',
                'string',
                'max:30',
                'regex:/^[A-ZÑ\s]+$/',
            ],
            'telefono' => [
                'required',
                'string',
                'max:20',
                'regex:/^\+\d{8,20}$/',  // + y de 8 a 20 dígitos
            ],
            'institucion' => [
                'required',
                'string',
                'max:100',
                'regex:/^[A-ZÑ\s]+$/',
            ],
        ], [
            'name.required'       => 'El nombre es obligatorio.',
            'name.regex'          => 'El nombre solo puede contener letras mayúsculas sin tildes y la letra Ñ.',
            'last_name.required'  => 'El apellido es obligatorio.',
            'last_name.regex'     => 'El apellido solo puede contener letras mayúsculas sin tildes y la letra Ñ.',
            'telefono.required'   => 'El teléfono es obligatorio.',
            'telefono.regex'      => 'El teléfono debe incluir el prefijo (+) y solo dígitos, por ejemplo +593991234567.',
            'institucion.required'=> 'La institución es obligatoria.',
            'institucion.regex'   => 'La institución solo puede contener letras mayúsculas sin tildes y la letra Ñ.',
        ]);

        // Aquí asumimos que el front ya manda todo en mayúsculas y sin tildes,
        // tal como se registró. Solo actualizamos.
        $user->update($validated);

        return back()->with('success', 'Perfil actualizado correctamente.');
    }

    /**
     * Actualizar contraseña del usuario.
     */
    public function updatePassword(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'current_password' => ['required'],
            'password'         => [
                'required',
                'confirmed',
                'min:8',
                'different:current_password', // nueva != actual (texto ingresado)
            ],
        ], [
            'current_password.required' => 'Debes ingresar tu contraseña actual.',
            'password.required'         => 'Debes ingresar una nueva contraseña.',
            'password.confirmed'        => 'La confirmación de la nueva contraseña no coincide.',
            'password.min'              => 'La nueva contraseña debe tener al menos 8 caracteres.',
            'password.different'        => 'La nueva contraseña debe ser diferente a la contraseña actual.',
        ]);

        // Verificar que la contraseña actual sea correcta
        if (! Hash::check($validated['current_password'], $user->password)) {
            return back()->withErrors([
                'current_password' => 'La contraseña actual no es correcta.',
            ]);
        }

        // Actualizar contraseña (ya viene validada y distinta de la actual en texto)
        $user->password = Hash::make($validated['password']);
        $user->save();

        return back()->with('success', 'Contraseña actualizada correctamente.');
    }
}
