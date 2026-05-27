<?php

namespace App\Modules\Competidor\Inscripciones\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
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
                'regex:/^[\p{L}\p{N} .-]+$/u',
            ],

            'nombre_equipo' => [
                'required',
                'string',
                'max:255',
                'regex:/^[\p{L}\p{N} -]+$/u',
            ],

            'nombre_capitan' => [
                'required',
                'string',
                'max:255',
                'regex:/^[\p{L} ]+$/u',
            ],

            'nombre_prototipo' => [
                'required',
                'string',
                'max:255',
                'regex:/^[\p{L}\p{N} .-]+$/u',
            ],

            'telefono_contacto' => [
                'required',
                'string',
                'max:30',
                'regex:/^\+\d{1,15}$/',
            ],

            'integrantes' => [
                'present',
                'array',
            ],

            'integrantes.*' => [
                'required',
                'string',
                'max:255',
                'regex:/^[\p{L} ]+$/u',
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

            'institucion.regex' => 'La institución o club solo permite letras, números, espacios, puntos y guiones.',
            'nombre_equipo.regex' => 'El nombre del equipo solo permite letras, números, espacios y guiones.',
            'nombre_capitan.regex' => 'El nombre del capitán solo permite letras y espacios.',
            'nombre_prototipo.regex' => 'El nombre del prototipo solo permite letras, números, espacios, guiones y puntos.',
            'telefono_contacto.regex' => 'El contacto debe iniciar con + y tener máximo 15 dígitos, sin espacios.',
            'integrantes.required' => 'Debes registrar al menos un integrante.',
            'integrantes.array' => 'El formato de integrantes no es válido.',
            'integrantes.min' => 'Debes registrar al menos un integrante.',
            'integrantes.*.required' => 'Cada integrante debe tener un nombre.',
            'integrantes.*.regex' => 'Los integrantes solo permiten letras y espacios.',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($validator->errors()->has('categoria_id')) {
                return;
            }

            $maxIntegrantes = (int) (
                DB::table('catalogo.categorias')
                    ->where('id', $this->integer('categoria_id'))
                    ->value('max_integrantes')
                ?? 2
            );

            $maxIntegrantes = max(1, min(5, $maxIntegrantes));
            $adicionalesRequeridos = $maxIntegrantes - 1;
            $integrantes = $this->input('integrantes', []);

            if (count($integrantes) !== $adicionalesRequeridos) {
                $validator->errors()->add(
                    'integrantes',
                    $adicionalesRequeridos === 0
                        ? 'Esta categoría solo requiere capitán.'
                        : "Debes registrar {$adicionalesRequeridos} integrante(s) adicional(es)."
                );
            }
        });
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
            'telefono_contacto' => preg_replace('/\s+/', '', trim((string) $this->telefono_contacto)),
            'integrantes' => array_values($integrantes),
        ]);
    }
}
