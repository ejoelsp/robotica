<?php

namespace App\Modules\Competidor\Reclamos\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreReclamoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'inscripcion_id' => ['required', 'integer'],
            'descripcion' => ['required', 'string', 'min:10', 'max:3000'],
        ];
    }

    public function messages(): array
    {
        return [
            'inscripcion_id.required' => 'Selecciona una categoría aprobada.',
            'descripcion.required' => 'Escribe de forma puntual tu reclamo.',
            'descripcion.min' => 'El reclamo debe tener al menos 10 caracteres.',
            'descripcion.max' => 'El reclamo no debe superar los 3000 caracteres.',
        ];
    }
}
