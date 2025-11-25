<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:150',
                'regex:/^[\pL\s]+$/u', // solo letras y espacios
            ],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('users', 'email'),
            ],
            'telefono' => [
                'required',
                'string',
                'max:30',
                'regex:/^\+\d{8,20}$/', // +593 seguido de 8–20 dígitos
            ],
            'institucion' => [
                'required',
                'string',
                'max:150',
            ],
            'password' => [
                'required',
                'string',
                'min:8',
                'confirmed',
                'regex:/[A-Z]/',        // mayúscula
                'regex:/[a-z]/',        // minúscula
                'regex:/[0-9]/',        // número
                'regex:/[^A-Za-z0-9]/', // carácter especial
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'name.regex' => 'El nombre solo puede contener letras y espacios.',
            'email.unique' => 'Este correo ya está registrado.',
            'telefono.regex' => 'El teléfono debe incluir el prefijo (+) y solo dígitos.',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres.',
            'password.confirmed' => 'Las contraseñas no coinciden.',
            'password.regex' => 'La contraseña debe incluir mayúsculas, minúsculas, números y un carácter especial.',
        ];
    }
}
