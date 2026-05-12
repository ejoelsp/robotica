<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

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
                'max:30',
                'regex:/^[A-ZÑ\s]+$/',
            ],
            'last_name' => [
                'required',
                'string',
                'max:30',
                'regex:/^[A-ZÑ\s]+$/',
            ],
            'email' => [
                'required',
                'string',
                'email',
                'max:150',
            ],
            'telefono' => [
                'nullable',
                'string',
                'max:20',
                'regex:/^\+\d{8,20}$/',
            ],
            'password' => [
                'required',
                'string',
                'min:8',
                'confirmed',
                'regex:/[A-Z]/',
                'regex:/[a-z]/',
                'regex:/[0-9]/',
                'regex:/[^A-Za-z0-9]/',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'El nombre es obligatorio.',
            'name.regex' => 'El nombre solo puede contener letras mayúsculas sin tildes y la letra Ñ.',
            'last_name.required' => 'El apellido es obligatorio.',
            'last_name.regex' => 'El apellido solo puede contener letras mayúsculas sin tildes y la letra Ñ.',
            'telefono.regex' => 'El teléfono debe incluir el prefijo (+) y solo dígitos.',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres.',
            'password.confirmed' => 'Las contraseñas no coinciden.',
            'password.regex' => 'La contraseña debe incluir mayúsculas, minúsculas, números y un carácter especial.',
        ];
    }
}
