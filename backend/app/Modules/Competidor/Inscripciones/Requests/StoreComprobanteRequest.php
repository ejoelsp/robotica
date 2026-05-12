<?php

namespace App\Modules\Competidor\Inscripciones\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreComprobanteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'inscripcion_id' => [
                'nullable',
                'required_without:inscripcion_ids',
                'integer',
                Rule::exists('pgsql.vinculaciones.inscripciones', 'id'),
            ],
            'inscripcion_ids' => [
                'nullable',
                'required_without:inscripcion_id',
                'array',
                'min:1',
            ],
            'inscripcion_ids.*' => [
                'integer',
                Rule::exists('pgsql.vinculaciones.inscripciones', 'id'),
            ],

            'comprobante' => [
                'required',
                'file',
                'mimes:jpg,jpeg,png,pdf',
                'max:5120',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'inscripcion_id.required' => 'La inscripción es obligatoria.',
            'inscripcion_id.required_without' => 'Debes seleccionar al menos una inscripción.',
            'inscripcion_id.exists' => 'La inscripción seleccionada no existe.',
            'inscripcion_ids.required_without' => 'Debes seleccionar al menos una inscripción.',
            'inscripcion_ids.array' => 'Las inscripciones seleccionadas no son válidas.',
            'inscripcion_ids.min' => 'Debes seleccionar al menos una inscripción.',
            'inscripcion_ids.*.exists' => 'Una de las inscripciones seleccionadas no existe.',

            'comprobante.required' => 'Debes subir un comprobante.',
            'comprobante.file' => 'El comprobante debe ser un archivo válido.',
            'comprobante.mimes' => 'El comprobante debe estar en formato JPG, JPEG, PNG o PDF.',
            'comprobante.max' => 'El comprobante no puede superar los 5MB.',
        ];
    }
}
