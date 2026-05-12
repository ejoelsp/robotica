<?php

namespace App\Modules\Admin\AsignacionJueces\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;

class UpdateAsignacionJuezRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    protected function prepareForValidation(): void
    {
        if (! $this->has('juez_principal_ids') && $this->filled('juez_principal_id')) {
            $this->merge([
                'juez_principal_ids' => [$this->input('juez_principal_id')],
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'categoria_id' => ['required', 'integer'],
            'juez_principal_ids' => ['required', 'array', 'min:1'],
            'juez_principal_ids.*' => ['integer'],
            'jueces_apoyo_ids' => ['nullable', 'array'],
            'jueces_apoyo_ids.*' => ['integer'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $categoriaId = (int) $this->input('categoria_id');

            $principales = collect($this->input('juez_principal_ids', []))
                ->filter(fn ($id) => filled($id))
                ->map(fn ($id) => (int) $id)
                ->values();

            $apoyos = collect($this->input('jueces_apoyo_ids', []))
                ->filter(fn ($id) => filled($id))
                ->map(fn ($id) => (int) $id)
                ->values();

            if (!$categoriaId || $principales->isEmpty()) {
                return;
            }

            $categoriaExiste = DB::table('catalogo.categorias')
                ->where('id', $categoriaId)
                ->exists();

            if (!$categoriaExiste) {
                $validator->errors()->add('categoria_id', 'La categoria seleccionada no existe.');
                return;
            }

            $competenciaId = DB::table('catalogo.categorias')
                ->where('id', $categoriaId)
                ->value('competencia_id');

            $config = DB::table('catalogo.config_jueces_competencia')
                ->where('competencia_id', $competenciaId)
                ->first();

            $juecesPrincipalesRequeridos = (int) ($config?->jueces_principales_requeridos ?? 1);
            $juecesApoyoRequeridos = (int) ($config?->jueces_apoyo_requeridos ?? 2);

            $rolJuezId = DB::table('seguridad.roles')
                ->where('nombre', 'juez')
                ->value('id');

            $validarJuez = function (string $field, int $juezId) use ($validator, $rolJuezId): bool {
                $usuario = DB::table('seguridad.users')
                    ->where('id', $juezId)
                    ->first();

                if (!$usuario) {
                    $validator->errors()->add($field, 'El juez seleccionado no existe.');
                    return false;
                }

                if ((int) $usuario->role_id !== (int) $rolJuezId) {
                    $validator->errors()->add($field, 'El usuario seleccionado no tiene rol de juez.');
                    return false;
                }

                return true;
            };

            foreach ($principales as $index => $principalId) {
                $validarJuez("juez_principal_ids.$index", $principalId);
            }

            foreach ($apoyos as $index => $apoyoId) {
                $validarJuez("jueces_apoyo_ids.$index", $apoyoId);
            }

            if ($principales->unique()->count() !== $principales->count()) {
                $validator->errors()->add('juez_principal_ids', 'No repitas jueces principales.');
            }

            if ($apoyos->unique()->count() !== $apoyos->count()) {
                $validator->errors()->add('jueces_apoyo_ids', 'No repitas jueces de apoyo.');
            }

            if ($apoyos->intersect($principales)->isNotEmpty()) {
                $validator->errors()->add(
                    'jueces_apoyo_ids',
                    'Un juez principal no puede repetirse como juez de apoyo.'
                );
            }

            if ($principales->count() !== $juecesPrincipalesRequeridos) {
                $validator->errors()->add(
                    'juez_principal_ids',
                    "Debes seleccionar exactamente {$juecesPrincipalesRequeridos} jueces principales."
                );
            }

            if ($apoyos->count() !== $juecesApoyoRequeridos) {
                $validator->errors()->add(
                    'jueces_apoyo_ids',
                    "Debes seleccionar exactamente {$juecesApoyoRequeridos} jueces de apoyo."
                );
            }
        });
    }

    public function messages(): array
    {
        return [
            'categoria_id.required' => 'La categoria es obligatoria.',
            'juez_principal_ids.required' => 'Selecciona los jueces principales.',
            'juez_principal_ids.array' => 'Los jueces principales deben enviarse como lista.',
            'jueces_apoyo_ids.array' => 'Los jueces de apoyo deben enviarse como lista.',
        ];
    }
}
