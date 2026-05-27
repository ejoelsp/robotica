<?php

namespace App\Modules\Admin\Competencias\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;

class UpdateCompetenciaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:255'],
            'descripcion' => ['nullable', 'string', 'max:2000'],
            'fecha_inicio' => ['required', 'date'],
            'fecha_fin' => ['required', 'date', 'after_or_equal:fecha_inicio'],
            'enlace_evento' => ['required', 'string', 'max:500'],
            'tipo_competencia' => [
                'required',
                Rule::in(['Nacional', 'Internacional']),
            ],
            'imagen' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'estado' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'nombre.required' => 'El nombre es obligatorio.',
            'fecha_inicio.required' => 'La fecha de inicio es obligatoria.',
            'fecha_fin.required' => 'La fecha de fin es obligatoria.',
            'fecha_fin.after_or_equal' => 'La fecha de fin debe ser igual o posterior a la fecha de inicio.',
            'enlace_evento.required' => 'El enlace del evento es obligatorio.',
            'tipo_competencia.required' => 'El tipo de competencia es obligatorio.',
            'tipo_competencia.in' => 'El tipo de competencia debe ser Nacional o Internacional.',
            'imagen.image' => 'El archivo seleccionado debe ser una imagen válida.',
            'imagen.mimes' => 'La imagen debe estar en formato JPG, JPEG, PNG o WEBP.',
            'imagen.max' => 'La imagen no debe superar los 5 MB.',
            'logo.image' => 'El logo seleccionado debe ser una imagen válida.',
            'logo.mimes' => 'El logo debe estar en formato JPG, JPEG, PNG o WEBP.',
            'logo.max' => 'El logo no debe superar los 5 MB.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $enlace = trim((string) $this->input('enlace_evento', ''));
        if ($enlace && !preg_match('/^https?:\/\//i', $enlace)) {
            $enlace = 'https://' . $enlace;
        }

        $this->merge([
            'enlace_evento' => $enlace,
            'estado' => filter_var($this->input('estado', false), FILTER_VALIDATE_BOOL),
        ]);
    }

    public function update(UpdateCompetenciaRequest $request, int $id)
    {
        $competencia = Competencia::findOrFail($id);
        $data = $request->validated();

        DB::transaction(function () use ($competencia, $data) {
            if (!empty($data['estado'])) {
                DB::table('catalogo.competencias')->where('id', '!=', $competencia->id)->update(['estado' => false]);
            }

            $competencia->update($data);
        });

        return redirect()
            ->route('admin.competencias.index')
            ->with('success', 'Competencia actualizada correctamente.')
            ->setStatusCode(303);
    }

    public function toggle(int $id)
    {
        try{
            $competencia = Competencia::findOrFail($id);

            DB::transaction(function () use ($competencia) {
                $esUnicaActiva = $competencia->estado
                    && \DB::table('catalogo.competencias')->where('estado', true)->count() === 1;

                // No permitir apagar la última activa
                if ($esUnicaActiva) {
                    throw new \RuntimeException('Debe existir al menos una competencia activa.');
                }

                $nuevoEstado = !$competencia->estado;

                // Si la activas, apaga todas las demás
                if ($nuevoEstado) {
                    \DB::table('catalogo.competencias')->update(['estado' => false]);
                }

                $competencia->update(['estado' => $nuevoEstado]);
            });

            return redirect()
                ->route('admin.competencias.index')
                ->with('success', 'Evento principal actualizado.')
                ->setStatusCode(303);
            
        
        }catch (\RuntimeException $e) {
            return redirect()
                ->route('admin.competencias.index')
                ->with('error', $e->getMessage())
                ->setStatusCode(303);
        }
    }
}
