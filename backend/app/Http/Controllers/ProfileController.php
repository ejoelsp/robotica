<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    public function edit(Request $request): Response
    {
        return Inertia::render('Profile', [
            'user' => $request->user(),
        ]);
    }

    public function update(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:30', 'regex:/^[\pL\s]+$/u'],
            'last_name' => ['required', 'string', 'max:30', 'regex:/^[\pL\s]+$/u'],
            'telefono' => ['required', 'string', 'max:15', 'regex:/^\+\d{1,14}$/'],
        ], [
            'name.required' => 'El nombre es obligatorio.',
            'name.regex' => 'El nombre solo puede contener letras y espacios.',
            'last_name.required' => 'El apellido es obligatorio.',
            'last_name.regex' => 'El apellido solo puede contener letras y espacios.',
            'telefono.required' => 'El teléfono es obligatorio.',
            'telefono.max' => 'El teléfono no puede superar 15 caracteres incluyendo el signo +.',
            'telefono.regex' => 'El teléfono debe iniciar con + y contener solo números después del signo.',
        ]);

        $user->update($validated);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Perfil actualizado correctamente.',
                'user' => $this->profileUserPayload($user->fresh()),
            ]);
        }

        return back()->with('success', 'Perfil actualizado correctamente.');
    }

    public function updatePhoto(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ], [
            'photo.required' => 'Selecciona una foto de perfil.',
            'photo.image' => 'El archivo debe ser una imagen válida.',
            'photo.mimes' => 'La foto debe estar en formato JPG, PNG o WEBP.',
            'photo.max' => 'La foto no puede superar los 4 MB.',
        ]);

        if ($user->photo_path && Storage::disk('public')->exists($user->photo_path)) {
            Storage::disk('public')->delete($user->photo_path);
        }

        $user->photo_path = $validated['photo']->store('usuarios/perfiles', 'public');
        $user->save();

        return back()->with('success', 'Foto de perfil actualizada correctamente.');
    }

    public function updatePassword(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'current_password' => ['required'],
            'password' => [
                'required',
                'confirmed',
                'min:8',
                'regex:/[A-Z]/',
                'regex:/[a-z]/',
                'regex:/[0-9]/',
                'regex:/[!@#$%^&*(),.?":{}|<>\_\-+=\[\]\\\\;~]/',
                'different:current_password',
            ],
        ], [
            'current_password.required' => 'Debes ingresar tu contraseña actual.',
            'password.required' => 'Debes ingresar una nueva contraseña.',
            'password.confirmed' => 'La confirmación de la nueva contraseña no coincide.',
            'password.min' => 'La nueva contraseña debe tener al menos 8 caracteres.',
            'password.regex' => 'La nueva contraseña debe incluir mayúsculas, minúsculas, números y un carácter especial.',
            'password.different' => 'La nueva contraseña debe ser diferente a la contraseña actual.',
        ]);

        if (! Hash::check($validated['current_password'], $user->password)) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'La contraseña actual no es correcta.',
                    'errors' => [
                        'current_password' => ['La contraseña actual no es correcta.'],
                    ],
                ], 422);
            }

            return back()->withErrors([
                'current_password' => 'La contraseña actual no es correcta.',
            ]);
        }

        $user->password = Hash::make($validated['password']);
        $user->save();

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Contraseña actualizada correctamente.',
            ]);
        }

        return back()->with('success', 'Contraseña actualizada correctamente.');
    }

    private function profileUserPayload($user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'telefono' => $user->telefono,
            'role_id' => $user->role_id,
            'photo_path' => $user->photo_path,
            'photo_url' => $user->photo_path ? Storage::url($user->photo_path) : null,
        ];
    }
}
