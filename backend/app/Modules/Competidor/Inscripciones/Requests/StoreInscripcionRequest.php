<?php

namespace App\Modules\Competidor\Inscripciones\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInscripcionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'competencia_id' => [
                'required',
                'integer',
                Rule::exists('pgsql.catalogo.competencias', 'id'),
            ],

            'categoria_id' => [
                'required',
                'integer',
                Rule::exists('pgsql.catalogo.categorias', 'id'),
            ],

            'institucion' => [
                'required',
                'string',
                'max:255',
            ],

            'nombre_equipo' => [
                'required',
                'string',
                'max:255',
            ],

            'nombre_capitan' => [
                'required',
                'string',
                'max:255',
            ],

            'nombre_prototipo' => [
                'required',
                'string',
                'max:255',
            ],

            'telefono_contacto' => [
                'required',
                'string',
                'max:30',
            ],

            'integrantes' => [
                'required',
                'array',
                'min:1',
            ],

            'integrantes.*' => [
                'required',
                'string',
                'max:255',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'competencia_id.required' => 'La competencia es obligatoria.',
            'competencia_id.exists' => 'La competencia seleccionada no existe.',

            'categoria_id.required' => 'La categoría es obligatoria.',
            'categoria_id.exists' => 'La categoría seleccionada no existe.',

            'institucion.required' => 'La institución o club es obligatoria.',
            'nombre_equipo.required' => 'El nombre del equipo es obligatorio.',
            'nombre_capitan.required' => 'El nombre del capitán es obligatorio.',
            'nombre_prototipo.required' => 'El nombre del prototipo es obligatorio.',
            'telefono_contacto.required' => 'El número de contacto es obligatorio.',

            'integrantes.required' => 'Debes registrar al menos un integrante.',
            'integrantes.array' => 'El formato de integrantes no es válido.',
            'integrantes.min' => 'Debes registrar al menos un integrante.',
            'integrantes.*.required' => 'Cada integrante debe tener un nombre.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $integrantes = $this->input('integrantes');

        if (is_string($integrantes)) {
            $integrantes = array_filter(
                array_map('trim', explode(',', $integrantes))
            );
        }

        if (!is_array($integrantes)) {
            $integrantes = [];
        }

        $this->merge([
            'institucion' => trim((string) $this->institucion),
            'nombre_equipo' => trim((string) $this->nombre_equipo),
            'nombre_capitan' => trim((string) $this->nombre_capitan),
            'nombre_prototipo' => trim((string) $this->nombre_prototipo),
            'telefono_contacto' => trim((string) $this->telefono_contacto),
            'integrantes' => array_values($integrantes),
        ]);
    }
}