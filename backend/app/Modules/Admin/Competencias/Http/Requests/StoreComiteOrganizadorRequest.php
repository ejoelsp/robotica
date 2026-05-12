<?php

namespace App\Modules\Admin\Competencias\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreComiteOrganizadorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nombres' => ['required', 'string', 'max:255'],
            'apellidos' => ['required', 'string', 'max:255'],
            'correo' => ['nullable', 'email', 'max:255'],
            'rol_comite' => ['required', 'string', 'max:255'],
            'foto' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'orden' => ['nullable', 'integer', 'min:0'],
            'estado' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'nombres.required' => 'Los nombres son obligatorios.',
            'apellidos.required' => 'Los apellidos son obligatorios.',
            'correo.email' => 'Ingresa un correo valido.',
            'rol_comite.required' => 'El rol dentro del comite es obligatorio.',
            'foto.image' => 'El archivo seleccionado debe ser una imagen valida.',
            'foto.mimes' => 'La foto debe estar en formato JPG, JPEG, PNG o WEBP.',
            'foto.max' => 'La foto no debe superar los 5 MB.',
            'orden.integer' => 'El orden debe ser un numero entero.',
            'orden.min' => 'El orden no puede ser negativo.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'nombres' => trim((string) $this->input('nombres', '')),
            'apellidos' => trim((string) $this->input('apellidos', '')),
            'correo' => $this->filled('correo') ? trim((string) $this->input('correo')) : null,
            'rol_comite' => trim((string) $this->input('rol_comite', '')),
            'orden' => $this->filled('orden') ? (int) $this->input('orden') : 0,
            'estado' => filter_var($this->input('estado', true), FILTER_VALIDATE_BOOL),
        ]);
    }
}
