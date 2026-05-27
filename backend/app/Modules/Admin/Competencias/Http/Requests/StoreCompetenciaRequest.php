<?php

namespace App\Modules\Admin\Competencias\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;

class StoreCompetenciaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // ya controlas con middleware EnsureRole
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

    public function store(StoreCompetenciaRequest $request)
    {
        $data = $request->validated();

        DB::transaction(function () use ($data) {
            // si esta nueva competencia viene como activa, apaga las demás
            if (!empty($data['estado'])) {
                DB::table('catalogo.competencias')->update(['estado' => false]);
            }

            Competencia::create($data);
        });

        return redirect()
            ->route('admin.competencias.index')
            ->with('success', 'Competencia creada correctamente.')
            ->setStatusCode(303);
    }

}
