<?php

namespace App\Modules\Admin\Inscripciones\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RejectComprobanteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'motivo' => ['required', 'string', 'max:150'],
            'observacion' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'motivo.required' => 'Debes seleccionar un motivo de rechazo.',
            'motivo.max' => 'El motivo no puede superar los 150 caracteres.',
            'observacion.max' => 'La observación no puede superar los 1000 caracteres.',
        ];
    }
}