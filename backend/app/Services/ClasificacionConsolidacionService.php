<?php

namespace App\Services;

use App\Models\Categoria;
use App\Models\Clasificacion;
use App\Models\ClasificacionPublicacionHist;
use App\Models\ConfigCalificacion;
use App\Models\Resultado;
use App\Models\Ronda;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ClasificacionConsolidacionService
{
    public function obtenerVista(int $competenciaId, ?int $categoriaId = null, ?int $rondaId = null): array
    {
        [$categoria, $ronda, $config] = $this->resolverScope($competenciaId, $categoriaId, $rondaId);

        $clasificaciones = $this->obtenerClasificaciones($competenciaId, $categoria->id, $ronda->id, $config);
        $evaluaciones = $this->obtenerEvaluaciones($ronda->id);

        return [
            'scope' => [
                'competencia_id' => $competenciaId,
                'categoria_id' => (int) $categoria->id,
                'ronda_id' => (int) $ronda->id,
                'categoria_nombre' => (string) $categoria->nombre,
                'ronda_nombre' => (string) $ronda->nombre,
                'mecanismo_codigo' => (string) ($config->mecanismo?->codigo ?? ''),
                'mecanismo_nombre' => (string) ($config->mecanismo?->nombre ?? ''),
                'unidad_resultado' => $config->unidad_resultado,
                'orden_ranking' => (string) $config->orden_ranking,
            ],
            'summary' => [
                'evaluaciones_count' => $evaluaciones->count(),
                'equipos_evaluados_count' => $evaluaciones->pluck('equipo_id')->unique()->count(),
                'jueces_count' => $evaluaciones->pluck('juez_user_id')->unique()->count(),
                'clasificaciones_count' => count($clasificaciones),
                'estado_publicacion' => $clasificaciones[0]['estado_publicacion'] ?? 'sin_consolidar',
                'updated_at' => collect($clasificaciones)->pluck('updated_at')->filter()->max(),
                'ultimo_evento_publicacion_at' => $this->obtenerUltimoEventoPublicacionAt(
                    $competenciaId,
                    (int) $categoria->id,
                    (int) $ronda->id
                ),
            ],
            'rows' => $clasificaciones,
            'publication_history' => $this->obtenerHistorialPublicacion(
                $competenciaId,
                (int) $categoria->id,
                (int) $ronda->id
            ),
        ];
    }

    public function consolidar(int $competenciaId, int $categoriaId, int $rondaId, User $admin): array
    {
        [$categoria, $ronda, $config] = $this->resolverScope($competenciaId, $categoriaId, $rondaId);
        $evaluaciones = $this->obtenerEvaluaciones($ronda->id);

        if ($evaluaciones->isEmpty()) {
            throw ValidationException::withMessages([
                'ronda_id' => 'No hay evaluaciones registradas para consolidar en la ronda seleccionada.',
            ]);
        }

        $filas = $this->construirFilasConsolidadas($evaluaciones, $config);

        DB::transaction(function () use ($competenciaId, $categoria, $ronda, $filas) {
            Clasificacion::query()
                ->where('competencia_id', $competenciaId)
                ->where('categoria_id', $categoria->id)
                ->where('ronda_id', $ronda->id)
                ->delete();

            foreach ($filas as $index => $fila) {
                Clasificacion::create([
                    'competencia_id' => $competenciaId,
                    'categoria_id' => $categoria->id,
                    'equipo_id' => $fila['equipo_id'],
                    'ronda_id' => $ronda->id,
                    'puntaje_total' => $fila['puntaje_total'],
                    'tiempo_total' => $fila['tiempo_total'],
                    'penal_total' => $fila['penal_total'],
                    'posicion' => $index + 1,
                    'estado_publicacion' => 'borrador',
                    'publicado_at' => null,
                    'publicado_por' => null,
                    'origen_version' => $fila['origen_version'],
                    'detalle_json' => $fila['detalle_json'],
                ]);
            }
        });

        return $this->obtenerVista($competenciaId, $categoria->id, $ronda->id);
    }

    public function actualizarEstadoPublicacion(
        int $competenciaId,
        int $categoriaId,
        int $rondaId,
        string $estado,
        User $admin
    ): array {
        [$categoria, $ronda, $config] = $this->resolverScope($competenciaId, $categoriaId, $rondaId);

        $clasificaciones = Clasificacion::query()
            ->where('competencia_id', $competenciaId)
            ->where('categoria_id', $categoria->id)
            ->where('ronda_id', $ronda->id)
            ->get();

        if ($clasificaciones->isEmpty()) {
            throw ValidationException::withMessages([
                'ronda_id' => 'No existen clasificaciones consolidadas para actualizar su publicación.',
            ]);
        }

        DB::transaction(function () use ($clasificaciones, $estado, $admin, $competenciaId, $categoria, $ronda) {
            $estadosAnteriores = $clasificaciones
                ->pluck('estado_publicacion')
                ->filter()
                ->unique()
                ->values()
                ->all();

            foreach ($clasificaciones as $clasificacion) {
                $clasificacion->estado_publicacion = $estado;
                $clasificacion->publicado_at = $estado === 'borrador' ? null : now();
                $clasificacion->publicado_por = $estado === 'borrador' ? null : $admin->id;
                $clasificacion->save();
            }

            $this->registrarHistorialPublicacion(
                $competenciaId,
                (int) $categoria->id,
                (int) $ronda->id,
                $clasificaciones,
                $estado,
                $estadosAnteriores,
                $admin
            );
        });

        return $this->obtenerVista($competenciaId, $categoria->id, $ronda->id);
    }

    public function obtenerVistaPublica(int $competenciaId, ?int $categoriaId = null, ?int $rondaId = null): array
    {
        [$categoria, $ronda, $config] = $this->resolverScopePublico($competenciaId, $categoriaId, $rondaId);

        $rows = Clasificacion::query()
            ->with('equipo:id,nombre,institucion')
            ->where('competencia_id', $competenciaId)
            ->where('categoria_id', $categoria->id)
            ->where('ronda_id', $ronda->id)
            ->whereIn('estado_publicacion', ['visible', 'cerrado'])
            ->orderBy('posicion')
            ->get()
            ->map(function (Clasificacion $clasificacion) use ($config) {
                return [
                    'posicion' => (int) $clasificacion->posicion,
                    'equipo_nombre' => (string) ($clasificacion->equipo?->nombre ?? ''),
                    'institucion' => (string) ($clasificacion->equipo?->institucion ?? ''),
                    'resultado_label' => $this->formatearResultadoClasificacion($clasificacion, $config),
                    'puntaje_total' => $clasificacion->puntaje_total,
                    'estado_publicacion' => (string) $clasificacion->estado_publicacion,
                ];
            })
            ->all();

        return [
            'scope' => [
                'competencia_id' => $competenciaId,
                'categoria_id' => (int) $categoria->id,
                'ronda_id' => (int) $ronda->id,
                'categoria_nombre' => (string) $categoria->nombre,
                'ronda_nombre' => (string) $ronda->nombre,
                'mecanismo_nombre' => (string) ($config->mecanismo?->nombre ?? ''),
                'unidad_resultado' => $config->unidad_resultado,
                'estado_publicacion' => $rows[0]['estado_publicacion'] ?? 'sin_publicar',
                'es_en_vivo' => (bool) $config->visible_publico_en_vivo,
            ],
            'summary' => [
                'equipos_count' => count($rows),
                'updated_at' => Clasificacion::query()
                    ->where('competencia_id', $competenciaId)
                    ->where('categoria_id', $categoria->id)
                    ->where('ronda_id', $ronda->id)
                    ->whereIn('estado_publicacion', ['visible', 'cerrado'])
                    ->max('publicado_at'),
            ],
            'rows' => $rows,
        ];
    }

    public function obtenerPanelEnVivo(
        int $competenciaId,
        ?int $categoriaId = null,
        ?int $rondaId = null,
        array $options = []
    ): array
    {
        $audience = $options['audience'] ?? 'public';
        $estadosPermitidos = collect($options['estados_publicacion'] ?? ['visible', 'cerrado'])
            ->filter(fn ($estado) => in_array($estado, ['borrador', 'visible', 'cerrado'], true))
            ->values()
            ->all();

        $categorias = Categoria::query()
            ->with(['configCalificacion.mecanismo', 'rondas'])
            ->where('competencia_id', $competenciaId)
            ->whereHas('configCalificacion', fn ($query) => $query->where('visible_publico_en_vivo', true))
            ->orderBy('nombre')
            ->get();

        $scopes = [];

        foreach ($categorias as $categoria) {
            $config = $categoria->configCalificacion;

            if (! $config) {
                continue;
            }

            foreach ($categoria->rondas->sortBy('fecha_hora') as $ronda) {
                $clasificaciones = Clasificacion::query()
                    ->with('equipo:id,nombre,institucion')
                    ->where('competencia_id', $competenciaId)
                    ->where('categoria_id', $categoria->id)
                    ->where('ronda_id', $ronda->id)
                    ->whereIn('estado_publicacion', $estadosPermitidos)
                    ->orderBy('posicion')
                    ->get();

                if ($clasificaciones->isEmpty()) {
                    continue;
                }

                $rows = $clasificaciones->map(function (Clasificacion $clasificacion) use ($config) {
                    return [
                        'posicion' => (int) $clasificacion->posicion,
                        'equipo_nombre' => (string) ($clasificacion->equipo?->nombre ?? ''),
                        'institucion' => (string) ($clasificacion->equipo?->institucion ?? ''),
                        'resultado_label' => $this->formatearResultadoClasificacion($clasificacion, $config),
                        'puntaje_total' => $clasificacion->puntaje_total,
                        'tiempo_total' => $clasificacion->tiempo_total,
                        'estado_publicacion' => (string) $clasificacion->estado_publicacion,
                    ];
                })->values()->all();

                $scopes[] = [
                    'key' => $categoria->id . ':' . $ronda->id,
                    'categoria_id' => (int) $categoria->id,
                    'categoria_nombre' => (string) $categoria->nombre,
                    'ronda_id' => (int) $ronda->id,
                    'ronda_nombre' => (string) $ronda->nombre,
                    'mecanismo_nombre' => (string) ($config->mecanismo?->nombre ?? ''),
                    'visible_publico_en_vivo' => (bool) $config->visible_publico_en_vivo,
                    'unidad_resultado' => $config->unidad_resultado,
                    'estado_publicacion' => (string) ($clasificaciones->first()?->estado_publicacion ?? 'borrador'),
                    'es_oficial' => in_array(
                        (string) ($clasificaciones->first()?->estado_publicacion ?? ''),
                        ['visible', 'cerrado'],
                        true
                    ),
                    'ultima_consolidacion_at' => optional($clasificaciones->max('updated_at'))?->toIso8601String(),
                    'ultima_publicacion_at' => optional($clasificaciones->max('publicado_at'))?->toIso8601String(),
                    'updated_at' => optional($clasificaciones->max('updated_at'))?->toIso8601String(),
                    'rows' => $rows,
                ];
            }
        }

        usort($scopes, function (array $left, array $right) {
            return [$left['categoria_nombre'], $left['ronda_nombre']] <=> [$right['categoria_nombre'], $right['ronda_nombre']];
        });

        $selected = collect($scopes)->first(function (array $scope) use ($categoriaId, $rondaId) {
            if ($categoriaId && (int) $scope['categoria_id'] !== (int) $categoriaId) {
                return false;
            }

            if ($rondaId && (int) $scope['ronda_id'] !== (int) $rondaId) {
                return false;
            }

            return true;
        }) ?? ($scopes[0] ?? null);

        return [
            'competition_id' => $competenciaId,
            'generated_at' => now()->toIso8601String(),
            'meta' => [
                'audience' => $audience,
                'estados_publicacion' => $estadosPermitidos,
                'filters' => [
                    'categoria_id' => $categoriaId ? (int) $categoriaId : null,
                    'ronda_id' => $rondaId ? (int) $rondaId : null,
                ],
                'scopes_count' => count($scopes),
                'selected_key' => $selected['key'] ?? null,
            ],
            'scopes' => array_values($scopes),
            'selected' => $selected,
        ];
    }

    private function resolverScope(int $competenciaId, ?int $categoriaId, ?int $rondaId): array
    {
        $categoria = Categoria::query()
            ->with(['configCalificacion.mecanismo', 'rondas'])
            ->where('competencia_id', $competenciaId)
            ->when($categoriaId, fn ($query) => $query->where('id', $categoriaId))
            ->orderBy('nombre')
            ->first();

        if (! $categoria) {
            throw ValidationException::withMessages([
                'categoria_id' => 'No existe una categoría válida para la competencia seleccionada.',
            ]);
        }

        $config = $categoria->configCalificacion;

        if (! $config || ! $config->mecanismo) {
            throw ValidationException::withMessages([
                'categoria_id' => 'La categoría seleccionada no tiene configuración de calificación.',
            ]);
        }

        $ronda = $categoria->rondas
            ->sortBy('fecha_hora')
            ->when(
                $rondaId,
                fn (Collection $collection) => $collection->filter(fn (Ronda $item) => (int) $item->id === (int) $rondaId)
            )
            ->first();

        if (! $ronda) {
            throw ValidationException::withMessages([
                'ronda_id' => 'No existe una ronda válida para la categoría seleccionada.',
            ]);
        }

        return [$categoria, $ronda, $config];
    }

    private function resolverScopePublico(int $competenciaId, ?int $categoriaId, ?int $rondaId): array
    {
        $categoriaIdsConPublicacion = Clasificacion::query()
            ->where('competencia_id', $competenciaId)
            ->whereIn('estado_publicacion', ['visible', 'cerrado'])
            ->pluck('categoria_id')
            ->unique()
            ->values();

        if ($categoriaIdsConPublicacion->isEmpty()) {
            throw ValidationException::withMessages([
                'competencia_id' => 'Todavía no hay clasificaciones publicadas para la competencia seleccionada.',
            ]);
        }

        $categoria = Categoria::query()
            ->with(['configCalificacion.mecanismo', 'rondas'])
            ->where('competencia_id', $competenciaId)
            ->whereIn('id', $categoriaIdsConPublicacion)
            ->when($categoriaId, fn ($query) => $query->where('id', $categoriaId))
            ->orderBy('nombre')
            ->first();

        if (! $categoria || ! $categoria->configCalificacion?->mecanismo) {
            throw ValidationException::withMessages([
                'categoria_id' => 'No existe una categoría pública válida para la competencia seleccionada.',
            ]);
        }

        $rondaIdsConPublicacion = Clasificacion::query()
            ->where('competencia_id', $competenciaId)
            ->where('categoria_id', $categoria->id)
            ->whereIn('estado_publicacion', ['visible', 'cerrado'])
            ->pluck('ronda_id')
            ->filter()
            ->unique()
            ->values();

        $ronda = $categoria->rondas
            ->sortBy('fecha_hora')
            ->filter(fn (Ronda $item) => $rondaIdsConPublicacion->contains((int) $item->id))
            ->when(
                $rondaId,
                fn (Collection $collection) => $collection->filter(fn (Ronda $item) => (int) $item->id === (int) $rondaId)
            )
            ->first();

        if (! $ronda) {
            throw ValidationException::withMessages([
                'ronda_id' => 'No existe una ronda pública válida para la categoría seleccionada.',
            ]);
        }

        return [$categoria, $ronda, $categoria->configCalificacion];
    }

    private function obtenerEvaluaciones(int $rondaId): Collection
    {
        return Resultado::query()
            ->with(['equipo:id,nombre,institucion', 'juez:id,name,last_name'])
            ->where('ronda_id', $rondaId)
            ->whereIn('estado', ['registrado', 'publicado'])
            ->orderBy('equipo_id')
            ->orderBy('juez_user_id')
            ->get();
    }

    private function construirFilasConsolidadas(Collection $evaluaciones, ConfigCalificacion $config): array
    {
        $mecanismo = (string) ($config->mecanismo?->codigo ?? '');

        $filas = $evaluaciones
            ->groupBy('equipo_id')
            ->map(function (Collection $items) use ($mecanismo, $config) {
                /** @var \App\Models\Resultado $primero */
                $primero = $items->first();
                $payloads = $items->pluck('payload_json')->filter(fn ($item) => is_array($item))->values();

                $puntajeTotal = round((float) $items->sum(fn (Resultado $item) => (float) ($item->puntaje ?? 0)), 2);
                $tiempoTotal = round((float) $items->sum(fn (Resultado $item) => (float) ($item->tiempo ?? 0)), 3);
                $penalTotal = round((float) $items->sum(fn (Resultado $item) => (float) ($item->penalizaciones ?? 0)), 3);
                $valoresPrincipales = $items->filter(fn (Resultado $item) => $item->valor_principal !== null);
                $valoresSecundarios = $items->filter(fn (Resultado $item) => $item->valor_secundario !== null);
                $valorPrincipalPromedio = $valoresPrincipales->isNotEmpty()
                    ? round((float) $valoresPrincipales->avg(fn (Resultado $item) => (float) $item->valor_principal), 3)
                    : null;
                $valorSecundarioPromedio = $valoresSecundarios->isNotEmpty()
                    ? round((float) $valoresSecundarios->avg(fn (Resultado $item) => (float) $item->valor_secundario), 3)
                    : null;

                $metricPrimary = match ($mecanismo) {
                    'registro_resultado' => $valorPrincipalPromedio ?? ($config->orden_ranking === 'asc' ? ($tiempoTotal > 0 ? $tiempoTotal : null) : ($puntajeTotal > 0 ? $puntajeTotal : null)),
                    'tabla_evaluacion' => $valorPrincipalPromedio ?? ($puntajeTotal > 0 ? $puntajeTotal : null),
                    'cronometro', 'dron_carrera' => $valorPrincipalPromedio ?? ($tiempoTotal > 0 ? $tiempoTotal : null),
                    'puntaje', 'puntaje_jueces', 'mixto', 'dron_destreza', 'combate_llaves', 'soccer_goles' => $valorPrincipalPromedio ?? ($puntajeTotal > 0 ? $puntajeTotal : null),
                    'combate' => $this->sumPayloadNumeric($payloads, 'victorias'),
                    'solo_registro' => $this->resolveSoloRegistroMetric($items),
                    default => $config->orden_ranking === 'asc' ? $tiempoTotal : $puntajeTotal,
                };

                $metricSecondary = match ($mecanismo) {
                    'registro_resultado', 'tabla_evaluacion' => $valorSecundarioPromedio ?? ($tiempoTotal > 0 ? $tiempoTotal : $penalTotal),
                    'cronometro', 'dron_carrera' => $penalTotal,
                    'puntaje', 'puntaje_jueces', 'dron_destreza', 'combate_llaves', 'soccer_goles' => $valorSecundarioPromedio ?? ($tiempoTotal > 0 ? $tiempoTotal : $penalTotal),
                    'mixto' => $tiempoTotal > 0 ? $tiempoTotal : $penalTotal,
                    'combate' => $this->sumPayloadNumeric($payloads, 'derrotas'),
                    'solo_registro' => $valorSecundarioPromedio,
                    default => $penalTotal,
                };

                return [
                    'equipo_id' => (int) $primero->equipo_id,
                    'equipo_nombre' => (string) ($primero->equipo?->nombre ?? ''),
                    'institucion' => (string) ($primero->equipo?->institucion ?? ''),
                    'puntaje_total' => $puntajeTotal ?: null,
                    'tiempo_total' => $tiempoTotal ?: null,
                    'penal_total' => $penalTotal ?: null,
                    'metric_primary' => $metricPrimary,
                    'metric_secondary' => $metricSecondary,
                    'origen_version' => (int) $items->max('version'),
                    'detalle_json' => [
                        'mecanismo_codigo' => $mecanismo,
                        'orden_ranking' => $config->orden_ranking,
                        'unidad_resultado' => $config->unidad_resultado,
                        'resumen' => [
                            'evaluaciones_count' => $items->count(),
                            'puntaje_total' => $puntajeTotal ?: null,
                            'tiempo_total' => $tiempoTotal ?: null,
                            'penal_total' => $penalTotal ?: null,
                            'metric_primary' => $metricPrimary,
                            'metric_secondary' => $metricSecondary,
                        ],
                        'evaluaciones' => $items->map(function (Resultado $resultado) {
                            return [
                                'resultado_id' => (int) $resultado->id,
                                'juez_user_id' => (int) $resultado->juez_user_id,
                                'juez_nombre' => trim(
                                    (string) ($resultado->juez?->name ?? '') . ' ' . (string) ($resultado->juez?->last_name ?? '')
                                ),
                                'puntaje' => $resultado->puntaje,
                                'tiempo' => $resultado->tiempo,
                                'penalizaciones' => $resultado->penalizaciones,
                                'valor_principal' => $resultado->valor_principal,
                                'valor_secundario' => $resultado->valor_secundario,
                                'payload_json' => $resultado->payload_json,
                                'observaciones' => $resultado->observaciones,
                                'version' => (int) $resultado->version,
                                'updated_at' => optional($resultado->updated_at)?->toIso8601String(),
                            ];
                        })->values()->all(),
                    ],
                ];
            })
            ->values()
            ->all();

        usort($filas, fn (array $left, array $right) => $this->compararFilas($left, $right, $mecanismo, $config->orden_ranking));

        return $filas;
    }

    private function obtenerClasificaciones(int $competenciaId, int $categoriaId, int $rondaId, ConfigCalificacion $config): array
    {
        return Clasificacion::query()
            ->with('equipo:id,nombre,institucion')
            ->where('competencia_id', $competenciaId)
            ->where('categoria_id', $categoriaId)
            ->where('ronda_id', $rondaId)
            ->orderBy('posicion')
            ->get()
            ->map(function (Clasificacion $clasificacion) use ($config) {
                return [
                    'id' => (int) $clasificacion->id,
                    'posicion' => (int) $clasificacion->posicion,
                    'equipo_id' => (int) $clasificacion->equipo_id,
                    'equipo_nombre' => (string) ($clasificacion->equipo?->nombre ?? ''),
                    'institucion' => (string) ($clasificacion->equipo?->institucion ?? ''),
                    'puntaje_total' => $clasificacion->puntaje_total,
                    'tiempo_total' => $clasificacion->tiempo_total,
                    'penal_total' => $clasificacion->penal_total,
                    'estado_publicacion' => (string) $clasificacion->estado_publicacion,
                    'origen_version' => (int) ($clasificacion->origen_version ?? 0),
                    'resultado_label' => $this->formatearResultadoClasificacion($clasificacion, $config),
                    'updated_at' => optional($clasificacion->updated_at)?->toIso8601String(),
                    'detalle_json' => $clasificacion->detalle_json,
                ];
            })
            ->all();
    }

    private function obtenerHistorialPublicacion(int $competenciaId, int $categoriaId, int $rondaId, int $limit = 10): array
    {
        return ClasificacionPublicacionHist::query()
            ->with('ejecutadoPor:id,name,last_name')
            ->where('competencia_id', $competenciaId)
            ->where('categoria_id', $categoriaId)
            ->where('ronda_id', $rondaId)
            ->orderByDesc('ejecutado_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(function (ClasificacionPublicacionHist $evento) {
                return [
                    'id' => (int) $evento->id,
                    'accion' => (string) $evento->accion,
                    'estado_anterior' => $evento->estado_anterior,
                    'estado_nuevo' => (string) $evento->estado_nuevo,
                    'clasificaciones_count' => (int) $evento->clasificaciones_count,
                    'ejecutado_por' => $evento->ejecutadoPor
                        ? trim((string) $evento->ejecutadoPor->name . ' ' . (string) $evento->ejecutadoPor->last_name)
                        : null,
                    'ejecutado_at' => optional($evento->ejecutado_at)?->toIso8601String(),
                    'detalle_json' => $evento->detalle_json,
                ];
            })
            ->all();
    }

    private function obtenerUltimoEventoPublicacionAt(int $competenciaId, int $categoriaId, int $rondaId): ?string
    {
        return optional(
            ClasificacionPublicacionHist::query()
                ->where('competencia_id', $competenciaId)
                ->where('categoria_id', $categoriaId)
                ->where('ronda_id', $rondaId)
                ->orderByDesc('ejecutado_at')
                ->orderByDesc('id')
                ->first()
        )?->ejecutado_at?->toIso8601String();
    }

    private function registrarHistorialPublicacion(
        int $competenciaId,
        int $categoriaId,
        int $rondaId,
        Collection $clasificaciones,
        string $estadoNuevo,
        array $estadosAnteriores,
        User $admin
    ): void {
        ClasificacionPublicacionHist::create([
            'competencia_id' => $competenciaId,
            'categoria_id' => $categoriaId,
            'ronda_id' => $rondaId,
            'accion' => $this->resolverAccionPublicacion($estadoNuevo),
            'estado_anterior' => count($estadosAnteriores) === 1 ? (string) $estadosAnteriores[0] : null,
            'estado_nuevo' => $estadoNuevo,
            'clasificaciones_count' => $clasificaciones->count(),
            'ejecutado_por' => $admin->id,
            'ejecutado_at' => now(),
            'detalle_json' => [
                'estados_anteriores' => $estadosAnteriores,
                'clasificacion_ids' => $clasificaciones->pluck('id')->map(fn ($id) => (int) $id)->values()->all(),
                'equipo_ids' => $clasificaciones->pluck('equipo_id')->map(fn ($id) => (int) $id)->values()->all(),
                'posiciones' => $clasificaciones->pluck('posicion')->map(fn ($posicion) => (int) $posicion)->values()->all(),
            ],
        ]);
    }

    private function resolverAccionPublicacion(string $estadoNuevo): string
    {
        return match ($estadoNuevo) {
            'visible' => 'publicar',
            'cerrado' => 'cerrar',
            default => 'borrador',
        };
    }

    private function compararFilas(array $left, array $right, string $mecanismo, string $ordenRanking): int
    {
        $primaryDirection = $ordenRanking === 'asc' ? 1 : -1;

        if ($mecanismo === 'registro_resultado' && $ordenRanking === 'asc') {
            return $this->compareNullable($left['metric_primary'], $right['metric_primary'], 1)
                ?: $this->compareNullable($left['metric_secondary'], $right['metric_secondary'], 1)
                ?: strcasecmp($left['equipo_nombre'], $right['equipo_nombre']);
        }

        if (in_array($mecanismo, ['cronometro', 'dron_carrera'], true)) {
            return $this->compareNullable($left['metric_primary'], $right['metric_primary'], 1)
                ?: $this->compareNullable($left['metric_secondary'], $right['metric_secondary'], 1)
                ?: strcasecmp($left['equipo_nombre'], $right['equipo_nombre']);
        }

        if ($mecanismo === 'mixto') {
            return $this->compareNullable($left['metric_primary'], $right['metric_primary'], -1)
                ?: $this->compareNullable($left['metric_secondary'], $right['metric_secondary'], 1)
                ?: $this->compareNullable($left['penal_total'], $right['penal_total'], 1)
                ?: strcasecmp($left['equipo_nombre'], $right['equipo_nombre']);
        }

        if (in_array($mecanismo, ['registro_resultado', 'tabla_evaluacion', 'combate', 'combate_llaves'], true)) {
            return $this->compareNullable($left['metric_primary'], $right['metric_primary'], -1)
                ?: $this->compareNullable($left['metric_secondary'], $right['metric_secondary'], 1)
                ?: $this->compareNullable($left['penal_total'], $right['penal_total'], 1)
                ?: strcasecmp($left['equipo_nombre'], $right['equipo_nombre']);
        }

        return $this->compareNullable($left['metric_primary'], $right['metric_primary'], $primaryDirection)
            ?: $this->compareNullable($left['metric_secondary'], $right['metric_secondary'], 1)
            ?: $this->compareNullable($left['penal_total'], $right['penal_total'], 1)
            ?: strcasecmp($left['equipo_nombre'], $right['equipo_nombre']);
    }

    private function compareNullable(mixed $left, mixed $right, int $direction): int
    {
        if ($left === null && $right === null) {
            return 0;
        }

        if ($left === null) {
            return 1;
        }

        if ($right === null) {
            return -1;
        }

        if ((float) $left === (float) $right) {
            return 0;
        }

        return ((float) $left < (float) $right ? -1 : 1) * $direction;
    }

    private function sumPayloadNumeric(Collection $payloads, string $key): ?float
    {
        $sum = $payloads->sum(function (array $payload) use ($key) {
            $value = $payload[$key] ?? null;

            return is_numeric($value) ? (float) $value : 0;
        });

        return $sum > 0 ? round((float) $sum, 3) : null;
    }

    private function resolveSoloRegistroMetric(Collection $items): ?float
    {
        $numericos = $items
            ->map(function (Resultado $resultado) {
                if ($resultado->valor_principal !== null && is_numeric($resultado->valor_principal)) {
                    return (float) $resultado->valor_principal;
                }

                $payload = is_array($resultado->payload_json) ? $resultado->payload_json : [];
                $value = $payload['valor_principal'] ?? null;

                return is_numeric($value) ? (float) $value : null;
            })
            ->filter(fn ($value) => $value !== null)
            ->values();

        if ($numericos->isEmpty()) {
            return null;
        }

        return round((float) $numericos->avg(), 3);
    }

    private function formatearResultadoClasificacion(Clasificacion $clasificacion, ConfigCalificacion $config): string
    {
        $mecanismo = (string) ($config->mecanismo?->codigo ?? '');
        $unidad = $config->unidad_resultado ? ' ' . $config->unidad_resultado : '';
        $detalle = $clasificacion->detalle_json['resumen'] ?? [];

        return match ($mecanismo) {
            'registro_resultado' => isset($detalle['metric_primary']) && $detalle['metric_primary'] !== null
                ? number_format((float) $detalle['metric_primary'], $config->orden_ranking === 'asc' ? 3 : 2) . $unidad
                : 'Sin resultado',
            'tabla_evaluacion' => isset($detalle['metric_primary']) && $detalle['metric_primary'] !== null
                ? number_format((float) $detalle['metric_primary'], 2) . $unidad
                : 'Sin puntaje',
            'cronometro', 'dron_carrera' => isset($detalle['metric_primary']) && $detalle['metric_primary'] !== null
                ? number_format((float) $detalle['metric_primary'], 3) . $unidad
                : 'Sin tiempo',
            'puntaje', 'puntaje_jueces', 'dron_destreza' => isset($detalle['metric_primary']) && $detalle['metric_primary'] !== null
                ? number_format((float) $detalle['metric_primary'], 2) . $unidad
                : 'Sin puntaje',
            'combate' => isset($detalle['metric_primary']) && $detalle['metric_primary'] !== null
                ? number_format((float) $detalle['metric_primary'], 0) . ' victorias'
                : 'Sin consolidar',
            'combate_llaves' => isset($detalle['metric_primary']) && $detalle['metric_primary'] !== null
                ? number_format((float) $detalle['metric_primary'], 3) . $unidad
                : 'Sin consolidar',
            'soccer_goles' => isset($detalle['metric_primary']) && $detalle['metric_primary'] !== null
                ? number_format((float) $detalle['metric_primary'], 0) . ' dif. goles'
                : 'Sin consolidar',
            'mixto' => $clasificacion->puntaje_total !== null
                ? number_format((float) $clasificacion->puntaje_total, 2) . $unidad
                    . ($clasificacion->tiempo_total !== null ? ' / ' . number_format((float) $clasificacion->tiempo_total, 3) . ' s' : '')
                : 'Sin consolidar',
            'solo_registro' => isset($detalle['metric_primary']) && $detalle['metric_primary'] !== null
                ? number_format((float) $detalle['metric_primary'], 3) . $unidad
                : 'Registro manual',
            default => $clasificacion->puntaje_total !== null
                ? number_format((float) $clasificacion->puntaje_total, 2) . $unidad
                : 'Sin consolidar',
        };
    }
}
