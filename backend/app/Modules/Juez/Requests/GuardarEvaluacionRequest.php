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
            'inscripcion_id' => ['required', 'integer', 'min:1'],
            'intento_numero' => ['nullable', 'integer', 'min:1', 'max:10'],
            'expected_juez_user_id' => ['nullable', 'integer', 'min:1'],
            'version' => ['nullable', 'integer', 'min:0'],
            'observaciones' => ['nullable', 'string'],
            'motivo_cambio' => ['nullable', 'string', 'max:255'],
            'payload' => ['nullable', 'array'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $expectedJudgeId = $this->integer('expected_juez_user_id');
            $currentJudgeId = (int) ($this->user()?->id ?? 0);

            if ($expectedJudgeId > 0 && $currentJudgeId > 0 && $expectedJudgeId !== $currentJudgeId) {
                $validator->errors()->add(
                    'expected_juez_user_id',
                    'La sesion activa cambio de juez. Recarga la pagina e inicia sesion con el juez correcto antes de guardar.'
                );
            }
        });
    }

    public function messages(): array
    {
        return [
            'ronda_id.required' => 'La ronda es obligatoria.',
            'inscripcion_id.required' => 'El participante es obligatorio.',
            'payload.array' => 'Los datos de la evaluación deben enviarse como objeto.',
        ];
    }
}
