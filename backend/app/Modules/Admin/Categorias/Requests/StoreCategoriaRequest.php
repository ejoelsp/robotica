<?php

namespace App\Modules\Admin\Categorias\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class StoreCategoriaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $nombre = (string) $this->input('nombre', '');

        $nombreKey = DB::scalar("
            select regexp_replace(
                lower(unaccent(trim(?))),
                '\\s+',
                ' ',
                'g'
            )
        ", [$nombre]);

        $this->merge([
            'nombre_key' => $nombreKey,
        ]);
    }

    public function rules(): array
    {
        return [
            'competencia_id' => ['required', 'integer'],
            'nombre' => ['required', 'string', 'max:150'],
            'nombre_key' => ['required', 'string', 'max:200'],
            'costo_inscripcion' => ['required', 'numeric', 'min:0', 'max:999999.99'],
            'max_integrantes' => ['required', 'integer', Rule::in([1, 2, 3, 4, 5])],
            'estado' => ['required', 'boolean'],
            'mecanismo_calificacion_id' => ['required', 'integer'],
            'unidad_resultado' => ['nullable', 'string', 'max:30'],
            'orden_ranking' => ['nullable', 'string', Rule::in(['asc', 'desc'])],
            'requiere_aprobacion_admin' => ['required', 'boolean'],
            'visible_publico_en_vivo' => ['required', 'boolean'],
            'permite_edicion_juez' => ['required', 'boolean'],
            'pdf' => ['required', 'file', 'mimes:pdf', 'max:10240'],
            'imagen' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $exists = DB::table('catalogo.categorias')
                ->where('competencia_id', $this->input('competencia_id'))
                ->where('nombre_key', $this->input('nombre_key'))
                ->exists();

            if ($exists) {
                $validator->errors()->add(
                    'nombre',
                    'Ya existe una categoría con ese nombre en esta competencia.'
                );
            }

            $mecanismoExiste = DB::table('catalogo.mecanismos_calificacion')
                ->where('id', $this->input('mecanismo_calificacion_id'))
                ->where('activo', true)
                ->exists();

            if (! $mecanismoExiste) {
                $validator->errors()->add(
                    'mecanismo_calificacion_id',
                    'Selecciona un mecanismo de calificación válido.'
                );
            }
        });
    }

    public function messages(): array
    {
        return [
            'competencia_id.required' => 'Falta la competencia.',
            'nombre.required' => 'Falta el nombre de la categoría.',
            'nombre_key.unique' => 'Ya existe una categoría con ese nombre en esta competencia.',
            'costo_inscripcion.required' => 'Falta el costo de inscripción.',
            'costo_inscripcion.numeric' => 'El costo de inscripción debe ser numérico.',
            'costo_inscripcion.min' => 'El costo de inscripción no puede ser menor a 0.',
            'costo_inscripcion.max' => 'El costo de inscripción no puede superar 999999.99.',
            'estado.required' => 'Falta el estado.',
            'mecanismo_calificacion_id.required' => 'Falta el mecanismo de calificación.',
            'orden_ranking.in' => 'El orden del ranking seleccionado no es válido.',
            'requiere_aprobacion_admin.required' => 'Falta definir si requiere aprobación del admin.',
            'visible_publico_en_vivo.required' => 'Falta definir la visibilidad pública en vivo.',
            'permite_edicion_juez.required' => 'Falta definir si el juez puede editar.',
            'pdf.required' => 'El PDF es obligatorio.',
            'pdf.mimes' => 'El archivo debe ser PDF.',
            'pdf.max' => 'El PDF no debe superar 10MB.',
        ];
    }
}
