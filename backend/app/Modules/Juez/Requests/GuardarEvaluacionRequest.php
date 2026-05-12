<?php

namespace App\Modules\Juez\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GuardarEvaluacionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'ronda_id' => ['required', 'integer', 'min:1'],
            'equipo_id' => ['required', 'integer', 'min:1'],
            'version' => ['nullable', 'integer', 'min:0'],
            'observaciones' => ['nullable', 'string'],
            'motivo_cambio' => ['nullable', 'string', 'max:255'],
            'payload' => ['nullable', 'array'],
        ];
    }

    public function messages(): array
    {
        return [
            'ronda_id.required' => 'La ronda es obligatoria.',
            'equipo_id.required' => 'El equipo es obligatorio.',
            'payload.array' => 'Los datos de la evaluación deben enviarse como objeto.',
        ];
    }
}
