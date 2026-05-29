<?php

namespace App\Modules\Admin\Categorias\Http\Controllers;

use App\Models\Categoria;
use App\Models\ConfigCalificacion;
use App\Models\Ronda;
use App\Models\RondaParticipante;
use App\Services\ClasificacionConsolidacionService;
use App\Modules\Admin\Categorias\Requests\StoreCategoriaRequest;
use App\Modules\Admin\Categorias\Requests\UpdateCategoriaRequest;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Exceptions\HttpResponseException;
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
            ->orderBy('nombre')
            ->orderBy('id')
            ->get()
            ->map(fn ($c) => [
                'id' => $c->id,
                'nombre' => $c->nombre,
                'nombre_key' => $c->nombre_key,
                'costo_inscripcion' => (float) $c->costo_inscripcion,
                'max_integrantes' => (int) ($c->max_integrantes ?? 2),
                'estado' => (bool) $c->estado,
                'estado_resultados' => (string) ($c->estado_resultados ?? 'pendiente'),
                'resultados_finalizados_at' => optional($c->resultados_finalizados_at)?->toIso8601String(),
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
                    'modalidad_competencia' => $this->modalidadCompetencia($c),
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
            $competenciaId = (int) $request->integer('competencia_id');
            $mecanismoCalificacionId = $this->resolverMecanismoCalificacionId($request);

            DB::transaction(function () use ($request, &$competenciaId, $mecanismoCalificacionId) {
                $data = [
                    'competencia_id' => $request->integer('competencia_id'),
                    'nombre' => (string) $request->string('nombre'),
                    'nombre_key' => (string) $request->input('nombre_key'),
                    'costo_inscripcion' => (float) $request->input('costo_inscripcion'),
                    'max_integrantes' => $request->integer('max_integrantes', 2),
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
                $competenciaId = (int) $categoria->competencia_id;

                $this->upsertConfigCalificacion($categoria, $request, $mecanismoCalificacionId);
            });

            return redirect("/admin/categorias?competencia_id={$competenciaId}", 303)
                ->with('success', 'Categoría creada correctamente.');
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
                    'max_integrantes' => $request->integer('max_integrantes', 2),
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
            'orden_ranking' => ['nullable', 'string', 'in:asc,desc'],
            'requiere_aprobacion_admin' => ['required', 'boolean'],
            'visible_publico_en_vivo' => ['required', 'boolean'],
            'permite_edicion_juez' => ['required', 'boolean'],
            'promediar_resultado_final' => ['nullable', 'boolean'],
            'promediar_jueces' => ['nullable', 'boolean'],
            'tipo_registro' => ['required', 'string', 'in:registro_resultado,tabla_evaluacion'],
            'plantilla_resultado' => ['nullable', 'string', 'in:tiempo,marcador,tabla_individual_criterios,tabla_individual_puntaje_maximo,tabla_enfrentamiento_criterios'],
            'modalidad_competencia' => ['required', 'string', 'in:participacion_individual,enfrentamiento_directo'],
            'esquema_jueces' => ['required', 'string', 'in:registro_cualquier_juez,evaluacion_multi_juez'],
            'campos_json' => ['required', 'array', 'min:1'],
            'campos_json.*.key' => ['required', 'string', 'max:80', 'regex:/^[a-z0-9_]+$/'],
            'campos_json.*.type' => ['required', 'string', 'in:number,duration,text,textarea,select,checkbox,boolean'],
            'campos_json.*.label' => ['required', 'string', 'max:120'],
            'campos_json.*.required' => ['nullable', 'boolean'],
            'campos_json.*.max' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'campos_json.*.valor_unitario' => ['nullable', 'integer', 'min:1', 'max:999999'],
            'campos_json.*.es_penalizacion' => ['nullable', 'boolean'],
            'campos_json.*.options' => ['nullable', 'array'],
        ], [
            'mecanismo_codigo.required' => 'Selecciona el formato de registro.',
            'campos_json.required' => 'Define al menos un campo para el juez.',
            'campos_json.*.key.regex' => 'La clave solo puede contener letras minusculas, numeros y guion bajo.',
            'campos_json.*.valor_unitario.integer' => 'El valor de cada criterio debe ser un numero entero positivo.',
            'campos_json.*.valor_unitario.min' => 'El valor de cada criterio debe ser mayor a cero.',
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

        $plantillaResultado = (string) ($validated['plantilla_resultado'] ?? (
            $validated['tipo_registro'] === 'tabla_evaluacion' ? 'tabla_individual_criterios' : 'tiempo'
        ));
        $promediarResultadoFinal = false;
        $promediarJueces = ($validated['esquema_jueces'] ?? null) === 'evaluacion_multi_juez';
        $esPuntajeMaximo = $validated['tipo_registro'] === 'tabla_evaluacion'
            && $plantillaResultado === 'tabla_individual_puntaje_maximo';

        if ($validated['tipo_registro'] === 'tabla_evaluacion') {
            foreach ($validated['campos_json'] as $index => $campo) {
                if (($campo['type'] ?? null) !== 'number') {
                    continue;
                }

                $valorUnitario = $campo['valor_unitario'] ?? null;

                if (filter_var($valorUnitario, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) {
                    throw ValidationException::withMessages([
                        "campos_json.{$index}.valor_unitario" => 'El valor de cada criterio debe ser un numero entero positivo.',
                    ]);
                }
            }
        }

        $campos = collect($validated['campos_json'])
            ->map(fn (array $campo) => [
                'key' => (string) $campo['key'],
                'type' => (string) $campo['type'],
                'label' => (string) $campo['label'],
                'required' => (bool) ($campo['required'] ?? false),
                ...isset($campo['max']) && $campo['max'] !== null ? ['max' => (float) $campo['max']] : [],
                ...isset($campo['valor_unitario']) && $campo['valor_unitario'] !== null ? ['valor_unitario' => (int) $campo['valor_unitario']] : [],
                ...isset($campo['es_penalizacion']) ? ['es_penalizacion' => $esPuntajeMaximo ? false : (bool) $campo['es_penalizacion']] : [],
                ...isset($campo['options']) && is_array($campo['options']) ? ['options' => $campo['options']] : [],
            ])
            ->values()
            ->all();

        if (($validated['plantilla_resultado'] ?? null) === 'marcador') {
            $campos = [
                ['key' => 'marcador_equipo_a', 'type' => 'number', 'label' => 'Marcador equipo A', 'required' => true],
                ['key' => 'marcador_equipo_b', 'type' => 'number', 'label' => 'Marcador equipo B', 'required' => true],
            ];
        }

        if (($validated['plantilla_resultado'] ?? null) === 'tiempo') {
            $campos = [
                ['key' => 'tiempo', 'type' => 'duration', 'label' => 'Tiempo final', 'required' => true],
                ['key' => 'penalizaciones', 'type' => 'number', 'label' => 'Penalizaciones', 'required' => false],
                ['key' => 'observaciones', 'type' => 'textarea', 'label' => 'Observaciones', 'required' => false],
            ];
        }

        $ordenRanking = (string) ($validated['orden_ranking'] ?? 'desc');

        ConfigCalificacion::updateOrCreate(
            ['categoria_id' => $categoria->id],
            [
                'mecanismo_calificacion_id' => (int) $mecanismo->id,
                'unidad_resultado' => $validated['unidad_resultado'] ?? null,
                'orden_ranking' => $ordenRanking,
                'requiere_aprobacion_admin' => (bool) $validated['requiere_aprobacion_admin'],
                'visible_publico_en_vivo' => (bool) $validated['visible_publico_en_vivo'],
                'permite_edicion_juez' => (bool) $validated['permite_edicion_juez'],
                'campos_json' => $campos,
                'reglas_json' => [
                    'ranking' => [
                        'order' => $ordenRanking,
                        'unit' => $validated['unidad_resultado'] ?? null,
                    ],
                    'workflow' => [
                        'requiere_aprobacion_admin' => (bool) $validated['requiere_aprobacion_admin'],
                        'visible_publico_en_vivo' => (bool) $validated['visible_publico_en_vivo'],
                        'permite_edicion_juez' => (bool) $validated['permite_edicion_juez'],
                    ],
                    'registro' => [
                        'tipo_registro' => (string) $validated['tipo_registro'],
                        'plantilla_resultado' => $plantillaResultado,
                        'modalidad_competencia' => (string) $validated['modalidad_competencia'],
                        'esquema_jueces' => (string) $validated['esquema_jueces'],
                        'promediar_jueces' => $promediarJueces,
                        'promediar_resultado_final' => $promediarResultadoFinal,
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
            ->with('configCalificacion')
            ->withCount('inscripciones')
            ->findOrFail($categoriaId);

        $rondas = Ronda::query()
            ->where('categoria_id', $categoria->id)
            ->withCount('resultados')
            ->orderBy('orden')
            ->orderBy('id')
            ->get()
            ->map(fn (Ronda $ronda) => [
                'id' => (int) $ronda->id,
                'categoria_id' => (int) $ronda->categoria_id,
                'nombre' => (string) $ronda->nombre,
                'tipo' => (string) ($ronda->tipo ?? 'libre'),
                'orden' => (int) ($ronda->orden ?? 1),
                'cantidad_intentos' => (int) ($ronda->cantidad_intentos ?? 1),
                'intentos_consecutivos' => (bool) ($ronda->intentos_consecutivos ?? false),
                'clasifican_cantidad' => $ronda->clasifican_cantidad !== null ? (int) $ronda->clasifican_cantidad : null,
                'criterio_clasificacion' => (string) ($ronda->criterio_clasificacion ?? 'mayor_puntaje'),
                'ronda_origen_id' => $ronda->ronda_origen_id ? (int) $ronda->ronda_origen_id : null,
                'es_final' => (bool) ($ronda->es_final ?? false),
                'estado' => (string) ($ronda->estado ?? 'borrador'),
                'resultados_count' => (int) $ronda->resultados_count,
                'has_resultados' => (int) $ronda->resultados_count > 0,
            ])
            ->values();

        return response()->json([
            'categoria' => [
                'id' => (int) $categoria->id,
                'nombre' => (string) $categoria->nombre,
                'inscripciones_count' => (int) $categoria->inscripciones_count,
                'modalidad_competencia' => $this->modalidadCompetencia($categoria),
            ],
            'rondas' => $rondas,
        ]);
    }

    public function storeRonda(Request $request, int $categoriaId)
    {
        $categoria = Categoria::findOrFail($categoriaId);
        $this->ensureRondasIndividuales($categoria);
        $data = $this->validateRonda($request);
        $data['orden'] = $data['orden'] ?? $this->siguienteOrdenRonda($categoria->id);
        $data['nombre'] = $this->generarNombreRonda($data['tipo'], (int) $data['orden']);
        $data['es_final'] = ($data['tipo'] ?? '') === 'final' || (bool) ($data['es_final'] ?? false);
        $crearSiguienteRonda = $request->boolean('crear_siguiente_ronda');

        $this->validarFlujoRonda($categoria, $data, null, $crearSiguienteRonda);

        $ronda = DB::transaction(function () use ($categoria, $data, $crearSiguienteRonda) {
            $ronda = Ronda::create([
                'categoria_id' => $categoria->id,
                ...$data,
            ]);

            if ($crearSiguienteRonda && $this->rondaRequiereSiguiente($ronda)) {
                $this->crearSiguienteRondaBorrador($ronda);
            }

            return $ronda;
        });

        return response()->json([
            'message' => $crearSiguienteRonda
                ? 'Ronda creada correctamente y siguiente ronda preparada.'
                : 'Ronda creada correctamente.',
            'ronda' => $ronda,
        ], 201);
    }

    public function updateRonda(Request $request, int $categoriaId, int $rondaId)
    {
        $categoria = Categoria::findOrFail($categoriaId);
        $this->ensureRondasIndividuales($categoria);
        $ronda = Ronda::query()
            ->where('categoria_id', $categoriaId)
            ->findOrFail($rondaId);

        $data = $this->validateRonda($request);
        $data['orden'] = $data['orden'] ?? (int) ($ronda->orden ?? 1);
        $data['nombre'] = $this->generarNombreRonda($data['tipo'], (int) $data['orden']);
        $data['es_final'] = ($data['tipo'] ?? '') === 'final' || (bool) ($data['es_final'] ?? false);
        $crearSiguienteRonda = $request->boolean('crear_siguiente_ronda');

        $this->validarFlujoRonda($categoria, $data, (int) $ronda->id, $crearSiguienteRonda);

        DB::transaction(function () use ($ronda, $data, $crearSiguienteRonda) {
            $ronda->update($data);

            if ($crearSiguienteRonda && $this->rondaRequiereSiguiente($ronda)) {
                $this->crearSiguienteRondaBorrador($ronda);
            }
        });

        return response()->json([
            'message' => $crearSiguienteRonda
                ? 'Ronda actualizada correctamente y siguiente ronda preparada.'
                : 'Ronda actualizada correctamente.',
        ]);
    }

    public function destroyRonda(int $categoriaId, int $rondaId)
    {
        $categoria = Categoria::findOrFail($categoriaId);
        $this->ensureRondasIndividuales($categoria);
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

    public function clasificarRonda(Request $request, int $categoriaId, int $rondaId, ClasificacionConsolidacionService $clasificacionService)
    {
        $categoria = Categoria::findOrFail($categoriaId);
        $ronda = Ronda::query()
            ->where('categoria_id', $categoria->id)
            ->findOrFail($rondaId);

        if (! $ronda->clasifican_cantidad || (int) $ronda->clasifican_cantidad < 1) {
            throw ValidationException::withMessages([
                'clasifican_cantidad' => 'Configura cuántos participantes pasan a la siguiente ronda.',
            ]);
        }

        $siguiente = Ronda::query()
            ->where('categoria_id', $categoria->id)
            ->where('orden', '>', (int) $ronda->orden)
            ->orderBy('orden')
            ->first();

        if (! $siguiente) {
            throw ValidationException::withMessages([
                'ronda' => 'Crea primero la siguiente ronda para asignar los clasificados.',
            ]);
        }

        if ($siguiente->resultados()->exists()) {
            throw ValidationException::withMessages([
                'ronda' => 'La siguiente ronda ya tiene evaluaciones registradas y no puede recibir una nueva clasificación automática.',
            ]);
        }

        $vista = $clasificacionService->consolidar(
            (int) $categoria->competencia_id,
            (int) $categoria->id,
            (int) $ronda->id,
            $request->user()
        );

        $clasificados = $this->resolverClasificadosRonda($ronda, collect($vista['rows'] ?? []))
            ->take((int) $ronda->clasifican_cantidad)
            ->values();

        DB::transaction(function () use ($ronda, $siguiente, $clasificados) {
            $ronda->update(['estado' => 'cerrada']);

            RondaParticipante::query()
                ->where('ronda_id', $siguiente->id)
                ->delete();

            foreach ($clasificados as $fila) {
                $inscripcionId = DB::table('vinculaciones.inscripciones')
                    ->where('categoria_id', $siguiente->categoria_id)
                    ->where('equipo_id', (int) $fila['equipo_id'])
                    ->value('id');

                if (! $inscripcionId) {
                    continue;
                }

                RondaParticipante::create([
                    'ronda_id' => $siguiente->id,
                    'inscripcion_id' => (int) $inscripcionId,
                    'equipo_id' => (int) $fila['equipo_id'],
                    'estado' => 'clasificado',
                    'origen_clasificacion_id' => (int) ($fila['id'] ?? 0) ?: null,
                ]);
            }
        });

        return response()->json([
            'message' => 'Ronda cerrada y clasificados asignados correctamente.',
            'clasificados_count' => $clasificados->count(),
            'siguiente_ronda_id' => (int) $siguiente->id,
        ]);
    }

    private function resolverClasificadosRonda(Ronda $ronda, $filas)
    {
        $sorteo = DB::table('catalogo.sorteos')
            ->where('ronda_id', $ronda->id)
            ->where('estado', '!=', 'anulado')
            ->first();

        if (! $sorteo || (string) $sorteo->tipo_sorteo !== 'enfrentamiento') {
            return $filas;
        }

        $detalles = DB::table('catalogo.sorteo_detalles as sd')
            ->join('vinculaciones.inscripciones as i', 'i.id', '=', 'sd.inscripcion_id')
            ->where('sd.sorteo_id', $sorteo->id)
            ->select([
                'sd.inscripcion_id',
                'sd.grupo',
                'sd.lado',
                'sd.estado',
                'sd.orden',
                'i.equipo_id',
            ])
            ->orderBy('sd.orden')
            ->get();

        $filasPorEquipo = $filas->keyBy(fn ($fila) => (int) ($fila['equipo_id'] ?? 0));
        $clasificados = collect();

        $detalles
            ->where('estado', 'directo')
            ->each(function ($detalle) use ($clasificados) {
                $clasificados->push([
                    'id' => null,
                    'equipo_id' => (int) $detalle->equipo_id,
                ]);
            });

        $detalles
            ->where('estado', '!=', 'directo')
            ->groupBy('grupo')
            ->sortKeys()
            ->each(function ($grupoDetalles) use ($filasPorEquipo, $clasificados) {
                $ganador = $this->resolverGanadorGrupo($grupoDetalles, $filasPorEquipo);

                if ($ganador) {
                    $clasificados->push($ganador);
                }
            });

        return $clasificados->unique(fn ($fila) => (int) ($fila['equipo_id'] ?? 0))->values();
    }

    private function resolverGanadorGrupo($grupoDetalles, $filasPorEquipo): ?array
    {
        $detalleA = $grupoDetalles->first(fn ($detalle) => (string) $detalle->lado === 'A');
        $detalleB = $grupoDetalles->first(fn ($detalle) => (string) $detalle->lado === 'B');
        $filaA = $detalleA ? $filasPorEquipo->get((int) $detalleA->equipo_id) : null;
        $filaB = $detalleB ? $filasPorEquipo->get((int) $detalleB->equipo_id) : null;
        $payload = $this->payloadEnfrentamiento($filaA) ?: $this->payloadEnfrentamiento($filaB);

        if (
            $filaA
            && $filaB
            && isset($payload['marcador_equipo_a'], $payload['marcador_equipo_b'])
            && is_numeric($payload['marcador_equipo_a'])
            && is_numeric($payload['marcador_equipo_b'])
            && (float) $payload['marcador_equipo_a'] !== (float) $payload['marcador_equipo_b']
        ) {
            return (float) $payload['marcador_equipo_a'] > (float) $payload['marcador_equipo_b']
                ? $filaA
                : $filaB;
        }

        return $grupoDetalles
            ->map(fn ($detalle) => $filasPorEquipo->get((int) $detalle->equipo_id))
            ->filter()
            ->sortBy(fn ($fila) => (int) ($fila['posicion'] ?? PHP_INT_MAX))
            ->first();
    }

    private function payloadEnfrentamiento(?array $fila): array
    {
        if (! $fila) {
            return [];
        }

        $evaluacion = $fila['detalle_json']['evaluaciones'][0]['payload_json'] ?? [];

        return is_array($evaluacion) ? $evaluacion : [];
    }

    private function upsertConfigCalificacion(Categoria $categoria, Request $request, int $mecanismoCalificacionId): void
    {
        $mecanismo = DB::table('catalogo.mecanismos_calificacion')
            ->where('id', $mecanismoCalificacionId)
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
                'promediar_jueces' => false,
            ],
        ];

        ConfigCalificacion::updateOrCreate(
            ['categoria_id' => $categoria->id],
            [
                'mecanismo_calificacion_id' => $mecanismoCalificacionId,
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

    private function resolverMecanismoCalificacionId(Request $request): int
    {
        $mecanismoId = (int) $request->input('mecanismo_calificacion_id', 0);

        if ($mecanismoId > 0) {
            $existe = DB::table('catalogo.mecanismos_calificacion')
                ->where('id', $mecanismoId)
                ->where('activo', true)
                ->exists();

            if ($existe) {
                return $mecanismoId;
            }
        }

        $fallback = (int) (
            DB::table('catalogo.mecanismos_calificacion')
                ->where('activo', true)
                ->orderBy('id')
                ->value('id') ?? 0
        );

        if ($fallback > 0) {
            return $fallback;
        }

        throw ValidationException::withMessages([
            'mecanismo_calificacion_id' => 'No existen mecanismos de calificación activos para registrar la categoría.',
        ]);
    }

    private function camposPorMecanismo(string $codigo): array
    {
        return match ($codigo) {
            'registro_resultado' => [
                ['key' => 'tiempo', 'type' => 'duration', 'label' => 'Tiempo final', 'required' => true],
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
                ['key' => 'marcador_equipo_a', 'type' => 'number', 'label' => 'Marcador equipo A', 'required' => true],
                ['key' => 'marcador_equipo_b', 'type' => 'number', 'label' => 'Marcador equipo B', 'required' => true],
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
            'tipo' => ['required', 'string', 'in:clasificatoria,semifinal,final,libre'],
            'orden' => ['nullable', 'integer', 'min:1', 'max:99'],
            'cantidad_intentos' => ['required', 'integer', 'min:1', 'max:10'],
            'intentos_consecutivos' => ['sometimes', 'boolean'],
            'clasifican_cantidad' => ['nullable', 'integer', 'min:1', 'max:999'],
            'criterio_clasificacion' => ['required', 'string', 'in:menor_tiempo,mayor_puntaje,mayor_promedio'],
            'ronda_origen_id' => ['nullable', 'integer', 'min:1'],
            'es_final' => ['required', 'boolean'],
            'estado' => ['required', 'string', 'in:borrador,activa,cerrada'],
        ], [
            'tipo.in' => 'Selecciona un tipo de ronda valido.',
            'estado.in' => 'Selecciona un estado de ronda valido.',
        ]) + ['intentos_consecutivos' => false];
    }

    private function validarFlujoRonda(Categoria $categoria, array $data, ?int $rondaId = null, bool $crearSiguienteRonda = false): void
    {
        if (! $this->datosRequierenSiguienteRonda($data)) {
            return;
        }

        if ((bool) ($data['es_final'] ?? false)) {
            throw ValidationException::withMessages([
                'clasifican_cantidad' => 'Una ronda final no debe enviar clasificados a otra ronda.',
            ]);
        }

        if ($this->existeSiguienteRonda($categoria->id, (int) $data['orden'], $rondaId) || $crearSiguienteRonda) {
            return;
        }

        throw new HttpResponseException(response()->json([
            'message' => 'Esta ronda clasifica participantes. Debes crear una siguiente ronda para recibirlos.',
            'requires_next_round' => true,
            'errors' => [
                'ronda' => ['Esta ronda clasifica participantes. Debes crear una siguiente ronda para recibirlos.'],
            ],
        ], 409));
    }

    private function datosRequierenSiguienteRonda(array $data): bool
    {
        return ! empty($data['clasifican_cantidad']) && (int) $data['clasifican_cantidad'] > 0;
    }

    private function rondaRequiereSiguiente(Ronda $ronda): bool
    {
        return ! $ronda->es_final
            && ! empty($ronda->clasifican_cantidad)
            && (int) $ronda->clasifican_cantidad > 0
            && ! $this->existeSiguienteRonda((int) $ronda->categoria_id, (int) $ronda->orden, (int) $ronda->id);
    }

    private function existeSiguienteRonda(int $categoriaId, int $orden, ?int $rondaId = null): bool
    {
        return Ronda::query()
            ->where('categoria_id', $categoriaId)
            ->where('orden', '>', $orden)
            ->when($rondaId, fn ($query) => $query->where('id', '!=', $rondaId))
            ->exists();
    }

    private function crearSiguienteRondaBorrador(Ronda $ronda): Ronda
    {
        $orden = max(
            ((int) $ronda->orden) + 1,
            $this->siguienteOrdenRonda((int) $ronda->categoria_id)
        );

        return Ronda::create([
            'categoria_id' => $ronda->categoria_id,
            'tipo' => 'final',
            'orden' => $orden,
            'nombre' => $this->generarNombreRonda('final', $orden),
            'cantidad_intentos' => 1,
            'intentos_consecutivos' => false,
            'clasifican_cantidad' => null,
            'criterio_clasificacion' => $ronda->criterio_clasificacion ?: 'mayor_puntaje',
            'ronda_origen_id' => $ronda->id,
            'es_final' => true,
            'estado' => 'activa',
        ]);
    }

    private function siguienteOrdenRonda(int $categoriaId): int
    {
        return ((int) Ronda::query()
            ->where('categoria_id', $categoriaId)
            ->max('orden')) + 1;
    }

    private function generarNombreRonda(string $tipo, int $orden): string
    {
        return match ($tipo) {
            'clasificatoria' => 'Clasificatoria ' . $orden,
            'semifinal' => 'Semifinal',
            'final' => 'Final',
            default => 'Ronda ' . $orden,
        };
    }

    private function modalidadCompetencia(Categoria $categoria): string
    {
        $config = $categoria->configCalificacion;
        $registro = is_array($config?->reglas_json ?? null)
            ? (array) ($config->reglas_json['registro'] ?? [])
            : [];
        $campos = is_array($config?->campos_json ?? null) ? $config->campos_json : [];
        $mecanismo = (string) ($config?->mecanismo?->codigo ?? '');

        return (string) (
            $registro['modalidad_competencia']
            ?? $campos['modalidad_competencia']
            ?? (in_array($mecanismo, ['combate', 'combate_llaves', 'soccer_goles'], true)
                ? 'enfrentamiento_directo'
                : 'participacion_individual')
        );
    }

    private function ensureRondasIndividuales(Categoria $categoria): void
    {
        if (! $categoria->relationLoaded('configCalificacion')) {
            $categoria->load('configCalificacion');
        }

        if ($this->modalidadCompetencia($categoria) === 'enfrentamiento_directo') {
            throw ValidationException::withMessages([
                'categoria' => 'La gestion de llaves para esta categoria se genera automaticamente.',
            ]);
        }
    }

}
