<?php

namespace App\Modules\Admin\AsignacionJueces\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;

class UpdateConfigJuecesCompetenciaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'competencia_id' => ['required', 'integer', 'min:1'],
            'jueces_principales_requeridos' => ['required', 'integer', 'min:1', 'max:10'],
            'jueces_apoyo_requeridos' => ['required', 'integer', 'min:0', 'max:20'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $competenciaId = (int) $this->input('competencia_id');

            if (! $competenciaId) {
                return;
            }

            $exists = DB::table('catalogo.competencias')
                ->where('id', $competenciaId)
                ->exists();

            if (! $exists) {
                $validator->errors()->add('competencia_id', 'La competencia seleccionada no existe.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'competencia_id.required' => 'La competencia es obligatoria.',
            'jueces_principales_requeridos.required' => 'Define cuántos jueces principales requiere cada categoría.',
            'jueces_principales_requeridos.min' => 'Debe existir al menos un juez principal por categoría.',
            'jueces_apoyo_requeridos.required' => 'Define cuántos jueces de apoyo requiere cada categoría.',
            'jueces_apoyo_requeridos.min' => 'La cantidad de jueces de apoyo no puede ser negativa.',
        ];
    }
}
