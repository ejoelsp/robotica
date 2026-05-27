<?php

namespace App\Modules\Admin\AsignacionJueces\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Models\User;

class StoreJuezRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name', '')),
            'last_name' => trim((string) $this->input('last_name', '')),
            'email' => mb_strtolower(trim((string) $this->input('email', ''))),
            'telefono' => trim((string) $this->input('telefono', '')),
        ]);
    }

    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100', 'regex:/^[\pL\s]+$/u'],
            'last_name' => ['required', 'string', 'max:100', 'regex:/^[\pL\s]+$/u'],
            'email' => [
                'required',
                'string',
                'email',
                'max:150',
                Rule::unique(User::class, 'email'),
            ],
            'telefono' => ['required', 'string', 'regex:/^\+\d{1,14}$/', 'max:15'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'El nombre es obligatorio.',
            'name.regex' => 'El nombre solo puede contener letras y espacios.',
            'last_name.required' => 'El apellido es obligatorio.',
            'last_name.regex' => 'El apellido solo puede contener letras y espacios.',
            'email.required' => 'El correo electrónico es obligatorio.',
            'email.email' => 'El correo electrónico no tiene un formato válido.',
            'email.unique' => 'Ya existe un usuario con ese correo.',
            'telefono.required' => 'El teléfono es obligatorio.',
            'telefono.regex' => 'El teléfono debe iniciar con + y contener solo números.',
            'telefono.max' => 'El teléfono no puede superar 15 caracteres incluido el signo +.',
        ];
    }
}
