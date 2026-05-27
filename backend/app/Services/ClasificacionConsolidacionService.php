<?php

namespace App\Services;

use App\Events\ResultadosActualizados;
use App\Models\Categoria;
use App\Models\Clasificacion;
use App\Models\ClasificacionPublicacionHist;
use App\Models\ConfigCalificacion;
use App\Models\Inscripcion;
use App\Models\Resultado;
use App\Models\Ronda;
use App\Models\Sorteo;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class ClasificacionConsolidacionService
{
    private array $nombrePrototipoPorClasificacion = [];

    public function obtenerResultadosCompetidor(User $user): array
    {
        $inscripciones = Inscripcion::query()
            ->with([
                'competencia:id,nombre,estado,fecha_inicio',
                'categoria.configCalificacion.mecanismo',
                'equipo:id,nombre,institucion',
            ])
            ->where('user_id', $user->id)
            ->aprobadas()
            ->orderByDesc('id')
            ->get();

        $items = $inscripciones
            ->map(fn (Inscripcion $inscripcion) => $this->serializarResultadoCompetidor($inscripcion))
            ->values()
            ->all();
        $categorias = collect($items)
            ->map(fn (array $item) => [
                'id' => $item['categoria_id'],
                'nombre' => $item['categoria_nombre'],
            ])
            ->unique('id')
            ->sortBy('nombre')
            ->values()
            ->all();

        return [
            'summary' => [
                'total_inscripciones' => count($items),
                'con_resultado' => collect($items)->where('estado_resultado', 'publicado')->count(),
                'cerrados' => collect($items)->where('estado_publicacion', 'cerrado')->count(),
                'pendientes' => collect($items)->whereIn('estado_resultado', ['pendiente', 'pendiente_publicacion'])->count(),
            ],
            'categorias' => $categorias,
            'items' => $items,
        ];
    }

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
                'usa_tiempo' => $this->clasificacionUsaTiempo($config),
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

        $filas = $this->construirFilasConsolidadas($evaluaciones, $config, $ronda);

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
                    'puntaje_total' => $fila['puntaje_total'] ?? 0,
                    'tiempo_total' => $fila['tiempo_total'],
                    'penal_total' => $fila['penal_total'] ?? 0,
                    'posicion' => $index + 1,
                    'estado_publicacion' => 'borrador',
                    'publicado_at' => null,
                    'publicado_por' => null,
                    'origen_version' => $fila['origen_version'],
                    'detalle_json' => $fila['detalle_json'],
                ]);
            }
        });

        $this->emitirResultadosActualizados(new ResultadosActualizados(
            $competenciaId,
            (int) $categoria->id,
            (int) $ronda->id,
            'borrador'
        ));

        return $this->obtenerVista($competenciaId, $categoria->id, $ronda->id);
    }

    public function consolidarYPublicarAutomaticamente(
        int $competenciaId,
        int $categoriaId,
        int $rondaId,
        User $actor,
        string $estadoPublicacion = 'visible'
    ): array {
        if (! in_array($estadoPublicacion, ['borrador', 'visible', 'cerrado'], true)) {
            $estadoPublicacion = 'visible';
        }

        [$categoria, $ronda, $config] = $this->resolverScope($competenciaId, $categoriaId, $rondaId);
        $evaluaciones = $this->obtenerEvaluaciones($ronda->id);

        if ($evaluaciones->isEmpty()) {
            throw ValidationException::withMessages([
                'ronda_id' => 'No hay evaluaciones registradas para consolidar en la ronda seleccionada.',
            ]);
        }

        $filas = $this->construirFilasConsolidadas($evaluaciones, $config, $ronda);

        DB::transaction(function () use ($competenciaId, $categoria, $ronda, $filas, $estadoPublicacion, $actor) {
            $estadosAnteriores = Clasificacion::query()
                ->where('competencia_id', $competenciaId)
                ->where('categoria_id', $categoria->id)
                ->where('ronda_id', $ronda->id)
                ->pluck('estado_publicacion')
                ->filter()
                ->unique()
                ->values()
                ->all();

            Clasificacion::query()
                ->where('competencia_id', $competenciaId)
                ->where('categoria_id', $categoria->id)
                ->where('ronda_id', $ronda->id)
                ->delete();

            $clasificaciones = collect();

            foreach ($filas as $index => $fila) {
                $clasificaciones->push(Clasificacion::create([
                    'competencia_id' => $competenciaId,
                    'categoria_id' => $categoria->id,
                    'equipo_id' => $fila['equipo_id'],
                    'ronda_id' => $ronda->id,
                    'puntaje_total' => $fila['puntaje_total'] ?? 0,
                    'tiempo_total' => $fila['tiempo_total'],
                    'penal_total' => $fila['penal_total'] ?? 0,
                    'posicion' => $index + 1,
                    'estado_publicacion' => $estadoPublicacion,
                    'publicado_at' => $estadoPublicacion === 'borrador' ? null : now(),
                    'publicado_por' => $estadoPublicacion === 'borrador' ? null : $actor->id,
                    'origen_version' => $fila['origen_version'],
                    'detalle_json' => $fila['detalle_json'],
                ]));
            }

            $this->registrarHistorialPublicacion(
                $competenciaId,
                (int) $categoria->id,
                (int) $ronda->id,
                $clasificaciones,
                $estadoPublicacion,
                $estadosAnteriores,
                $actor
            );
        });

        $this->emitirResultadosActualizados(new ResultadosActualizados(
            $competenciaId,
            (int) $categoria->id,
            (int) $ronda->id,
            $estadoPublicacion,
            $estadoPublicacion === 'cerrado'
        ));

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
            ->where('resultados.clasificaciones.competencia_id', $competenciaId)
            ->where('resultados.clasificaciones.categoria_id', $categoria->id)
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

        $this->emitirResultadosActualizados(new ResultadosActualizados(
            $competenciaId,
            (int) $categoria->id,
            (int) $ronda->id,
            $estado,
            $estado === 'cerrado'
        ));

        return $this->obtenerVista($competenciaId, $categoria->id, $ronda->id);
    }

    public function obtenerVistaPublica(int $competenciaId, ?int $categoriaId = null, ?int $rondaId = null): array
    {
        [$categoria, $ronda, $config] = $this->resolverScopePublico($competenciaId, $categoriaId, $rondaId);

        $clasificaciones = Clasificacion::query()
            ->with('equipo:id,nombre,institucion')
            ->where('resultados.clasificaciones.competencia_id', $competenciaId)
            ->where('categoria_id', $categoria->id)
            ->where('ronda_id', $ronda->id)
            ->whereIn('estado_publicacion', ['visible', 'cerrado'])
            ->orderBy('posicion')
            ->limit(3)
            ->get();

        $usaEnfrentamiento = $this->clasificacionUsaEnfrentamiento($config);

        if ($usaEnfrentamiento) {
            $rows = $this->construirPodioPublicoEnfrentamiento($competenciaId, $categoria, $ronda, $config, $clasificaciones);
        } else {
            $rows = $clasificaciones
                ->take(3)
                ->values()
                ->map(function (Clasificacion $clasificacion, int $index) use ($config) {
                    return $this->filaPodioDesdeClasificacion($clasificacion, $config, (int) ($clasificacion->posicion ?: $index + 1));
                })
                ->all();
        }

        return [
            'scope' => [
                'competencia_id' => $competenciaId,
                'categoria_id' => (int) $categoria->id,
                'ronda_id' => (int) $ronda->id,
                'categoria_nombre' => (string) $categoria->nombre,
                'ronda_nombre' => (string) $ronda->nombre,
                'mecanismo_nombre' => (string) ($config->mecanismo?->nombre ?? ''),
                'unidad_resultado' => $config->unidad_resultado,
                'promediar_jueces' => $this->promediaJueces($config),
                'estado_publicacion' => $rows[0]['estado_publicacion'] ?? 'sin_publicar',
                'es_en_vivo' => (bool) $config->visible_publico_en_vivo,
            ],
            'summary' => [
                'equipos_count' => count($rows),
                'updated_at' => Clasificacion::query()
                    ->where('competencia_id', $competenciaId)
                    ->where('categoria_id', $categoria->id)
                    ->whereIn('estado_publicacion', ['visible', 'cerrado'])
                    ->max('publicado_at'),
            ],
            'rows' => $rows,
        ];
    }

    private function construirPodioPublicoEnfrentamiento(
        int $competenciaId,
        Categoria $categoria,
        Ronda $final,
        ConfigCalificacion $config,
        Collection $clasificacionesFinal
    ): array {
        $rows = $this->podioFinalDesdeSorteo($final, $config, $clasificacionesFinal);

        if (count($rows) < 2) {
            $rows = $clasificacionesFinal
                ->take(2)
                ->values()
                ->map(function (Clasificacion $clasificacion, int $index) use ($config) {
                    return $this->filaPodioDesdeClasificacion($clasificacion, $config, $index + 1);
                })
                ->all();
        }

        $tercerLugar = $categoria->rondas
            ->first(fn (Ronda $item) => (string) $item->tipo === 'tercer_lugar');

        if ($tercerLugar && count($rows) < 3) {
            $tercero = Clasificacion::query()
                ->with('equipo:id,nombre,institucion')
                ->where('competencia_id', $competenciaId)
                ->where('categoria_id', $categoria->id)
                ->where('ronda_id', $tercerLugar->id)
                ->whereIn('estado_publicacion', ['visible', 'cerrado'])
                ->orderBy('posicion')
                ->first();

            if ($tercero && ! collect($rows)->contains(fn (array $row) => (int) ($row['equipo_id'] ?? 0) === (int) $tercero->equipo_id)) {
                $rows[] = $this->filaPodioDesdeClasificacion(
                    $tercero,
                    $config,
                    3,
                    'Ganador del encuentro por tercer lugar'
                );
            }
        }

        return collect($rows)
            ->take(3)
            ->map(function (array $row) {
                unset($row['equipo_id']);

                return $row;
            })
            ->values()
            ->all();
    }

    private function podioFinalDesdeSorteo(Ronda $final, ConfigCalificacion $config, Collection $clasificacionesFinal): array
    {
        $sorteo = Sorteo::query()
            ->with('detalles.inscripcion.equipo')
            ->where('ronda_id', $final->id)
            ->where('estado', '!=', 'anulado')
            ->first();

        if (! $sorteo || $sorteo->tipo_sorteo !== 'enfrentamiento') {
            return [];
        }

        $grupo = $sorteo->detalles
            ->filter(fn ($detalle) => $detalle->estado !== 'directo')
            ->groupBy(fn ($detalle) => $detalle->grupo ?? $detalle->orden)
            ->first();

        if (! $grupo || $grupo->count() < 2) {
            return [];
        }

        $ordenados = $grupo->sortBy('orden')->values();
        $detalleA = $ordenados->firstWhere('lado', 'A') ?? $ordenados->get(0);
        $detalleB = $ordenados->firstWhere('lado', 'B') ?? $ordenados->get(1);

        if (! $detalleA?->inscripcion?->equipo || ! $detalleB?->inscripcion?->equipo) {
            return [];
        }

        $resultado = Resultado::query()
            ->where('ronda_id', $final->id)
            ->whereIn('equipo_id', [
                (int) $detalleA->inscripcion->equipo_id,
                (int) $detalleB->inscripcion->equipo_id,
            ])
            ->whereIn('estado', ['registrado', 'publicado'])
            ->latest('updated_at')
            ->first();

        if (! $resultado) {
            return [];
        }

        $valores = $this->valoresResultadoEnfrentamiento($resultado, $config);

        if (! $valores || (float) $valores['a'] === (float) $valores['b']) {
            return [];
        }

        $menorValorGana = ! in_array($this->registroTemplate($config), ['marcador', 'tabla_enfrentamiento_criterios'], true)
            && ($this->registroTemplate($config) === 'tiempo' || (string) $config->orden_ranking === 'asc');
        $ganaA = $menorValorGana
            ? (float) $valores['a'] < (float) $valores['b']
            : (float) $valores['a'] > (float) $valores['b'];
        $ganador = $ganaA ? $detalleA : $detalleB;
        $perdedor = $ganaA ? $detalleB : $detalleA;
        $clasificacionBase = $clasificacionesFinal->firstWhere('equipo_id', (int) $ganador->inscripcion->equipo_id)
            ?? $clasificacionesFinal->first();
        $estado = (string) ($clasificacionBase?->estado_publicacion ?? 'cerrado');

        return [
            $this->filaPodioDesdeDetalleSorteo(
                $ganador,
                1,
                'Final: ganó ' . $this->resultadoDesdePerspectiva($valores, $ganaA ? 'A' : 'B'),
                $estado,
                'Campeón'
            ),
            $this->filaPodioDesdeDetalleSorteo(
                $perdedor,
                2,
                'Marcador de la final: ' . $this->resultadoDesdePerspectiva($valores, $ganaA ? 'B' : 'A'),
                $estado,
                'Subcampeón'
            ),
        ];
    }

    private function valoresResultadoEnfrentamiento(Resultado $resultado, ConfigCalificacion $config): ?array
    {
        $payload = is_array($resultado->payload_json) ? $resultado->payload_json : [];
        $plantilla = $this->registroTemplate($config);

        if ($plantilla === 'marcador') {
            if (! isset($payload['marcador_equipo_a'], $payload['marcador_equipo_b'])) {
                return null;
            }

            $valorA = (float) $payload['marcador_equipo_a'];
            $valorB = (float) $payload['marcador_equipo_b'];

            return [
                'a' => $valorA,
                'b' => $valorB,
                'label' => (int) $valorA . ' - ' . (int) $valorB,
                'reverse_label' => (int) $valorB . ' - ' . (int) $valorA,
            ];
        }

        if ($plantilla === 'tabla_enfrentamiento_criterios') {
            [$valorA, $valorB] = $this->totalesTablaEnfrentamiento($payload, $config);

            return [
                'a' => (float) $valorA,
                'b' => (float) $valorB,
                'label' => $valorA . ' - ' . $valorB,
                'reverse_label' => $valorB . ' - ' . $valorA,
            ];
        }

        $valorA = $resultado->valor_principal;
        $valorB = $resultado->valor_secundario;

        if ($valorA === null || $valorB === null) {
            return null;
        }

        return [
            'a' => (float) $valorA,
            'b' => (float) $valorB,
            'label' => $this->formatearResultadoClasificacion(
                Clasificacion::make([
                    'puntaje_total' => $resultado->puntaje,
                    'tiempo_total' => $resultado->tiempo,
                    'penal_total' => $resultado->penalizaciones,
                    'detalle_json' => [
                        'evaluaciones' => [
                            ['payload_json' => $payload],
                        ],
                        'resumen' => [
                            'metric_primary' => $resultado->valor_principal,
                            'metric_secondary' => $resultado->valor_secundario,
                        ],
                    ],
                ]),
                $config
            ),
            'reverse_label' => $this->formatearResultadoClasificacion(
                Clasificacion::make([
                    'puntaje_total' => $resultado->puntaje,
                    'tiempo_total' => $resultado->tiempo,
                    'penal_total' => $resultado->penalizaciones,
                    'detalle_json' => [
                        'evaluaciones' => [
                            ['payload_json' => $payload],
                        ],
                        'resumen' => [
                            'metric_primary' => $resultado->valor_secundario,
                            'metric_secondary' => $resultado->valor_principal,
                        ],
                    ],
                ]),
                $config
            ),
        ];
    }

    private function resultadoDesdePerspectiva(array $valores, string $lado): string
    {
        return $lado === 'B'
            ? (string) ($valores['reverse_label'] ?? $valores['label'] ?? '')
            : (string) ($valores['label'] ?? '');
    }

    private function nombrePublicoParticipante(?string $nombrePrototipo, ?string $equipoNombre, string $fallback = ''): string
    {
        $nombrePrototipo = trim((string) $nombrePrototipo);

        if ($nombrePrototipo !== '') {
            return $nombrePrototipo;
        }

        $equipoNombre = trim((string) $equipoNombre);

        return $equipoNombre !== '' ? $equipoNombre : $fallback;
    }

    private function nombreParticipanteDesdeClasificacion(Clasificacion $clasificacion, string $fallback = ''): string
    {
        $key = implode(':', [
            (int) $clasificacion->competencia_id,
            (int) $clasificacion->categoria_id,
            (int) $clasificacion->equipo_id,
        ]);

        if (! array_key_exists($key, $this->nombrePrototipoPorClasificacion)) {
            $this->nombrePrototipoPorClasificacion[$key] = Inscripcion::query()
                ->where('competencia_id', $clasificacion->competencia_id)
                ->where('categoria_id', $clasificacion->categoria_id)
                ->where('equipo_id', $clasificacion->equipo_id)
                ->whereNotNull('nombre_prototipo')
                ->where('nombre_prototipo', '!=', '')
                ->orderByDesc('id')
                ->value('nombre_prototipo');
        }

        return $this->nombrePublicoParticipante(
            $this->nombrePrototipoPorClasificacion[$key],
            $clasificacion->equipo?->nombre,
            $fallback
        );
    }

    private function filaPodioDesdeDetalleSorteo(
        $detalle,
        int $posicion,
        string $resultadoLabel,
        string $estadoPublicacion,
        string $nota = ''
    ): array
    {
        $inscripcion = $detalle->inscripcion;
        $equipo = $inscripcion?->equipo;

        return [
            'equipo_id' => (int) ($inscripcion?->equipo_id ?? 0),
            'posicion' => $posicion,
            'equipo_nombre' => $this->nombrePublicoParticipante(
                $inscripcion?->nombre_prototipo,
                $equipo?->nombre
            ),
            'institucion' => (string) ($equipo?->institucion ?? ''),
            'resultado_label' => $resultadoLabel,
            'puntaje_total' => null,
            'estado_publicacion' => $estadoPublicacion,
            'nota' => $nota,
        ];
    }

    private function filaPodioDesdeClasificacion(
        Clasificacion $clasificacion,
        ConfigCalificacion $config,
        int $posicion,
        string $nota = ''
    ): array {
        if ($nota === '' && $posicion === 3 && $this->clasificacionUsaEnfrentamiento($config)) {
            $nota = 'Ganador del tercer lugar';
        }

        $resultadoLabel = $this->clasificacionUsaEnfrentamiento($config)
            ? $this->formatearResultadoEnfrentamiento($clasificacion, $config)
            : $this->formatearResultadoClasificacion($clasificacion, $config);

        if ($this->clasificacionUsaEnfrentamiento($config) && $posicion === 3) {
            $resultadoLabel = 'Tercer lugar: ganó ' . $resultadoLabel;
        }

        return [
            'equipo_id' => (int) $clasificacion->equipo_id,
            'posicion' => $posicion,
            'equipo_nombre' => $this->nombreParticipanteDesdeClasificacion($clasificacion),
            'institucion' => (string) ($clasificacion->equipo?->institucion ?? ''),
            'resultado_label' => $resultadoLabel,
            'resultado_valor' => $clasificacion->detalle_json['resumen']['metric_primary'] ?? null,
            'puntaje_total' => $clasificacion->puntaje_total,
            'estado_publicacion' => (string) $clasificacion->estado_publicacion,
            'nota' => $nota,
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

            foreach ($categoria->rondas->sortBy('orden') as $ronda) {
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

                $usaTiempo = $this->clasificacionUsaTiempo($config);
                $usaEnfrentamiento = $this->clasificacionUsaEnfrentamiento($config);
                $cantidadIntentos = max(1, (int) ($ronda->cantidad_intentos ?? 1));
                $usaIntentos = ! $usaEnfrentamiento;

                $rows = $usaEnfrentamiento
                    ? $this->filasEnfrentamientoEnVivo((int) $ronda->id, $clasificaciones, $config)
                    : $this->filasIndividualesEnVivo($ronda, $clasificaciones, $config);

                $scopes[] = [
                    'key' => $categoria->id . ':' . $ronda->id,
                    'categoria_id' => (int) $categoria->id,
                    'categoria_nombre' => (string) $categoria->nombre,
                    'ronda_id' => (int) $ronda->id,
                    'ronda_nombre' => (string) $ronda->nombre,
                    'mecanismo_nombre' => (string) ($config->mecanismo?->nombre ?? ''),
                    'usa_tiempo' => $usaTiempo,
                    'usa_enfrentamiento' => $usaEnfrentamiento,
                    'usa_intentos' => $usaIntentos,
                    'cantidad_intentos' => $cantidadIntentos,
                    'intentos_headers' => collect(range(1, $cantidadIntentos))
                        ->map(fn (int $intento) => [
                            'numero' => $intento,
                            'label' => 'Intento ' . $intento,
                        ])
                        ->all(),
                    'intentos_consecutivos' => (bool) ($ronda->intentos_consecutivos ?? false),
                    'criterio_clasificacion' => (string) ($ronda->criterio_clasificacion ?? ''),
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
            ->sortBy('orden')
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
            ->join('catalogo.rondas as r', 'r.id', '=', 'resultados.clasificaciones.ronda_id')
            ->where('resultados.clasificaciones.competencia_id', $competenciaId)
            ->whereIn('resultados.clasificaciones.estado_publicacion', ['visible', 'cerrado'])
            ->where('r.es_final', true)
            ->pluck('resultados.clasificaciones.categoria_id')
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
            ->join('catalogo.rondas as r', 'r.id', '=', 'resultados.clasificaciones.ronda_id')
            ->where('resultados.clasificaciones.competencia_id', $competenciaId)
            ->where('resultados.clasificaciones.categoria_id', $categoria->id)
            ->whereIn('resultados.clasificaciones.estado_publicacion', ['visible', 'cerrado'])
            ->where('r.es_final', true)
            ->pluck('resultados.clasificaciones.ronda_id')
            ->filter()
            ->unique()
            ->values();

        $ronda = $categoria->rondas
            ->sortBy('orden')
            ->filter(fn (Ronda $item) => $rondaIdsConPublicacion->contains((int) $item->id))
            ->when(
                $rondaId,
                fn (Collection $collection) => $collection->filter(fn (Ronda $item) => (int) $item->id === (int) $rondaId)
            )
            ->when(
                ! $rondaId,
                fn (Collection $collection) => $collection->sortByDesc(
                    fn (Ronda $item) => ((bool) $item->es_final ? 100000 : 0) + (int) ($item->orden ?? 1)
                )
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

    private function construirFilasConsolidadas(Collection $evaluaciones, ConfigCalificacion $config, ?Ronda $ronda = null): array
    {
        $mecanismo = (string) ($config->mecanismo?->codigo ?? '');

        $filas = $evaluaciones
            ->groupBy('equipo_id')
            ->map(function (Collection $items) use ($mecanismo, $config, $ronda) {
                $promediaJueces = $this->promediaJueces($config);
                $multiJuezSinPromedio = $this->esEvaluacionMultiJuez($config) && ! $promediaJueces;

                if (! $multiJuezSinPromedio) {
                    $items = $this->seleccionarMejorIntento($items, $config, $ronda);
                }

                /** @var \App\Models\Resultado $primero */
                $primero = $items->first();
                $payloads = $items->pluck('payload_json')->filter(fn ($item) => is_array($item))->values();

                $puntajeTotal = $multiJuezSinPromedio ? 0.0 : round((float) $items->sum(fn (Resultado $item) => (float) ($item->puntaje ?? 0)), 2);
                $tiempoTotal = $multiJuezSinPromedio ? 0.0 : round((float) $items->sum(fn (Resultado $item) => (float) ($item->tiempo ?? 0)), 3);
                $penalTotal = $multiJuezSinPromedio ? 0.0 : round((float) $items->sum(fn (Resultado $item) => (float) ($item->penalizaciones ?? 0)), 3);
                $plantillaRegistro = $this->registroTemplate($config);
                $criterioRonda = (string) ($ronda?->criterio_clasificacion ?? '');
                $ignorarCeroComoTiempo = $plantillaRegistro === 'tiempo'
                    || $criterioRonda === 'menor_tiempo'
                    || $this->clasificacionUsaTiempo($config);
                $valoresPrincipales = $items->filter(fn (Resultado $item) => $item->valor_principal !== null
                    && (! $ignorarCeroComoTiempo || (float) $item->valor_principal > 0));
                $valoresSecundarios = $items->filter(fn (Resultado $item) => $item->valor_secundario !== null);
                $valorPrincipalPromedio = $valoresPrincipales->isNotEmpty()
                    ? round((float) $valoresPrincipales->avg(fn (Resultado $item) => (float) $item->valor_principal), 3)
                    : null;
                $valorSecundarioPromedio = $valoresSecundarios->isNotEmpty()
                    ? round((float) $valoresSecundarios->avg(fn (Resultado $item) => (float) $item->valor_secundario), 3)
                    : null;
                if ($multiJuezSinPromedio) {
                    $metricPrimary = null;
                    $metricSecondary = null;
                } elseif (in_array($plantillaRegistro, ['marcador', 'tabla_individual_criterios', 'tabla_individual_puntaje_maximo', 'tabla_enfrentamiento_criterios'], true)) {
                    $metricPrimary = $valorPrincipalPromedio ?? ($puntajeTotal > 0 ? $puntajeTotal : null);
                    $metricSecondary = $valorSecundarioPromedio ?? $penalTotal;
                } elseif ($plantillaRegistro === 'tiempo') {
                    $metricPrimary = $valorPrincipalPromedio ?? ($tiempoTotal > 0 ? $tiempoTotal : null);
                    $metricSecondary = $valorSecundarioPromedio ?? $penalTotal;
                } else {
                    $metricPrimary = match ($mecanismo) {
                        'cronometro', 'dron_carrera' => $valorPrincipalPromedio ?? ($tiempoTotal > 0 ? $tiempoTotal : null),
                        'puntaje', 'puntaje_jueces', 'mixto', 'dron_destreza', 'combate_llaves', 'soccer_goles' => $valorPrincipalPromedio ?? ($puntajeTotal > 0 ? $puntajeTotal : null),
                        'combate' => $this->sumPayloadNumeric($payloads, 'victorias'),
                        'solo_registro' => $this->resolveSoloRegistroMetric($items),
                        default => $config->orden_ranking === 'asc' ? $tiempoTotal : $puntajeTotal,
                    };

                    $metricSecondary = match ($mecanismo) {
                        'cronometro', 'dron_carrera' => $penalTotal,
                        'puntaje', 'puntaje_jueces', 'dron_destreza', 'combate_llaves', 'soccer_goles' => $valorSecundarioPromedio ?? ($tiempoTotal > 0 ? $tiempoTotal : $penalTotal),
                        'mixto' => $tiempoTotal > 0 ? $tiempoTotal : $penalTotal,
                        'combate' => $this->sumPayloadNumeric($payloads, 'derrotas'),
                        'solo_registro' => $valorSecundarioPromedio,
                        default => $penalTotal,
                    };
                }

                return [
                    'equipo_id' => (int) $primero->equipo_id,
                    'equipo_nombre' => (string) ($primero->equipo?->nombre ?? ''),
                    'institucion' => (string) ($primero->equipo?->institucion ?? ''),
                    'puntaje_total' => $puntajeTotal ?? 0,
                    'tiempo_total' => $tiempoTotal ?: null,
                    'penal_total' => $penalTotal ?? 0,
                    'metric_primary' => $metricPrimary,
                    'metric_secondary' => $metricSecondary,
                    'origen_version' => (int) $items->max('version'),
                    'detalle_json' => [
                        'mecanismo_codigo' => $mecanismo,
                        'orden_ranking' => $config->orden_ranking,
                        'unidad_resultado' => $config->unidad_resultado,
                        'resumen' => [
                            'evaluaciones_count' => $items->count(),
                            'promediar_jueces' => $promediaJueces,
                            'requiere_promedio_jueces' => $multiJuezSinPromedio,
                            'puntaje_total' => $puntajeTotal,
                            'tiempo_total' => $tiempoTotal ?: null,
                            'penal_total' => $penalTotal,
                            'metric_primary' => $metricPrimary,
                            'metric_secondary' => $metricSecondary,
                        ],
                        'evaluaciones' => $items->map(function (Resultado $resultado) {
                            return [
                                'resultado_id' => (int) $resultado->id,
                                'intento_numero' => (int) ($resultado->intento_numero ?? 1),
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

        usort($filas, fn (array $left, array $right) => $this->compararFilasPorRonda($left, $right, $ronda)
            ?: $this->compararFilas($left, $right, $mecanismo, $config->orden_ranking));

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
                    'equipo_nombre' => $this->nombreParticipanteDesdeClasificacion($clasificacion),
                    'institucion' => (string) ($clasificacion->equipo?->institucion ?? ''),
                    'puntaje_total' => $clasificacion->puntaje_total,
                    'puntaje_label' => $this->formatearPuntajeClasificacion($clasificacion, $config),
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

    private function seleccionarMejorIntento(Collection $items, ConfigCalificacion $config, ?Ronda $ronda = null): Collection
    {
        $porIntento = $items->groupBy(fn (Resultado $resultado) => (int) ($resultado->intento_numero ?? 1));

        if ($porIntento->count() <= 1) {
            return $items;
        }

        $criterio = (string) ($ronda?->criterio_clasificacion ?? '');
        $plantilla = $this->registroTemplate($config);
        $ascendente = $criterio === 'menor_tiempo'
            || ($criterio === '' && ($config->orden_ranking === 'asc' || $plantilla === 'tiempo' || $this->clasificacionUsaTiempo($config)));

        $gruposEvaluables = $porIntento
            ->reject(fn (Collection $grupo) => $grupo->every(fn (Resultado $resultado) => $this->resultadoNoParticipa($resultado)))
            ->when($ascendente, fn (Collection $grupos) => $grupos
                ->reject(fn (Collection $grupo) => $grupo->every(fn (Resultado $resultado) => $resultado->valor_principal !== null
                    && (float) $resultado->valor_principal <= 0)));

        if ($gruposEvaluables->isEmpty()) {
            return $items;
        }

        return $gruposEvaluables
            ->sort(function (Collection $left, Collection $right) use ($ascendente, $criterio) {
                $resolvePrimary = function (Collection $grupo) use ($ascendente, $criterio): ?float {
                    $valoresPrincipales = $grupo->filter(fn (Resultado $resultado) => $resultado->valor_principal !== null);
                    $valor = $valoresPrincipales->isNotEmpty() && ($criterio === 'mayor_promedio' || $valoresPrincipales->count() > 1)
                        ? (float) $valoresPrincipales->avg(fn (Resultado $resultado) => (float) $resultado->valor_principal)
                        : null;

                    if ($valor === null) {
                        $valor = $ascendente
                            ? (float) $grupo->sum(fn (Resultado $resultado) => (float) ($resultado->tiempo ?? 0))
                            : (float) $grupo->sum(fn (Resultado $resultado) => (float) ($resultado->valor_principal ?? $resultado->puntaje ?? 0));
                    }

                    return $valor;
                };

                $resolveSecondary = function (Collection $grupo): ?float {
                    $valoresSecundarios = $grupo->filter(fn (Resultado $resultado) => $resultado->valor_secundario !== null);

                    return $valoresSecundarios->isNotEmpty()
                        ? (float) $valoresSecundarios->avg(fn (Resultado $resultado) => (float) $resultado->valor_secundario)
                        : null;
                };

                return $this->compareNullable($resolvePrimary($left), $resolvePrimary($right), $ascendente ? 1 : -1)
                    ?: $this->compareNullable($resolveSecondary($left), $resolveSecondary($right), 1);
            })
            ->first()
            ->values();
    }

    private function resultadoNoParticipa(Resultado $resultado): bool
    {
        $payload = is_array($resultado->payload_json) ? $resultado->payload_json : [];

        return (bool) ($payload['sin_tiempo_valido'] ?? false)
            || (bool) ($payload['no_participa'] ?? false);
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

    private function emitirResultadosActualizados(ResultadosActualizados $event): void
    {
        try {
            event($event);
        } catch (Throwable $exception) {
            Log::warning('No se pudo emitir el evento de resultados en vivo.', [
                'competencia_id' => $event->competenciaId,
                'categoria_id' => $event->categoriaId,
                'ronda_id' => $event->rondaId,
                'estado_publicacion' => $event->estadoPublicacion,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);
        }
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

    private function compararFilasPorRonda(array $left, array $right, ?Ronda $ronda): int
    {
        return match ((string) ($ronda?->criterio_clasificacion ?? '')) {
            'menor_tiempo' => $this->compareNullable($left['metric_primary'], $right['metric_primary'], 1)
                ?: $this->compareNullable($left['metric_secondary'], $right['metric_secondary'], 1)
                ?: strcasecmp($left['equipo_nombre'], $right['equipo_nombre']),
            'mayor_puntaje' => $this->compareNullable($left['metric_primary'], $right['metric_primary'], -1)
                ?: $this->compareNullable($left['metric_secondary'], $right['metric_secondary'], 1)
                ?: strcasecmp($left['equipo_nombre'], $right['equipo_nombre']),
            'mayor_promedio' => $this->compareNullable($left['metric_primary'], $right['metric_primary'], -1)
                ?: $this->compareNullable($left['metric_secondary'], $right['metric_secondary'], 1)
                ?: strcasecmp($left['equipo_nombre'], $right['equipo_nombre']),
            default => 0,
        };
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

    private function serializarResultadoCompetidor(Inscripcion $inscripcion): array
    {
        $config = $inscripcion->categoria?->configCalificacion;
        $clasificaciones = collect();

        if ($config && $inscripcion->equipo_id) {
            $clasificaciones = Clasificacion::query()
                ->with(['ronda:id,nombre,orden,es_final', 'equipo:id,nombre,institucion'])
                ->where('competencia_id', $inscripcion->competencia_id)
                ->where('categoria_id', $inscripcion->categoria_id)
                ->where('equipo_id', $inscripcion->equipo_id)
                ->whereIn('estado_publicacion', ['visible', 'cerrado'])
                ->get()
                ->sortBy(fn (Clasificacion $clasificacion) => [
                    (bool) ($clasificacion->ronda?->es_final ?? false) ? 1 : 0,
                    (int) ($clasificacion->ronda?->orden ?? 0),
                    (int) $clasificacion->id,
                ])
                ->values();
        }

        $destacado = $clasificaciones
            ->sortByDesc(fn (Clasificacion $clasificacion) => [
                (bool) ($clasificacion->ronda?->es_final ?? false) ? 1 : 0,
                (int) ($clasificacion->ronda?->orden ?? 0),
                (int) $clasificacion->id,
            ])
            ->first();

        $evaluacionesRegistradas = Resultado::query()
            ->where('competencia_id', $inscripcion->competencia_id)
            ->where('categoria_id', $inscripcion->categoria_id)
            ->where('equipo_id', $inscripcion->equipo_id)
            ->whereIn('estado', ['registrado', 'publicado'])
            ->exists();

        $estadoResultado = $destacado
            ? 'publicado'
            : ($evaluacionesRegistradas ? 'pendiente_publicacion' : 'pendiente');

        return [
            'inscripcion_id' => (int) $inscripcion->id,
            'competencia_id' => (int) $inscripcion->competencia_id,
            'competencia_nombre' => (string) ($inscripcion->competencia?->nombre ?? 'Competencia'),
            'categoria_id' => (int) $inscripcion->categoria_id,
            'categoria_nombre' => (string) ($inscripcion->categoria?->nombre ?? 'Categoria'),
            'equipo_id' => (int) ($inscripcion->equipo_id ?? 0),
            'equipo_nombre' => (string) ($inscripcion->equipo?->nombre ?? 'Sin equipo'),
            'institucion' => (string) ($inscripcion->equipo?->institucion ?? ''),
            'nombre_prototipo' => $inscripcion->nombre_prototipo,
            'estado_inscripcion' => (string) $inscripcion->estado,
            'estado_resultado' => $estadoResultado,
            'estado_publicacion' => $destacado ? (string) $destacado->estado_publicacion : null,
            'resultado_label' => $destacado && $config
                ? $this->formatearResultadoClasificacion($destacado, $config)
                : null,
            'posicion' => $destacado ? (int) $destacado->posicion : null,
            'ronda_nombre' => $destacado ? (string) ($destacado->ronda?->nombre ?? 'Ronda') : null,
            'es_podio' => $destacado ? (int) $destacado->posicion <= 3 : false,
            'publicado_at' => optional($destacado?->publicado_at)->toIso8601String(),
            'updated_at' => optional($destacado?->updated_at)->toIso8601String(),
            'resultados' => $clasificaciones
                ->map(fn (Clasificacion $clasificacion) => [
                    'id' => (int) $clasificacion->id,
                    'ronda_id' => (int) $clasificacion->ronda_id,
                    'ronda_nombre' => (string) ($clasificacion->ronda?->nombre ?? 'Ronda'),
                    'posicion' => (int) $clasificacion->posicion,
                    'resultado_label' => $config ? $this->formatearResultadoClasificacion($clasificacion, $config) : 'Resultado publicado',
                    'puntaje_total' => $clasificacion->puntaje_total,
                    'tiempo_total' => $clasificacion->tiempo_total,
                    'penal_total' => $clasificacion->penal_total,
                    'estado_publicacion' => (string) $clasificacion->estado_publicacion,
                    'publicado_at' => optional($clasificacion->publicado_at)->toIso8601String(),
                    'updated_at' => optional($clasificacion->updated_at)->toIso8601String(),
                ])
                ->values()
                ->all(),
        ];
    }

    private function formatearResultadoClasificacion(Clasificacion $clasificacion, ConfigCalificacion $config): string
    {
        $mecanismo = (string) ($config->mecanismo?->codigo ?? '');
        $unidad = $config->unidad_resultado ? ' ' . $config->unidad_resultado : '';
        $detalle = $clasificacion->detalle_json['resumen'] ?? [];
        $plantilla = $this->registroTemplate($config);

        if ($this->clasificacionNoParticipa($clasificacion)) {
            return 'Sin tiempo valido';
        }

        if ($plantilla === 'marcador') {
            return $this->formatearMarcadorDesdeDetalle($clasificacion);
        }

        if ($plantilla === 'tiempo') {
            return $this->formatearTiempoDesdeSegundos($clasificacion->tiempo_total ?? ($detalle['metric_primary'] ?? null));
        }

        if (in_array($plantilla, ['tabla_individual_criterios', 'tabla_individual_puntaje_maximo', 'tabla_enfrentamiento_criterios'], true)) {
            if (! isset($detalle['metric_primary']) || $detalle['metric_primary'] === null) {
                return 'Sin puntaje';
            }

            $resultado = number_format((float) $detalle['metric_primary'], 2) . $unidad;

            if ($plantilla === 'tabla_individual_criterios' && $clasificacion->tiempo_total !== null) {
                $resultado .= ' / ' . $this->formatearTiempoDesdeSegundos($clasificacion->tiempo_total);
            }

            return $resultado;
        }

        return match ($mecanismo) {
            'registro_resultado' => $this->registroTemplate($config) === 'tiempo'
                ? $this->formatearTiempoDesdeSegundos($clasificacion->tiempo_total ?? ($detalle['metric_primary'] ?? null))
                : (isset($detalle['metric_primary']) && $detalle['metric_primary'] !== null
                    ? number_format((float) $detalle['metric_primary'], $config->orden_ranking === 'asc' ? 3 : 2) . $unidad
                    : 'Sin resultado'),
            'tabla_evaluacion' => isset($detalle['metric_primary']) && $detalle['metric_primary'] !== null
                ? number_format((float) $detalle['metric_primary'], 2) . $unidad
                : 'Sin puntaje',
            'cronometro', 'dron_carrera' => isset($detalle['metric_primary']) && $detalle['metric_primary'] !== null
                ? $this->formatearTiempoDesdeSegundos($detalle['metric_primary'])
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
            'soccer_goles' => $this->formatearMarcadorDesdeDetalle($clasificacion),
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

    private function formatearMarcadorDesdeDetalle(Clasificacion $clasificacion): string
    {
        $evaluacion = $clasificacion->detalle_json['evaluaciones'][0]['payload_json'] ?? [];

        if (! isset($evaluacion['marcador_equipo_a'], $evaluacion['marcador_equipo_b'])) {
            return 'Sin marcador';
        }

        return 'Equipo A ' . (int) $evaluacion['marcador_equipo_a']
            . ' - ' . (int) $evaluacion['marcador_equipo_b']
            . ' Equipo B';
    }

    private function filasIndividualesEnVivo(Ronda $ronda, Collection $clasificaciones, ConfigCalificacion $config): array
    {
        $cantidadIntentos = max(1, (int) ($ronda->cantidad_intentos ?? 1));
        $equipoIds = $clasificaciones
            ->pluck('equipo_id')
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->values()
            ->all();

        $resultadosPorEquipoIntento = Resultado::query()
            ->with('juez:id,name,last_name,email')
            ->where('ronda_id', $ronda->id)
            ->whereIn('equipo_id', $equipoIds)
            ->whereIn('estado', ['registrado', 'publicado'])
            ->orderBy('equipo_id')
            ->orderBy('intento_numero')
            ->get()
            ->groupBy(fn (Resultado $resultado) => ((int) $resultado->equipo_id) . ':' . ((int) ($resultado->intento_numero ?? 1)));

        return $clasificaciones
            ->sortBy('posicion')
            ->map(function (Clasificacion $clasificacion) use ($config, $cantidadIntentos, $resultadosPorEquipoIntento) {
                $mejorIntento = (int) ($clasificacion->detalle_json['evaluaciones'][0]['intento_numero'] ?? 1);
                $resultadosEquipo = $resultadosPorEquipoIntento
                    ->filter(fn ($items, string $key) => str_starts_with($key, ((int) $clasificacion->equipo_id) . ':'))
                    ->flatten(1)
                    ->values();
                $matrizJueces = $this->promediaJueces($config)
                    ? $this->matrizPromedioJueces($resultadosEquipo, $cantidadIntentos, $config)
                    : null;

                $intentos = collect(range(1, $cantidadIntentos))
                    ->map(function (int $intento) use ($clasificacion, $config, $resultadosPorEquipoIntento, $mejorIntento) {
                        $resultados = $resultadosPorEquipoIntento->get(((int) $clasificacion->equipo_id) . ':' . $intento, collect());

                        return [
                            'numero' => $intento,
                            'label' => 'Intento ' . $intento,
                            'resultado_label' => $this->formatearIntentoEnVivo($resultados, $config),
                            'tiene_resultado' => $resultados->isNotEmpty(),
                            'es_mejor' => $resultados->isNotEmpty() && $intento === $mejorIntento,
                            'observaciones' => $resultados
                                ->pluck('observaciones')
                                ->filter()
                                ->unique()
                                ->values()
                                ->all(),
                        ];
                    })
                    ->all();
                $resultadoLabel = $matrizJueces
                    ? (collect($intentos)->firstWhere('numero', $mejorIntento)['resultado_label'] ?? $this->formatearResultadoClasificacion($clasificacion, $config))
                    : $this->formatearResultadoClasificacion($clasificacion, $config);

                return [
                    'posicion' => (int) $clasificacion->posicion,
                    'equipo_id' => (int) $clasificacion->equipo_id,
                    'equipo_nombre' => $this->nombreParticipanteDesdeClasificacion($clasificacion),
                    'institucion' => (string) ($clasificacion->equipo?->institucion ?? ''),
                    'resultado_label' => $resultadoLabel,
                    'puntaje_total' => $clasificacion->puntaje_total,
                    'puntaje_label' => $this->formatearPuntajeClasificacion($clasificacion, $config),
                    'tiempo_total' => $clasificacion->tiempo_total,
                    'estado_publicacion' => (string) $clasificacion->estado_publicacion,
                    'mejor_intento_numero' => $mejorIntento,
                    'intentos' => $intentos,
                    'detalle_publico' => $this->detallePublicoClasificacion($clasificacion, $config) + array_filter([
                        'matriz_jueces' => $matrizJueces,
                    ]),
                ];
            })
            ->values()
            ->all();
    }

    private function formatearIntentoEnVivo(Collection $resultados, ConfigCalificacion $config): string
    {
        if ($resultados->isEmpty()) {
            return 'Pendiente';
        }

        $payloads = $resultados
            ->pluck('payload_json')
            ->filter(fn ($payload) => is_array($payload))
            ->values();

        if ($payloads->contains(fn (array $payload) => (bool) ($payload['sin_tiempo_valido'] ?? false) || (bool) ($payload['no_participa'] ?? false))) {
            return 'Sin tiempo valido';
        }

        $valorPrincipal = $resultados
            ->filter(fn (Resultado $resultado) => $resultado->valor_principal !== null)
            ->avg(fn (Resultado $resultado) => (float) $resultado->valor_principal);

        $valor = $valorPrincipal !== null
            ? round((float) $valorPrincipal, 3)
            : $this->valorIntentoFallback($resultados, $config);

        if ($valor === null) {
            return 'Registrado';
        }

        if ($this->clasificacionUsaTiempo($config) || $this->registroTemplate($config) === 'tiempo') {
            return $this->formatearTiempoDesdeSegundos($valor);
        }

        $unidad = $config->unidad_resultado ? ' ' . $config->unidad_resultado : '';

        if ($this->promediaJueces($config)) {
            return 'Promedio ' . number_format((float) $valor, 2);
        }

        return number_format((float) $valor, 2) . $unidad;
    }

    private function matrizPromedioJueces(Collection $resultados, int $cantidadIntentos, ConfigCalificacion $config): array
    {
        $intentos = collect(range(1, $cantidadIntentos))
            ->map(fn (int $intento) => [
                'numero' => $intento,
                'label' => 'Intento ' . $intento,
            ])
            ->all();

        $porJuez = $resultados
            ->sortBy(fn (Resultado $resultado) => [
                trim((string) ($resultado->juez?->name ?? '') . ' ' . (string) ($resultado->juez?->last_name ?? '')),
                (int) $resultado->juez_user_id,
            ])
            ->groupBy(fn (Resultado $resultado) => (int) $resultado->juez_user_id);

        $jueces = $porJuez
            ->map(function (Collection $items) use ($cantidadIntentos, $config) {
                $primero = $items->first();

                return [
                    'juez_user_id' => (int) $primero->juez_user_id,
                    'juez_nombre' => trim((string) ($primero->juez?->name ?? '') . ' ' . (string) ($primero->juez?->last_name ?? ''))
                        ?: (string) ($primero->juez?->email ?? 'Juez'),
                    'intentos' => collect(range(1, $cantidadIntentos))
                        ->map(function (int $intento) use ($items, $config) {
                            $resultado = $items->first(fn (Resultado $item) => (int) ($item->intento_numero ?? 1) === $intento);

                            return [
                                'numero' => $intento,
                                'valor' => $resultado?->valor_principal,
                                'label' => $resultado && $resultado->valor_principal !== null
                                    ? $this->formatearValorMatrizJueces((float) $resultado->valor_principal, $config, false)
                                    : '',
                            ];
                        })
                        ->all(),
                ];
            })
            ->values()
            ->all();

        $promedios = collect(range(1, $cantidadIntentos))
            ->map(function (int $intento) use ($resultados, $config) {
                $valores = $resultados
                    ->filter(fn (Resultado $resultado) => (int) ($resultado->intento_numero ?? 1) === $intento && $resultado->valor_principal !== null)
                    ->map(fn (Resultado $resultado) => (float) $resultado->valor_principal);
                $promedio = $valores->isNotEmpty() ? round((float) $valores->avg(), 3) : null;

                return [
                    'numero' => $intento,
                    'valor' => $promedio,
                    'label' => $promedio !== null
                        ? $this->formatearValorMatrizJueces($promedio, $config, true)
                        : '',
                ];
            })
            ->all();

        return [
            'titulo' => 'Promedio por juez e intento',
            'intentos' => $intentos,
            'jueces' => $jueces,
            'promedios' => $promedios,
        ];
    }

    private function formatearValorMatrizJueces(float $valor, ConfigCalificacion $config, bool $esPromedio): string
    {
        if ($this->clasificacionUsaTiempo($config) || $this->registroTemplate($config) === 'tiempo') {
            return $this->formatearTiempoDesdeSegundos($valor);
        }

        $unidad = $config->unidad_resultado ? ' ' . $config->unidad_resultado : '';

        return number_format($valor, 2) . ($esPromedio ? '' : $unidad);
    }

    private function valorIntentoFallback(Collection $resultados, ConfigCalificacion $config): ?float
    {
        $campo = ($config->orden_ranking === 'asc' || $this->clasificacionUsaTiempo($config))
            ? 'tiempo'
            : 'puntaje';

        $valores = $resultados
            ->pluck($campo)
            ->filter(fn ($value) => $value !== null && $value !== '')
            ->map(fn ($value) => (float) $value);

        return $valores->isNotEmpty()
            ? round((float) $valores->avg(), 3)
            : null;
    }

    private function filasEnfrentamientoEnVivo(int $rondaId, Collection $clasificaciones, ConfigCalificacion $config): array
    {
        $sorteo = Sorteo::query()
            ->with('detalles.inscripcion.equipo')
            ->where('ronda_id', $rondaId)
            ->where('estado', '!=', 'anulado')
            ->first();

        if (! $sorteo || $sorteo->tipo_sorteo !== 'enfrentamiento') {
            return $clasificaciones->map(fn (Clasificacion $clasificacion) => [
                'encuentro' => (int) $clasificacion->posicion,
                'equipo_a' => $this->nombreParticipanteDesdeClasificacion($clasificacion),
                'institucion_a' => (string) ($clasificacion->equipo?->institucion ?? ''),
                'resultado_label' => $this->formatearResultadoClasificacion($clasificacion, $config),
                'equipo_b' => '-',
                'institucion_b' => '',
                'estado_publicacion' => (string) $clasificacion->estado_publicacion,
                'detalle_publico' => $this->detallePublicoClasificacion($clasificacion, $config),
            ])->values()->all();
        }

        $clasificacionesPorEquipo = $clasificaciones->keyBy(fn (Clasificacion $item) => (int) $item->equipo_id);

        return $sorteo->detalles
            ->filter(fn ($detalle) => $detalle->estado !== 'directo')
            ->groupBy(fn ($detalle) => $detalle->grupo ?? $detalle->orden)
            ->sortKeys()
            ->map(function ($grupoDetalles, $grupo) use ($clasificacionesPorEquipo, $config) {
                $ordenados = $grupoDetalles->sortBy('orden')->values();
                $detalleA = $ordenados->firstWhere('lado', 'A') ?? $ordenados->get(0);
                $detalleB = $ordenados->firstWhere('lado', 'B') ?? $ordenados->get(1);
                $equipoIds = $ordenados
                    ->map(fn ($detalle) => (int) ($detalle->inscripcion?->equipo_id ?? 0))
                    ->filter()
                    ->values();
                $clasificacion = $equipoIds
                    ->map(fn (int $equipoId) => $clasificacionesPorEquipo->get($equipoId))
                    ->filter()
                    ->first();

                if (! $clasificacion) {
                    return null;
                }

                return [
                    'encuentro' => (int) $grupo,
                    'equipo_a' => $this->nombrePublicoParticipante(
                        $detalleA?->inscripcion?->nombre_prototipo,
                        $detalleA?->inscripcion?->equipo?->nombre,
                        'Equipo A'
                    ),
                    'institucion_a' => (string) ($detalleA?->inscripcion?->equipo?->institucion ?? ''),
                    'resultado_label' => $this->formatearResultadoEnfrentamiento($clasificacion, $config),
                    'equipo_b' => $this->nombrePublicoParticipante(
                        $detalleB?->inscripcion?->nombre_prototipo,
                        $detalleB?->inscripcion?->equipo?->nombre,
                        'Equipo B'
                    ),
                    'institucion_b' => (string) ($detalleB?->inscripcion?->equipo?->institucion ?? ''),
                    'estado_publicacion' => (string) $clasificacion->estado_publicacion,
                    'detalle_publico' => $this->detallePublicoClasificacion($clasificacion, $config, [
                        'equipo_a' => $this->nombrePublicoParticipante(
                            $detalleA?->inscripcion?->nombre_prototipo,
                            $detalleA?->inscripcion?->equipo?->nombre,
                            'Equipo A'
                        ),
                        'equipo_b' => $this->nombrePublicoParticipante(
                            $detalleB?->inscripcion?->nombre_prototipo,
                            $detalleB?->inscripcion?->equipo?->nombre,
                            'Equipo B'
                        ),
                    ]),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    private function formatearResultadoEnfrentamiento(Clasificacion $clasificacion, ConfigCalificacion $config): string
    {
        $payload = $clasificacion->detalle_json['evaluaciones'][0]['payload_json'] ?? [];

        if (isset($payload['marcador_equipo_a'], $payload['marcador_equipo_b'])) {
            return (int) $payload['marcador_equipo_a'] . ' - ' . (int) $payload['marcador_equipo_b'];
        }

        if ($this->registroTemplate($config) === 'tabla_enfrentamiento_criterios') {
            [$totalA, $totalB] = $this->totalesTablaEnfrentamiento($payload, $config);

            return $totalA . ' - ' . $totalB;
        }

        return $this->formatearResultadoClasificacion($clasificacion, $config);
    }

    private function totalesTablaEnfrentamiento(array $payload, ConfigCalificacion $config): array
    {
        $totalA = 0;
        $totalB = 0;

        foreach ($config->campos_json ?? [] as $campo) {
            if (($campo['type'] ?? null) !== 'number') {
                continue;
            }

            $unitario = (float) ($campo['valor_unitario'] ?? 0);
            $factor = ($campo['es_penalizacion'] ?? false) ? -1 : 1;
            $key = (string) ($campo['key'] ?? '');

            $totalA += ((float) ($payload["{$key}_a"] ?? 0)) * $unitario * $factor;
            $totalB += ((float) ($payload["{$key}_b"] ?? 0)) * $unitario * $factor;
        }

        return [(int) $totalA, (int) $totalB];
    }

    private function detallePublicoClasificacion(Clasificacion $clasificacion, ConfigCalificacion $config, array $meta = []): array
    {
        $plantilla = $this->registroTemplate($config);
        $payload = $clasificacion->detalle_json['evaluaciones'][0]['payload_json'] ?? [];
        $unidad = $config->unidad_resultado ? ' ' . $config->unidad_resultado : '';

        if ($this->clasificacionNoParticipa($clasificacion)) {
            return [
                'tipo' => 'resumen',
                'titulo' => 'Detalle del resultado',
                'resultado_label' => 'Sin tiempo valido',
            ];
        }

        if ($plantilla === 'marcador') {
            return [
                'tipo' => 'marcador',
                'titulo' => 'Detalle del marcador',
                'marcador_a' => (int) ($payload['marcador_equipo_a'] ?? 0),
                'marcador_b' => (int) ($payload['marcador_equipo_b'] ?? 0),
                'resultado_label' => $this->formatearResultadoClasificacion($clasificacion, $config),
            ];
        }

        if ($plantilla === 'tiempo') {
            $tiempo = $clasificacion->tiempo_total ?? ($clasificacion->detalle_json['resumen']['metric_primary'] ?? null);
            $penalizaciones = $clasificacion->penal_total ?? ($payload['penalizaciones'] ?? 0);

            return [
                'tipo' => 'tiempo',
                'titulo' => 'Detalle del tiempo',
                'tiempo_label' => $this->formatearTiempoDesdeSegundos($tiempo),
                'penalizaciones' => (float) ($penalizaciones ?? 0),
                'resultado_label' => $this->formatearResultadoClasificacion($clasificacion, $config),
            ];
        }

        if ($plantilla === 'tabla_individual_criterios') {
            return $this->detallePublicoTablaIndividual($clasificacion, $config, $payload, $unidad);
        }

        if ($plantilla === 'tabla_individual_puntaje_maximo') {
            return $this->detallePublicoTablaPuntajeMaximo($clasificacion, $config, $payload, $unidad);
        }

        if ($plantilla === 'tabla_enfrentamiento_criterios') {
            return $this->detallePublicoTablaEnfrentamiento($clasificacion, $config, $payload, $meta);
        }

        return [
            'tipo' => 'resumen',
            'titulo' => 'Detalle del resultado',
            'resultado_label' => $this->formatearResultadoClasificacion($clasificacion, $config),
        ];
    }

    private function detallePublicoTablaIndividual(Clasificacion $clasificacion, ConfigCalificacion $config, array $payload, string $unidad): array
    {
        $criterios = [];
        $subtotal = 0.0;
        $penalizaciones = 0.0;
        $cantidadCriterios = 0;

        foreach ($config->campos_json ?? [] as $campo) {
            if (($campo['type'] ?? null) !== 'number') {
                continue;
            }

            $key = (string) ($campo['key'] ?? '');
            $cantidad = (float) ($payload[$key] ?? 0);
            $valor = (float) ($campo['valor_unitario'] ?? 0);
            $esPenalizacion = (bool) ($campo['es_penalizacion'] ?? false);
            $puntaje = round($cantidad * $valor, 3);
            $cantidadCriterios++;

            if ($esPenalizacion) {
                $penalizaciones += $puntaje;
            } else {
                $subtotal += $puntaje;
            }

            $criterios[] = [
                'criterio' => (string) ($campo['label'] ?? $key),
                'cantidad' => $cantidad,
                'valor_unitario' => $valor,
                'puntaje' => $esPenalizacion ? -$puntaje : $puntaje,
                'es_penalizacion' => $esPenalizacion,
            ];
        }

        $total = round($subtotal - $penalizaciones, 3);

        return [
            'tipo' => 'tabla_individual_criterios',
            'titulo' => 'Detalle de criterios',
            'criterios' => $criterios,
            'subtotal' => round($subtotal, 3),
            'penalizaciones' => round($penalizaciones, 3),
            'total' => $total,
            'promediar_resultado_final' => false,
            'cantidad_criterios' => $cantidadCriterios,
            'resultado_label' => number_format($total, 2) . $unidad,
        ];
    }

    private function detallePublicoTablaPuntajeMaximo(Clasificacion $clasificacion, ConfigCalificacion $config, array $payload, string $unidad): array
    {
        $criterios = [];
        $total = 0.0;
        $maximoTotal = 0.0;

        foreach ($config->campos_json ?? [] as $campo) {
            if (($campo['type'] ?? null) !== 'number') {
                continue;
            }

            $key = (string) ($campo['key'] ?? '');
            $maximo = (float) ($campo['valor_unitario'] ?? 0);
            $puntaje = min((float) ($payload[$key] ?? 0), $maximo);

            $total += $puntaje;
            $maximoTotal += $maximo;

            $criterios[] = [
                'criterio' => (string) ($campo['label'] ?? $key),
                'puntaje_maximo' => $maximo,
                'puntaje' => round($puntaje, 3),
            ];
        }

        $total = round($total, 3);

        return [
            'tipo' => 'tabla_individual_puntaje_maximo',
            'titulo' => 'Detalle de puntaje maximo',
            'criterios' => $criterios,
            'subtotal' => $total,
            'penalizaciones' => 0,
            'total' => $total,
            'puntaje_maximo_total' => round($maximoTotal, 3),
            'resultado_label' => number_format($total, 2) . $unidad,
        ];
    }

    private function detallePublicoTablaEnfrentamiento(Clasificacion $clasificacion, ConfigCalificacion $config, array $payload, array $meta): array
    {
        $criterios = [];
        $subtotalA = 0.0;
        $subtotalB = 0.0;
        $penalizacionesA = 0.0;
        $penalizacionesB = 0.0;

        foreach ($config->campos_json ?? [] as $campo) {
            if (($campo['type'] ?? null) !== 'number') {
                continue;
            }

            $key = (string) ($campo['key'] ?? '');
            $valor = (float) ($campo['valor_unitario'] ?? 0);
            $esPenalizacion = (bool) ($campo['es_penalizacion'] ?? false);
            $cantidadA = (float) ($payload["{$key}_a"] ?? 0);
            $cantidadB = (float) ($payload["{$key}_b"] ?? 0);
            $puntajeA = round($cantidadA * $valor, 3);
            $puntajeB = round($cantidadB * $valor, 3);

            if ($esPenalizacion) {
                $penalizacionesA += $puntajeA;
                $penalizacionesB += $puntajeB;
            } else {
                $subtotalA += $puntajeA;
                $subtotalB += $puntajeB;
            }

            $criterios[] = [
                'criterio' => (string) ($campo['label'] ?? $key),
                'valor_unitario' => $valor,
                'es_penalizacion' => $esPenalizacion,
                'cantidad_a' => $cantidadA,
                'cantidad_b' => $cantidadB,
                'puntaje_a' => $esPenalizacion ? -$puntajeA : $puntajeA,
                'puntaje_b' => $esPenalizacion ? -$puntajeB : $puntajeB,
            ];
        }

        $totalA = round($subtotalA - $penalizacionesA, 3);
        $totalB = round($subtotalB - $penalizacionesB, 3);

        return [
            'tipo' => 'tabla_enfrentamiento_criterios',
            'titulo' => 'Detalle del enfrentamiento',
            'equipo_a' => $meta['equipo_a'] ?? 'Equipo A',
            'equipo_b' => $meta['equipo_b'] ?? 'Equipo B',
            'criterios' => $criterios,
            'subtotal_a' => round($subtotalA, 3),
            'subtotal_b' => round($subtotalB, 3),
            'penalizaciones_a' => round($penalizacionesA, 3),
            'penalizaciones_b' => round($penalizacionesB, 3),
            'total_a' => $totalA,
            'total_b' => $totalB,
            'resultado_label' => $totalA . ' - ' . $totalB,
        ];
    }

    private function registroTemplate(ConfigCalificacion $config): ?string
    {
        $registro = $config->reglas_json['registro'] ?? [];

        if (is_array($registro) && ($registro['plantilla_resultado'] ?? null)) {
            return (string) $registro['plantilla_resultado'];
        }

        $campos = collect($config->campos_json ?? []);

        if ($campos->contains(fn ($campo) => ($campo['key'] ?? null) === 'tiempo' && ($campo['type'] ?? null) === 'duration')) {
            return 'tiempo';
        }

        if ($campos->contains(fn ($campo) => ($campo['key'] ?? null) === 'marcador_equipo_a')) {
            return 'marcador';
        }

        if (($config->mecanismo?->codigo ?? null) === 'registro_resultado') {
            return 'tiempo';
        }

        return null;
    }

    private function clasificacionUsaTiempo(ConfigCalificacion $config): bool
    {
        $mecanismo = (string) ($config->mecanismo?->codigo ?? '');

        return $this->registroTemplate($config) === 'tiempo'
            || in_array($mecanismo, ['cronometro', 'dron_carrera'], true);
    }

    private function promediaResultadoFinal(ConfigCalificacion $config): bool
    {
        return false;
    }

    private function esEvaluacionMultiJuez(ConfigCalificacion $config): bool
    {
        $registro = is_array($config->reglas_json ?? null)
            ? (array) ($config->reglas_json['registro'] ?? [])
            : [];

        return ($registro['esquema_jueces'] ?? null) === 'evaluacion_multi_juez';
    }

    private function promediaJueces(ConfigCalificacion $config): bool
    {
        $registro = is_array($config->reglas_json ?? null)
            ? (array) ($config->reglas_json['registro'] ?? [])
            : [];

        return ($registro['esquema_jueces'] ?? null) === 'evaluacion_multi_juez'
            && filter_var($registro['promediar_jueces'] ?? false, FILTER_VALIDATE_BOOLEAN);
    }

    private function clasificacionUsaEnfrentamiento(ConfigCalificacion $config): bool
    {
        $registro = $config->reglas_json['registro'] ?? [];
        $plantilla = $this->registroTemplate($config);

        return (is_array($registro) && ($registro['modalidad_competencia'] ?? null) === 'enfrentamiento_directo')
            || in_array($plantilla, ['marcador', 'tabla_enfrentamiento_criterios'], true);
    }

    private function clasificacionNoParticipa(Clasificacion $clasificacion): bool
    {
        $evaluaciones = collect($clasificacion->detalle_json['evaluaciones'] ?? []);

        return $evaluaciones->isNotEmpty()
            && $evaluaciones->every(fn (array $evaluacion) => (bool) ($evaluacion['payload_json']['sin_tiempo_valido'] ?? false)
                || (bool) ($evaluacion['payload_json']['no_participa'] ?? false));
    }

    private function formatearPuntajeClasificacion(Clasificacion $clasificacion, ConfigCalificacion $config): string
    {
        if ($this->clasificacionNoParticipa($clasificacion)) {
            return 'Sin tiempo valido';
        }

        if ($this->clasificacionUsaTiempo($config)) {
            $detalle = $clasificacion->detalle_json['resumen'] ?? [];

            return $this->formatearTiempoDesdeSegundos($clasificacion->tiempo_total ?? ($detalle['metric_primary'] ?? null));
        }

        if ($clasificacion->puntaje_total === null) {
            return '-';
        }

        $unidad = $config->unidad_resultado ? ' ' . $config->unidad_resultado : '';

        return number_format((float) $clasificacion->puntaje_total, 2) . $unidad;
    }

    private function formatearTiempoDesdeSegundos(mixed $value): string
    {
        if ($value === null || $value === '') {
            return 'Sin tiempo';
        }

        $totalSeconds = max(0, (int) floor((float) $value));
        $hours = intdiv($totalSeconds, 3600);
        $minutes = intdiv($totalSeconds % 3600, 60);
        $seconds = $totalSeconds % 60;

        return sprintf('%02dh %02dm %02ds', $hours, $minutes, $seconds);
    }
}
