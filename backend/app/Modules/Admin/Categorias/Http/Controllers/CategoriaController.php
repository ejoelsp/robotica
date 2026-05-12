<?php

namespace App\Modules\Admin\Categorias\Http\Controllers;

use App\Models\Categoria;
use App\Models\ConfigCalificacion;
use App\Models\Ronda;
use App\Modules\Admin\Categorias\Requests\StoreCategoriaRequest;
use App\Modules\Admin\Categorias\Requests\UpdateCategoriaRequest;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class CategoriaController
{
    public function index(Request $request)
    {
        $competenciaId = (int) $request->query('competencia_id', 0);
        $defaultCompetenciaId = (int) (
            DB::table('catalogo.competencias')
                ->where('estado', true)
                ->orderBy('id')
                ->value('id')
            ?? DB::table('catalogo.competencias')->orderBy('id')->value('id')
        );

        if (! $competenciaId) {
            $competenciaId = $defaultCompetenciaId;
        }

        $competenciaExiste = DB::table('catalogo.competencias')
            ->where('id', $competenciaId)
            ->exists();

        if ($competenciaId && $competenciaId !== $request->integer('competencia_id') || ! $competenciaExiste) {
            $competenciaId = $defaultCompetenciaId;
        }

        if ($competenciaId > 0 && $request->integer('competencia_id') !== $competenciaId) {
            return redirect("/admin/categorias?competencia_id={$competenciaId}", 303);
        }

        $competencias = DB::table('catalogo.competencias')
            ->select('id', 'nombre', 'estado')
            ->orderByDesc('estado')
            ->orderBy('id')
            ->get()
            ->map(fn ($c) => [
                'id' => (int) $c->id,
                'nombre' => (string) $c->nombre,
                'es_principal' => (bool) $c->estado,
            ]);

        $cats = Categoria::query()
            ->with(['configCalificacion.mecanismo'])
            ->withCount('inscripciones')
            ->where('competencia_id', $competenciaId)
            ->orderByDesc('id')
            ->get()
            ->map(fn ($c) => [
                'id' => $c->id,
                'nombre' => $c->nombre,
                'nombre_key' => $c->nombre_key,
                'costo_inscripcion' => (float) $c->costo_inscripcion,
                'estado' => (bool) $c->estado,
                'inscripciones_count' => (int) $c->inscripciones_count,
                'has_participantes' => (int) $c->inscripciones_count > 0,
                'rondas_count' => $c->rondas()->count(),
                'reglamento_url' => $c->reglamento ? Storage::url($c->reglamento) : null,
                'imagen_url' => $c->imagen ? Storage::url($c->imagen) : null,
                'config_calificacion' => $c->configCalificacion ? [
                    'id' => $c->configCalificacion->id,
                    'mecanismo_calificacion_id' => $c->configCalificacion->mecanismo_calificacion_id,
                    'mecanismo_codigo' => $c->configCalificacion->mecanismo?->codigo,
                    'mecanismo_nombre' => $c->configCalificacion->mecanismo?->nombre,
                    'unidad_resultado' => $c->configCalificacion->unidad_resultado,
                    'orden_ranking' => $c->configCalificacion->orden_ranking,
                    'requiere_aprobacion_admin' => (bool) $c->configCalificacion->requiere_aprobacion_admin,
                    'visible_publico_en_vivo' => (bool) $c->configCalificacion->visible_publico_en_vivo,
                    'permite_edicion_juez' => (bool) $c->configCalificacion->permite_edicion_juez,
                    'campos_json' => $c->configCalificacion->campos_json ?? [],
                    'reglas_json' => $c->configCalificacion->reglas_json ?? [],
                ] : null,
            ]);

        $mecanismos = DB::table('catalogo.mecanismos_calificacion')
            ->where('activo', true)
            ->orderBy('nombre')
            ->get()
            ->map(fn ($m) => [
                'id' => (int) $m->id,
                'codigo' => (string) $m->codigo,
                'nombre' => (string) $m->nombre,
                'descripcion' => $m->descripcion ? (string) $m->descripcion : null,
            ])
            ->values();

        return Inertia::render('Admin/Categorias', [
            'competenciaId' => $competenciaId,
            'competencias' => $competencias,
            'categorias' => $cats,
            'mecanismosCalificacion' => $mecanismos,
        ]);
    }

    public function store(StoreCategoriaRequest $request)
    {
        try {
            DB::transaction(function () use ($request) {
                $data = [
                    'competencia_id' => $request->integer('competencia_id'),
                    'nombre' => (string) $request->string('nombre'),
                    'nombre_key' => (string) $request->input('nombre_key'),
                    'costo_inscripcion' => (float) $request->input('costo_inscripcion'),
                    'estado' => (bool) $request->boolean('estado'),
                    'reglamento' => null,
                    'imagen' => null,
                ];

                if ($request->hasFile('pdf')) {
                    $data['reglamento'] = $request->file('pdf')->store('reglamentos', 'public');
                }

                if ($request->hasFile('imagen')) {
                    $data['imagen'] = $request->file('imagen')->store('categorias', 'public');
                }

                $categoria = Categoria::create($data);

                $this->upsertConfigCalificacion($categoria, $request);
            });

            return back()->with('success', 'Categoría creada correctamente.');
        } catch (QueryException $e) {
            if (($e->errorInfo[0] ?? null) === '23505') {
                throw ValidationException::withMessages([
                    'nombre' => 'Ya existe una categoría con ese nombre en esta competencia.',
                ]);
            }

            throw $e;
        }
    }

    public function update(UpdateCategoriaRequest $request, $id)
    {
        $cat = Categoria::findOrFail($id);
        $inscripcionesCount = $cat->inscripciones()->count();

        if (! $request->boolean('estado') && $inscripcionesCount > 0) {
            throw ValidationException::withMessages([
                'estado' => 'No se puede desactivar la categoría porque tiene participantes registrados.',
            ]);
        }

        try {
            DB::transaction(function () use ($request, $cat) {
                $data = [
                    'competencia_id' => $request->integer('competencia_id'),
                    'nombre' => (string) $request->string('nombre'),
                    'nombre_key' => (string) $request->input('nombre_key'),
                    'costo_inscripcion' => (float) $request->input('costo_inscripcion'),
                    'estado' => (bool) $request->boolean('estado'),
                ];

                if ($request->hasFile('pdf')) {
                    if ($cat->reglamento && Storage::disk('public')->exists($cat->reglamento)) {
                        Storage::disk('public')->delete($cat->reglamento);
                    }

                    $data['reglamento'] = $request->file('pdf')->store('reglamentos', 'public');
                }

                if ($request->hasFile('imagen')) {
                    if ($cat->imagen && Storage::disk('public')->exists($cat->imagen)) {
                        Storage::disk('public')->delete($cat->imagen);
                    }

                    $data['imagen'] = $request->file('imagen')->store('categorias', 'public');
                }

                $cat->update($data);

                $this->upsertConfigCalificacion($cat, $request);
            });

            return back()->with('success', 'Categoría actualizada correctamente.');
        } catch (QueryException $e) {
            if (($e->errorInfo[0] ?? null) === '23505') {
                throw ValidationException::withMessages([
                    'nombre' => 'Categoría ya registrada en esta competencia.',
                ]);
            }

            throw $e;
        }
    }

    public function updateFormato(Request $request, int $categoriaId)
    {
        $categoria = Categoria::findOrFail($categoriaId);

        $validated = $request->validate([
            'mecanismo_codigo' => ['required', 'string', 'in:registro_resultado,tabla_evaluacion'],
            'unidad_resultado' => ['nullable', 'string', 'max:30'],
            'orden_ranking' => ['required', 'string', 'in:asc,desc'],
            'requiere_aprobacion_admin' => ['required', 'boolean'],
            'visible_publico_en_vivo' => ['required', 'boolean'],
            'permite_edicion_juez' => ['required', 'boolean'],
            'tipo_registro' => ['required', 'string', 'in:registro_resultado,tabla_evaluacion'],
            'plantilla_resultado' => ['nullable', 'string', 'in:tiempo,goles,puntaje,ganador,tabla_individual_criterios,tabla_enfrentamiento_criterios,personalizado'],
            'modalidad_competencia' => ['required', 'string', 'in:participacion_individual,enfrentamiento_directo'],
            'esquema_jueces' => ['required', 'string', 'in:registro_cualquier_juez,evaluacion_multi_juez,registro_por_rol'],
            'campos_json' => ['required', 'array', 'min:1'],
            'campos_json.*.key' => ['required', 'string', 'max:80', 'regex:/^[a-z0-9_]+$/'],
            'campos_json.*.type' => ['required', 'string', 'in:number,duration,text,textarea,select,checkbox,boolean'],
            'campos_json.*.label' => ['required', 'string', 'max:120'],
            'campos_json.*.required' => ['nullable', 'boolean'],
            'campos_json.*.max' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'campos_json.*.valor_unitario' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'campos_json.*.es_penalizacion' => ['nullable', 'boolean'],
            'campos_json.*.options' => ['nullable', 'array'],
        ], [
            'mecanismo_codigo.required' => 'Selecciona el formato de registro.',
            'campos_json.required' => 'Define al menos un campo para el juez.',
            'campos_json.*.key.regex' => 'La clave solo puede contener letras minusculas, numeros y guion bajo.',
        ]);

        $mecanismo = DB::table('catalogo.mecanismos_calificacion')
            ->where('codigo', $validated['mecanismo_codigo'])
            ->where('activo', true)
            ->first();

        if (! $mecanismo) {
            throw ValidationException::withMessages([
                'mecanismo_codigo' => 'El formato seleccionado no esta disponible.',
            ]);
        }

        $campos = collect($validated['campos_json'])
            ->map(fn (array $campo) => [
                'key' => (string) $campo['key'],
                'type' => (string) $campo['type'],
                'label' => (string) $campo['label'],
                'required' => (bool) ($campo['required'] ?? false),
                ...isset($campo['max']) && $campo['max'] !== null ? ['max' => (float) $campo['max']] : [],
                ...isset($campo['valor_unitario']) && $campo['valor_unitario'] !== null ? ['valor_unitario' => (float) $campo['valor_unitario']] : [],
                ...isset($campo['es_penalizacion']) ? ['es_penalizacion' => (bool) $campo['es_penalizacion']] : [],
                ...isset($campo['options']) && is_array($campo['options']) ? ['options' => $campo['options']] : [],
            ])
            ->values()
            ->all();

        ConfigCalificacion::updateOrCreate(
            ['categoria_id' => $categoria->id],
            [
                'mecanismo_calificacion_id' => (int) $mecanismo->id,
                'unidad_resultado' => $validated['unidad_resultado'] ?? null,
                'orden_ranking' => (string) $validated['orden_ranking'],
                'requiere_aprobacion_admin' => (bool) $validated['requiere_aprobacion_admin'],
                'visible_publico_en_vivo' => (bool) $validated['visible_publico_en_vivo'],
                'permite_edicion_juez' => (bool) $validated['permite_edicion_juez'],
                'campos_json' => $campos,
                'reglas_json' => [
                    'ranking' => [
                        'order' => (string) $validated['orden_ranking'],
                        'unit' => $validated['unidad_resultado'] ?? null,
                    ],
                    'workflow' => [
                        'requiere_aprobacion_admin' => (bool) $validated['requiere_aprobacion_admin'],
                        'visible_publico_en_vivo' => (bool) $validated['visible_publico_en_vivo'],
                        'permite_edicion_juez' => (bool) $validated['permite_edicion_juez'],
                    ],
                    'registro' => [
                        'tipo_registro' => (string) $validated['tipo_registro'],
                        'plantilla_resultado' => (string) ($validated['plantilla_resultado'] ?? (
                            $validated['tipo_registro'] === 'tabla_evaluacion' ? 'tabla_individual_criterios' : 'tiempo'
                        )),
                        'modalidad_competencia' => (string) $validated['modalidad_competencia'],
                        'esquema_jueces' => (string) $validated['esquema_jueces'],
                    ],
                ],
            ]
        );

        return response()->json([
            'message' => 'Formato de registro actualizado correctamente.',
        ]);
    }

    public function destroy($id): RedirectResponse
    {
        $cat = Categoria::findOrFail($id);
        $competenciaId = (int) $cat->competencia_id;
        $inscripcionesCount = $cat->inscripciones()->count();

        if ($inscripcionesCount > 0) {
            return redirect("/admin/categorias?competencia_id={$competenciaId}", 303)
                ->with('error', 'No se puede eliminar la categoría porque tiene participantes registrados.');
        }

        if ($cat->reglamento && Storage::disk('public')->exists($cat->reglamento)) {
            Storage::disk('public')->delete($cat->reglamento);
        }

        if ($cat->imagen && Storage::disk('public')->exists($cat->imagen)) {
            Storage::disk('public')->delete($cat->imagen);
        }

        $cat->delete();

        return redirect("/admin/categorias?competencia_id={$competenciaId}", 303)
            ->with('success', 'Categoría eliminada correctamente.');
    }

    public function rondas(int $categoriaId)
    {
        $categoria = Categoria::query()
            ->withCount('inscripciones')
            ->findOrFail($categoriaId);

        $rondas = Ronda::query()
            ->where('categoria_id', $categoria->id)
            ->withCount('resultados')
            ->orderByRaw('fecha_hora IS NULL')
            ->orderBy('fecha_hora')
            ->orderBy('id')
            ->get()
            ->map(fn (Ronda $ronda) => [
                'id' => (int) $ronda->id,
                'categoria_id' => (int) $ronda->categoria_id,
                'nombre' => (string) $ronda->nombre,
                'tipo' => (string) ($ronda->tipo ?? 'libre'),
                'estado' => (string) ($ronda->estado ?? 'borrador'),
                'fecha_hora' => optional($ronda->fecha_hora)?->format('Y-m-d\TH:i'),
                'resultados_count' => (int) $ronda->resultados_count,
                'has_resultados' => (int) $ronda->resultados_count > 0,
            ])
            ->values();

        return response()->json([
            'categoria' => [
                'id' => (int) $categoria->id,
                'nombre' => (string) $categoria->nombre,
                'inscripciones_count' => (int) $categoria->inscripciones_count,
            ],
            'rondas' => $rondas,
        ]);
    }

    public function storeRonda(Request $request, int $categoriaId)
    {
        $categoria = Categoria::findOrFail($categoriaId);
        $data = $this->validateRonda($request);

        $ronda = Ronda::create([
            'categoria_id' => $categoria->id,
            ...$data,
        ]);

        return response()->json([
            'message' => 'Ronda creada correctamente.',
            'ronda' => $ronda,
        ], 201);
    }

    public function updateRonda(Request $request, int $categoriaId, int $rondaId)
    {
        Categoria::findOrFail($categoriaId);
        $ronda = Ronda::query()
            ->where('categoria_id', $categoriaId)
            ->findOrFail($rondaId);

        $ronda->update($this->validateRonda($request));

        return response()->json([
            'message' => 'Ronda actualizada correctamente.',
        ]);
    }

    public function destroyRonda(int $categoriaId, int $rondaId)
    {
        Categoria::findOrFail($categoriaId);
        $ronda = Ronda::query()
            ->where('categoria_id', $categoriaId)
            ->withCount('resultados')
            ->findOrFail($rondaId);

        if ((int) $ronda->resultados_count > 0) {
            throw ValidationException::withMessages([
                'ronda' => 'No se puede eliminar una ronda que ya tiene evaluaciones registradas.',
            ]);
        }

        $ronda->delete();

        return response()->json([
            'message' => 'Ronda eliminada correctamente.',
        ]);
    }

    private function upsertConfigCalificacion(Categoria $categoria, Request $request): void
    {
        $mecanismo = DB::table('catalogo.mecanismos_calificacion')
            ->where('id', $request->integer('mecanismo_calificacion_id'))
            ->first();

        $codigo = (string) ($mecanismo->codigo ?? '');

        $campos = $this->camposPorMecanismo($codigo);

        $reglas = [
            'ranking' => [
                'order' => (string) $request->input('orden_ranking', 'desc'),
                'unit' => $request->filled('unidad_resultado')
                    ? (string) $request->input('unidad_resultado')
                    : null,
            ],
            'workflow' => [
                'requiere_aprobacion_admin' => (bool) $request->boolean('requiere_aprobacion_admin'),
                'visible_publico_en_vivo' => (bool) $request->boolean('visible_publico_en_vivo'),
                'permite_edicion_juez' => (bool) $request->boolean('permite_edicion_juez'),
            ],
            'registro' => [
                'tipo_registro' => in_array($codigo, ['tabla_evaluacion', 'puntaje', 'puntaje_jueces', 'dron_destreza', 'mixto'], true)
                    ? 'tabla_evaluacion'
                    : 'registro_resultado',
                'modalidad_competencia' => in_array($codigo, ['combate', 'combate_llaves', 'soccer_goles'], true)
                    ? 'enfrentamiento_directo'
                    : 'participacion_individual',
                'esquema_jueces' => 'registro_cualquier_juez',
            ],
        ];

        ConfigCalificacion::updateOrCreate(
            ['categoria_id' => $categoria->id],
            [
                'mecanismo_calificacion_id' => $request->integer('mecanismo_calificacion_id'),
                'unidad_resultado' => $request->filled('unidad_resultado')
                    ? (string) $request->input('unidad_resultado')
                    : null,
                'orden_ranking' => (string) $request->input('orden_ranking', 'desc'),
                'requiere_aprobacion_admin' => (bool) $request->boolean('requiere_aprobacion_admin'),
                'visible_publico_en_vivo' => (bool) $request->boolean('visible_publico_en_vivo'),
                'permite_edicion_juez' => (bool) $request->boolean('permite_edicion_juez'),
                'campos_json' => $campos,
                'reglas_json' => $reglas,
            ]
        );
    }

    private function camposPorMecanismo(string $codigo): array
    {
        return match ($codigo) {
            'registro_resultado' => [
                ['key' => 'valor_principal', 'type' => 'number', 'label' => 'Resultado final', 'required' => true],
                ['key' => 'penalizaciones', 'type' => 'number', 'label' => 'Penalizaciones', 'required' => false],
                ['key' => 'observaciones', 'type' => 'textarea', 'label' => 'Observaciones', 'required' => false],
            ],
            'tabla_evaluacion' => [
                ['key' => 'criterio_1', 'type' => 'number', 'label' => 'Criterio 1', 'required' => true, 'max' => 10],
                ['key' => 'criterio_2', 'type' => 'number', 'label' => 'Criterio 2', 'required' => true, 'max' => 10],
                ['key' => 'observaciones', 'type' => 'textarea', 'label' => 'Observaciones', 'required' => false],
            ],
            'cronometro' => [
                ['key' => 'tiempo', 'type' => 'duration', 'label' => 'Tiempo final', 'required' => true],
                ['key' => 'penalizaciones', 'type' => 'number', 'label' => 'Penalizaciones', 'required' => false],
                ['key' => 'distancia_avanzada', 'type' => 'number', 'label' => 'Distancia avanzada', 'required' => false],
                ['key' => 'completo_si_no', 'type' => 'checkbox', 'label' => 'Recorrido completo', 'required' => false],
                ['key' => 'observaciones', 'type' => 'textarea', 'label' => 'Observaciones', 'required' => false],
            ],
            'puntaje', 'puntaje_jueces' => [
                ['key' => 'puntaje', 'type' => 'number', 'label' => 'Puntaje', 'required' => true],
                ['key' => 'penalizaciones', 'type' => 'number', 'label' => 'Penalizaciones', 'required' => false],
                ['key' => 'observaciones', 'type' => 'textarea', 'label' => 'Observaciones', 'required' => false],
            ],
            'combate' => [
                ['key' => 'victorias', 'type' => 'number', 'label' => 'Victorias', 'required' => true],
                ['key' => 'derrotas', 'type' => 'number', 'label' => 'Derrotas', 'required' => false],
                ['key' => 'penalizaciones', 'type' => 'number', 'label' => 'Penalizaciones', 'required' => false],
                ['key' => 'observaciones', 'type' => 'textarea', 'label' => 'Observaciones', 'required' => false],
            ],
            'combate_llaves' => [
                [
                    'key' => 'resultado',
                    'type' => 'select',
                    'label' => 'Resultado',
                    'required' => true,
                    'options' => [
                        ['value' => 'victoria', 'label' => 'Victoria'],
                        ['value' => 'derrota', 'label' => 'Derrota'],
                        ['value' => 'empate', 'label' => 'Empate'],
                    ],
                ],
                ['key' => 'puntos', 'type' => 'number', 'label' => 'Puntos', 'required' => false],
                ['key' => 'amonestaciones', 'type' => 'number', 'label' => 'Amonestaciones', 'required' => false],
                ['key' => 'descalificado', 'type' => 'checkbox', 'label' => 'Descalificado', 'required' => false],
                [
                    'key' => 'metodo_victoria',
                    'type' => 'select',
                    'label' => 'Metodo de victoria',
                    'required' => false,
                    'options' => [
                        ['value' => 'expulsion', 'label' => 'Expulsion'],
                        ['value' => 'ko', 'label' => 'KO'],
                        ['value' => 'inmovilidad', 'label' => 'Inmovilidad'],
                        ['value' => 'abandono', 'label' => 'Abandono'],
                        ['value' => 'decision_juez', 'label' => 'Decision juez'],
                    ],
                ],
                ['key' => 'observaciones', 'type' => 'textarea', 'label' => 'Observaciones', 'required' => false],
            ],
            'soccer_goles' => [
                ['key' => 'goles_favor', 'type' => 'number', 'label' => 'Goles a favor', 'required' => true],
                ['key' => 'goles_contra', 'type' => 'number', 'label' => 'Goles en contra', 'required' => true],
                ['key' => 'faltas', 'type' => 'number', 'label' => 'Faltas', 'required' => false],
                ['key' => 'amonestaciones', 'type' => 'number', 'label' => 'Amonestaciones', 'required' => false],
                ['key' => 'observaciones', 'type' => 'textarea', 'label' => 'Observaciones', 'required' => false],
            ],
            'dron_carrera' => [
                ['key' => 'tiempo', 'type' => 'duration', 'label' => 'Tiempo final', 'required' => true],
                ['key' => 'obstaculos_no_superados', 'type' => 'number', 'label' => 'Obstaculos no superados', 'required' => false],
                ['key' => 'penalizaciones_segundos', 'type' => 'number', 'label' => 'Penalizacion en segundos', 'required' => false],
                ['key' => 'completo_si_no', 'type' => 'checkbox', 'label' => 'Recorrido completo', 'required' => false],
                ['key' => 'porcentaje_recorrido', 'type' => 'number', 'label' => 'Porcentaje recorrido', 'required' => false],
                ['key' => 'observaciones', 'type' => 'textarea', 'label' => 'Observaciones', 'required' => false],
            ],
            'dron_destreza' => [
                ['key' => 'puntaje', 'type' => 'number', 'label' => 'Puntaje', 'required' => true],
                ['key' => 'obstaculos_superados', 'type' => 'number', 'label' => 'Obstaculos superados', 'required' => false],
                ['key' => 'penalizaciones', 'type' => 'number', 'label' => 'Penalizaciones', 'required' => false],
                ['key' => 'observaciones', 'type' => 'textarea', 'label' => 'Observaciones', 'required' => false],
            ],
            'mixto' => [
                ['key' => 'puntaje', 'type' => 'number', 'label' => 'Puntaje', 'required' => true],
                ['key' => 'tiempo', 'type' => 'duration', 'label' => 'Tiempo', 'required' => false],
                ['key' => 'penalizaciones', 'type' => 'number', 'label' => 'Penalizaciones', 'required' => false],
                ['key' => 'observaciones', 'type' => 'textarea', 'label' => 'Observaciones', 'required' => false],
            ],
            default => [
                ['key' => 'valor_principal', 'type' => 'text', 'label' => 'Resultado', 'required' => true],
                ['key' => 'observaciones', 'type' => 'textarea', 'label' => 'Observaciones', 'required' => false],
            ],
        };
    }

    private function validateRonda(Request $request): array
    {
        return $request->validate([
            'nombre' => ['required', 'string', 'max:100'],
            'tipo' => ['required', 'string', 'in:clasificatoria,semifinal,final,libre'],
            'estado' => ['required', 'string', 'in:borrador,activa,cerrada'],
            'fecha_hora' => ['nullable', 'date'],
        ], [
            'nombre.required' => 'Ingresa el nombre de la ronda.',
            'tipo.in' => 'Selecciona un tipo de ronda valido.',
            'estado.in' => 'Selecciona un estado de ronda valido.',
        ]);
    }

}
