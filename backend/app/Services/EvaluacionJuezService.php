<?php

namespace App\Services;

use App\Events\ResultadosActualizados;
use App\Exceptions\EvaluacionConcurrencyException;
use App\Models\AsignacionJuezCategoria;
use App\Models\Categoria;
use App\Models\Clasificacion;
use App\Models\ConfigCalificacion;
use App\Models\Inscripcion;
use App\Models\Resultado;
use App\Models\ResultadoHist;
use App\Models\Ronda;
use App\Models\RondaParticipante;
use App\Models\Sorteo;
use App\Models\SorteoDetalle;
use App\Models\User;
use App\Modules\Admin\Notificaciones\Services\NotificacionService;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Throwable;

class EvaluacionJuezService
{
    public function __construct(
        private readonly RegistroCategoriaLockService $registroLockService,
        private readonly ClasificacionConsolidacionService $consolidacionService,
        private readonly ?NotificacionService $notificacionService = null
    ) {
    }

    public function getContextoJuez(User $juez, ?int $categoriaId = null, ?int $rondaId = null, ?string $sessionId = null): array
    {
        $asignaciones = AsignacionJuezCategoria::query()
            ->with([
                'categoria' => fn ($query) => $query->withCount([
                    'inscripciones as equipos_inscritos_count',
                ]),
                'categoria.competencia:id,nombre',
                'categoria.configCalificacion.mecanismo:id,codigo,nombre',
                'categoria.rondas:id,categoria_id,nombre,tipo,estado,orden,cantidad_intentos,intentos_consecutivos,clasifican_cantidad,criterio_clasificacion,es_final',
            ])
            ->where('juez_user_id', $juez->id)
            ->orderBy('categoria_id')
            ->get();

        $categorias = $asignaciones
            ->map(function (AsignacionJuezCategoria $asignacion) {
                $categoria = $asignacion->categoria;
                $config = $categoria?->configCalificacion;
                $mecanismo = $config?->mecanismo;

                return [
                    'asignacion_juez_id' => (int) $asignacion->id,
                    'rol' => $asignacion->rol,
                    'categoria' => [
                        'id' => (int) $categoria->id,
                        'nombre' => (string) $categoria->nombre,
                        'competencia_id' => (int) $categoria->competencia_id,
                        'competencia_nombre' => (string) ($categoria->competencia?->nombre ?? ''),
                        'imagen' => $categoria->imagen,
                        'imagen_url' => $categoria->imagen ? Storage::url($categoria->imagen) : null,
                        'equipos_inscritos_count' => (int) ($categoria->equipos_inscritos_count ?? 0),
                    ],
                    'config_calificacion' => $config ? [
                        'id' => (int) $config->id,
                        'mecanismo_codigo' => (string) ($mecanismo?->codigo ?? ''),
                        'mecanismo_nombre' => (string) ($mecanismo?->nombre ?? ''),
                        'unidad_resultado' => $config->unidad_resultado,
                        'orden_ranking' => (string) $config->orden_ranking,
                        'plantilla_resultado' => $this->registroMeta($config)['plantilla_resultado'] ?? null,
                        'esquema_jueces' => $this->esquemaJueces($config),
                        'promediar_jueces' => $this->promediaJueces($config),
                        'requiere_aprobacion_admin' => (bool) $config->requiere_aprobacion_admin,
                        'visible_publico_en_vivo' => (bool) $config->visible_publico_en_vivo,
                        'permite_edicion_juez' => (bool) $config->permite_edicion_juez,
                    ] : null,
                    'rondas' => $categoria->rondas
                        ->filter(fn (Ronda $ronda) => ($ronda->estado ?? 'activa') === 'activa')
                        ->sortBy('orden')
                        ->values()
                        ->map(fn (Ronda $ronda) => [
                            'id' => (int) $ronda->id,
                            'nombre' => (string) $ronda->nombre,
                            'orden' => (int) ($ronda->orden ?? 1),
                            'cantidad_intentos' => (int) ($ronda->cantidad_intentos ?? 1),
                            'intentos_consecutivos' => (bool) ($ronda->intentos_consecutivos ?? false),
                            'clasifican_cantidad' => $ronda->clasifican_cantidad !== null ? (int) $ronda->clasifican_cantidad : null,
                            'criterio_clasificacion' => (string) ($ronda->criterio_clasificacion ?? ''),
                            'es_final' => (bool) ($ronda->es_final ?? false),
                        ])
                        ->all(),
                ];
            })
            ->values();

        $categoriaSeleccionada = $categoriaId
            ? $categorias->firstWhere('categoria.id', $categoriaId)
            : null;

        $rondas = collect($categoriaSeleccionada['rondas'] ?? []);
        $rondaSeleccionada = $rondas->firstWhere('id', $rondaId) ?? $rondas->first();

        $categoriaSeleccionadaId = $categoriaSeleccionada['categoria']['id'] ?? null;
        $rondaSeleccionadaId = $rondaSeleccionada['id'] ?? null;
        $bloqueoRegistro = ['activo' => false, 'bloqueado' => false];

        if ($categoriaSeleccionadaId) {
            try {
                $bloqueoRegistro = $this->tomarBloqueoRegistroSiAplica(
                    $juez,
                    (int) $categoriaSeleccionadaId,
                    $sessionId
                );
            } catch (ValidationException $exception) {
                $message = collect($exception->errors())->flatten()->first()
                    ?? $exception->getMessage();

                $bloqueoRegistro = [
                    'activo' => true,
                    'bloqueado' => true,
                    'categoria_id' => (int) $categoriaSeleccionadaId,
                    'message' => $message,
                ];
            }
        }

        return [
            'categorias' => $categorias->all(),
            'seleccion' => [
                'categoria_id' => $categoriaSeleccionadaId,
                'ronda_id' => $rondaSeleccionadaId,
            ],
            'sorteo' => $rondaSeleccionadaId
                ? $this->getSorteoVigente((int) $rondaSeleccionadaId)
                : null,
            'participantes_sorteo' => $categoriaSeleccionadaId && $rondaSeleccionadaId
                ? $this->getParticipantesSorteo(
                    (int) $categoriaSeleccionadaId,
                    (int) $rondaSeleccionadaId
                )
                : [],
            'bloqueo_registro' => $bloqueoRegistro,
            'equipos' => $categoriaSeleccionadaId && $rondaSeleccionadaId
                ? $this->getEquiposPorEvaluar(
                    $juez,
                    (int) $categoriaSeleccionadaId,
                    (int) $rondaSeleccionadaId
                )
                : [],
        ];
    }

    public function generarSorteo(User $juez, int $rondaId, bool $regenerar = false, ?string $sessionId = null): array
    {
        $ronda = Ronda::query()->with('categoria.configCalificacion.mecanismo')->find($rondaId);

        if (! $ronda || ! $ronda->categoria) {
            throw ValidationException::withMessages([
                'ronda_id' => 'La ronda seleccionada no existe o no tiene categoría asociada.',
            ]);
        }

        $categoria = $ronda->categoria;
        $config = $categoria->configCalificacion;

        if (! $config) {
            throw ValidationException::withMessages([
                'ronda_id' => 'La categoría de la ronda no tiene configuración de calificación.',
            ]);
        }

        $asignacion = AsignacionJuezCategoria::query()
            ->where('categoria_id', $categoria->id)
            ->where('juez_user_id', $juez->id)
            ->first();

        if (! $asignacion) {
            throw ValidationException::withMessages([
                'ronda_id' => 'No tienes asignacion activa para esta categoria.',
            ]);
        }

        if ($this->usaRegistroCualquierJuez($config)) {
            $this->registroLockService->asegurarDisponibleParaJuez(
                $juez,
                (int) $categoria->id,
                (int) $asignacion->id,
                $sessionId
            );
        }

        $existente = Sorteo::query()
            ->where('ronda_id', $ronda->id)
            ->where('estado', '!=', 'anulado')
            ->first();

        if ($existente && ! $regenerar) {
            return $this->serializarSorteo($existente->load('detalles.inscripcion.equipo'));
        }

        if ($this->rondaTieneResultadosRegistrados((int) $ronda->id)) {
            throw ValidationException::withMessages([
                'ronda_id' => $regenerar
                    ? 'No se puede generar nuevamente el sorteo porque esta ronda ya tiene resultados registrados.'
                    : 'No se puede generar el sorteo porque esta ronda ya tiene resultados registrados.',
            ]);
        }

        $inscripciones = $this->inscripcionesSorteables((int) $categoria->id, (int) $ronda->id);

        if ($inscripciones->isEmpty()) {
            throw ValidationException::withMessages([
                'ronda_id' => 'No hay participantes aprobados para sortear en esta categoria.',
            ]);
        }

        $tipoSorteo = (($this->registroMeta($config)['modalidad_competencia'] ?? '') === 'enfrentamiento_directo')
            ? 'enfrentamiento'
            : 'individual';

        $detalles = $tipoSorteo === 'enfrentamiento'
            ? $this->crearDetallesEnfrentamiento($inscripciones)
            : $this->crearDetallesIndividual($inscripciones);

        $sorteo = DB::transaction(function () use ($ronda, $tipoSorteo, $detalles, $regenerar) {
            if ($regenerar) {
                Sorteo::query()
                    ->where('ronda_id', $ronda->id)
                    ->where('estado', '!=', 'anulado')
                    ->update(['estado' => 'anulado']);
            }

            $sorteo = Sorteo::create([
                'ronda_id' => $ronda->id,
                'tipo_sorteo' => $tipoSorteo,
                'estado' => 'generado',
                'reglas_json' => [
                    'evitar_misma_institucion' => $tipoSorteo === 'enfrentamiento',
                    'usar_ronda_previa' => $tipoSorteo === 'enfrentamiento',
                    'usar_pase_directo' => $tipoSorteo === 'enfrentamiento',
                    'estado_pase_directo' => $tipoSorteo === 'enfrentamiento' ? 'directo' : null,
                ],
            ]);

            $sorteo->detalles()->createMany($detalles);

            $this->sincronizarParticipantesSorteo($ronda, $detalles);

            if ($tipoSorteo === 'enfrentamiento') {
                $this->actualizarMetadataRondaEnfrentamiento($ronda, count($detalles));
            }

            return $sorteo->load('detalles.inscripcion.equipo');
        });

        return $this->serializarSorteo($sorteo);
    }

    public function excluirParticipanteDelSorteo(User $juez, int $rondaId, int $inscripcionId, ?string $sessionId = null): array
    {
        $ronda = Ronda::query()->with('categoria.configCalificacion.mecanismo')->find($rondaId);

        if (! $ronda || ! $ronda->categoria) {
            throw ValidationException::withMessages([
                'ronda_id' => 'La ronda seleccionada no existe o no tiene categoría asociada.',
            ]);
        }

        $categoria = $ronda->categoria;

        $asignacion = AsignacionJuezCategoria::query()
            ->where('categoria_id', $categoria->id)
            ->where('juez_user_id', $juez->id)
            ->first();

        if (! $asignacion) {
            throw ValidationException::withMessages([
                'ronda_id' => 'No tienes asignacion activa para esta categoria.',
            ]);
        }

        $inscripcion = Inscripcion::query()
            ->with('equipo')
            ->where('id', $inscripcionId)
            ->where('categoria_id', $categoria->id)
            ->aprobadas()
            ->first();

        if (! $inscripcion) {
            throw ValidationException::withMessages([
                'inscripcion_id' => 'El participante no esta habilitado para esta categoria.',
            ]);
        }

        $sorteoExistente = Sorteo::query()
            ->where('ronda_id', $ronda->id)
            ->where('estado', '!=', 'anulado')
            ->exists();

        if ($sorteoExistente) {
            throw ValidationException::withMessages([
                'ronda_id' => 'No se puede excluir participantes con un sorteo ya generado. Anula o regenera el sorteo antes de continuar.',
            ]);
        }

        RondaParticipante::query()->updateOrCreate(
            [
                'ronda_id' => $ronda->id,
                'inscripcion_id' => $inscripcion->id,
            ],
            [
                'equipo_id' => (int) $inscripcion->equipo_id,
                'estado' => 'excluido',
                'origen_clasificacion_id' => null,
            ]
        );

        return [
            'message' => 'Participante excluido correctamente del sorteo.',
            'contexto' => $this->getContextoJuez(
                $juez,
                (int) $categoria->id,
                (int) $ronda->id,
                $sessionId
            ),
        ];
    }

    public function construirFormulario(User $juez, int $rondaId, int $inscripcionId, int $intentoNumero = 1, ?string $sessionId = null): array
    {
        $contexto = $this->resolverContextoEvaluacion($juez, $rondaId, $inscripcionId, $intentoNumero);
        $this->asegurarBloqueoRegistroSiAplica($juez, $contexto, $sessionId);
        $resultado = $contexto['resultado'];
        $config = $contexto['config'];
        $campos = $this->normalizarCampos($config);

        return [
            'ronda' => [
                'id' => (int) $contexto['ronda']->id,
                'nombre' => (string) $contexto['ronda']->nombre,
                'orden' => (int) ($contexto['ronda']->orden ?? 1),
                'cantidad_intentos' => (int) ($contexto['ronda']->cantidad_intentos ?? 1),
                'intentos_consecutivos' => (bool) ($contexto['ronda']->intentos_consecutivos ?? false),
                'intento_actual' => (int) $contexto['intento_numero'],
            ],
            'categoria' => [
                'id' => (int) $contexto['categoria']->id,
                'nombre' => (string) $contexto['categoria']->nombre,
            ],
            'competencia' => [
                'id' => (int) $contexto['categoria']->competencia_id,
                'nombre' => (string) ($contexto['categoria']->competencia?->nombre ?? ''),
            ],
            'equipo' => [
                'id' => (int) $contexto['inscripcion']->equipo_id,
                'nombre' => (string) ($contexto['inscripcion']->equipo?->nombre ?? ''),
                'institucion' => (string) ($contexto['inscripcion']->equipo?->institucion ?? ''),
                'inscripcion_id' => (int) $contexto['inscripcion']->id,
                'nombre_prototipo' => $contexto['inscripcion']->nombre_prototipo,
            ],
            'config_calificacion' => [
                'id' => (int) $config->id,
                'mecanismo_codigo' => (string) ($config->mecanismo?->codigo ?? ''),
                'mecanismo_nombre' => (string) ($config->mecanismo?->nombre ?? ''),
                'unidad_resultado' => $config->unidad_resultado,
                'orden_ranking' => (string) $config->orden_ranking,
                'plantilla_resultado' => $this->registroMeta($config)['plantilla_resultado'] ?? null,
                'esquema_jueces' => $this->esquemaJueces($config),
                'promediar_jueces' => $this->promediaJueces($config),
                'promediar_resultado_final' => $this->promediaResultadoFinal($config),
                'permite_edicion_juez' => (bool) $config->permite_edicion_juez,
                'requiere_aprobacion_admin' => (bool) $config->requiere_aprobacion_admin,
                'campos' => $campos,
            ],
            'resultado_actual' => $resultado ? $this->serializarResultado($resultado, $campos) : null,
        ];
    }

    public function guardarEvaluacion(User $juez, array $payload, ?string $sessionId = null): array
    {
        $intentoNumero = (int) ($payload['intento_numero'] ?? 1);

        $contexto = $this->resolverContextoEvaluacion(
            $juez,
            (int) $payload['ronda_id'],
            (int) $payload['inscripcion_id'],
            $intentoNumero
        );

        $config = $contexto['config'];
        $this->asegurarBloqueoRegistroSiAplica($juez, $contexto, $sessionId);
        $campos = $this->normalizarCampos($config);
        $validated = $this->validarPayloadSegunConfig($payload, $campos, $config);

        $this->validarTurnoActualSorteo($juez, $contexto);

        $resultado = DB::transaction(function () use ($contexto, $config, $campos, $validated, $juez) {
            if ($this->configEsTablaEnfrentamiento($config)) {
                return $this->guardarEvaluacionEnfrentamientoCompleto($juez, $contexto, $config, $campos, $validated);
            }

            $this->bloquearResultadoOficialPorInscripcionSiAplica($config, (int) $contexto['ronda']->id, (int) $contexto['inscripcion']->id, (int) $contexto['intento_numero']);

            $existente = $this->buscarResultadoPorInscripcionSegunEsquema(
                $juez,
                $config,
                (int) $contexto['ronda']->id,
                (int) $contexto['inscripcion']->id,
                (int) $contexto['intento_numero'],
                true
            );

            $this->validarResultadoPerteneceAJuezMultiJuez($existente, $juez, $config);

            $versionActual = (int) ($existente?->version ?? 0);
            $versionEsperada = (int) ($validated['version'] ?? 0);

            if ($existente && $versionEsperada !== $versionActual) {
                throw new EvaluacionConcurrencyException($existente->fresh());
            }

            if ($existente && ! $config->permite_edicion_juez) {
                throw ValidationException::withMessages([
                    'version' => 'La categoría no permite edición del juez una vez registrado el resultado.',
                ]);
            }

            $this->validarMotivoParaEdicionDeOtroJuez($existente, $juez, $validated['motivo_cambio'] ?? null);

            $normalizado = $this->normalizarValoresEvaluacion(
                $config,
                $campos,
                Arr::get($validated, 'payload', []),
                $validated['observaciones'] ?? null,
                $existente,
            );

            $resultado = $existente ?? new Resultado();

            if (! $existente) {
                $resultado->ronda_id = $contexto['ronda']->id;
                $resultado->equipo_id = $contexto['inscripcion']->equipo_id;
                $resultado->juez_user_id = $juez->id;
                $resultado->competencia_id = $contexto['categoria']->competencia_id;
                $resultado->categoria_id = $contexto['categoria']->id;
                $resultado->inscripcion_id = $contexto['inscripcion']->id;
                $resultado->asignacion_juez_id = $contexto['asignacion']->id;
                $resultado->intento_numero = $contexto['intento_numero'];
            }

            $resultado->fill([
                'puntaje' => $normalizado['puntaje'],
                'tiempo' => $normalizado['tiempo'],
                'penalizaciones' => $normalizado['penalizaciones'],
                'estado' => 'registrado',
                'valor_principal' => $normalizado['valor_principal'],
                'valor_secundario' => $normalizado['valor_secundario'],
                'payload_json' => $normalizado['payload_json'],
                'observaciones' => $normalizado['observaciones'],
                'version' => $versionActual + 1,
            ]);
            $resultado->save();

            $this->registrarHistorial(
                $resultado,
                $existente,
                $juez,
                $validated['motivo_cambio'] ?? null
            );

            return $resultado->fresh();
        });

        $publicacionAutomatica = $this->publicarResultadoAutomaticamente($resultado, $juez);

        return $this->construirFormulario($juez, (int) $payload['ronda_id'], (int) $payload['inscripcion_id'], $intentoNumero, $sessionId)
            + [
                'guardado' => true,
                'resultado' => $this->serializarResultado($resultado, $campos),
                'publicacion_automatica' => $publicacionAutomatica,
            ];
    }

    private function guardarEvaluacionEnfrentamientoCompleto(
        User $juez,
        array $contexto,
        ConfigCalificacion $config,
        array $campos,
        array $validated
    ): Resultado {
        [$sorteo, $detalleActual] = $this->obtenerSorteoYDetalle(
            (int) $contexto['ronda']->id,
            (int) $contexto['inscripcion']->id
        );

        if (! $sorteo || ! $detalleActual || $sorteo->tipo_sorteo !== 'enfrentamiento' || $detalleActual->grupo === null) {
            throw ValidationException::withMessages([
                'inscripcion_id' => 'Esta plantilla requiere un enfrentamiento directo generado en el sorteo.',
            ]);
        }

        $detallesGrupo = $sorteo->detalles
            ->filter(fn (SorteoDetalle $item) => (int) $item->grupo === (int) $detalleActual->grupo && $item->estado !== 'directo')
            ->sortBy('orden')
            ->values();

        if ($detallesGrupo->count() < 2) {
            throw ValidationException::withMessages([
                'inscripcion_id' => 'El encuentro necesita dos participantes para registrar la tabla de enfrentamiento.',
            ]);
        }

        $resultadoSeleccionado = null;
        $versionEsperada = (int) ($validated['version'] ?? 0);

        foreach ($detallesGrupo as $detalle) {
            $inscripcion = $detalle->inscripcion;

            if (! $this->inscripcionAprobada($inscripcion)) {
                continue;
            }

            $this->bloquearResultadoOficialPorInscripcionSiAplica($config, (int) $contexto['ronda']->id, (int) $inscripcion->id, (int) $contexto['intento_numero']);

            $existente = $this->buscarResultadoPorInscripcionSegunEsquema(
                $juez,
                $config,
                (int) $contexto['ronda']->id,
                (int) $inscripcion->id,
                (int) $contexto['intento_numero'],
                true
            );

            $this->validarResultadoPerteneceAJuezMultiJuez($existente, $juez, $config);

            $versionActual = (int) ($existente?->version ?? 0);

            if ((int) $inscripcion->id === (int) $contexto['inscripcion']->id && $existente && $versionEsperada !== $versionActual) {
                throw new EvaluacionConcurrencyException($existente->fresh());
            }

            if ($existente && ! $config->permite_edicion_juez) {
                throw ValidationException::withMessages([
                    'version' => 'La categorÃ­a no permite ediciÃ³n del juez una vez registrado el resultado.',
                ]);
            }

            $this->validarMotivoParaEdicionDeOtroJuez($existente, $juez, $validated['motivo_cambio'] ?? null);

            $normalizado = $this->normalizarValoresEvaluacion(
                $config,
                $campos,
                Arr::get($validated, 'payload', []),
                $validated['observaciones'] ?? null,
                $existente,
                (string) ($detalle->lado === 'B' ? 'B' : 'A')
            );

            $resultado = $existente ?? new Resultado();

            if (! $existente) {
                $resultado->ronda_id = $contexto['ronda']->id;
                $resultado->equipo_id = $inscripcion->equipo_id;
                $resultado->juez_user_id = $juez->id;
                $resultado->competencia_id = $contexto['categoria']->competencia_id;
                $resultado->categoria_id = $contexto['categoria']->id;
                $resultado->inscripcion_id = $inscripcion->id;
                $resultado->asignacion_juez_id = $contexto['asignacion']->id;
                $resultado->intento_numero = $contexto['intento_numero'];
            }

            $resultado->fill([
                'puntaje' => $normalizado['puntaje'],
                'tiempo' => $normalizado['tiempo'],
                'penalizaciones' => $normalizado['penalizaciones'],
                'estado' => 'registrado',
                'valor_principal' => $normalizado['valor_principal'],
                'valor_secundario' => $normalizado['valor_secundario'],
                'payload_json' => $normalizado['payload_json'],
                'observaciones' => $normalizado['observaciones'],
                'version' => $versionActual + 1,
            ]);
            $resultado->save();

            $this->registrarHistorial(
                $resultado,
                $existente,
                $juez,
                $validated['motivo_cambio'] ?? null
            );

            if ((int) $inscripcion->id === (int) $contexto['inscripcion']->id) {
                $resultadoSeleccionado = $resultado->fresh();
            }
        }

        if (! $resultadoSeleccionado) {
            throw ValidationException::withMessages([
                'inscripcion_id' => 'No se pudo registrar el resultado del participante seleccionado.',
            ]);
        }

        return $resultadoSeleccionado;
    }

    public function terminarEncuentro(User $juez, int $rondaId, int $inscripcionId, array $payloadActual = [], ?string $sessionId = null): array
    {
        $contexto = $this->resolverContextoEvaluacion($juez, $rondaId, $inscripcionId);
        $this->asegurarBloqueoRegistroSiAplica($juez, $contexto, $sessionId);
        [$sorteo, $detalle] = $this->obtenerSorteoYDetalle((int) $contexto['ronda']->id, (int) $contexto['inscripcion']->id);

        if (! $sorteo || ! $detalle || $sorteo->tipo_sorteo !== 'enfrentamiento' || $detalle->grupo === null) {
            throw ValidationException::withMessages([
                'inscripcion_id' => 'Esta acción solo está disponible para encuentros de enfrentamiento directo.',
            ]);
        }

        $inscripcionIdsGrupo = $sorteo->detalles
            ->filter(fn (SorteoDetalle $item) => (int) $item->grupo === (int) $detalle->grupo && $item->estado !== 'directo')
            ->map(fn (SorteoDetalle $item) => (int) ($item->inscripcion_id ?? 0))
            ->filter()
            ->values();

        if ($this->esquemaJueces($contexto['config']) === 'evaluacion_multi_juez') {
            $grupoCompleto = $inscripcionIdsGrupo->every(fn (int $grupoInscripcionId) => $this->evaluacionInscripcionIntentoCompleta(
                (int) $contexto['categoria']->id,
                (int) $contexto['ronda']->id,
                $grupoInscripcionId,
                (int) $contexto['intento_numero'],
                $contexto['config']
            ));

            if (! $grupoCompleto) {
                throw ValidationException::withMessages([
                    'inscripcion_id' => 'El encuentro no puede finalizar hasta que todos los jueces asignados registren su calificacion.',
                ]);
            }
        }

        $resultadosGuardadosQuery = Resultado::query()
            ->where('ronda_id', $contexto['ronda']->id)
            ->whereIn('inscripcion_id', $inscripcionIdsGrupo);

        if (! $this->usaRegistroCualquierJuez($contexto['config'])) {
            $resultadosGuardadosQuery->where('juez_user_id', $juez->id);
        }

        $resultadosGuardados = $resultadosGuardadosQuery
            ->latest('updated_at')
            ->get();

        if ($resultadosGuardados->isEmpty()) {
            throw ValidationException::withMessages([
                'inscripcion_id' => 'Guarda el resultado del encuentro antes de finalizarlo.',
            ]);
        }

        if (! empty($payloadActual)) {
            $campos = $this->normalizarCampos($contexto['config']);
            $payloadGuardado = $resultadosGuardados->first(function (Resultado $resultadoGuardado) use ($contexto, $campos, $payloadActual) {
                $normalizado = $this->normalizarValoresEvaluacion(
                    $contexto['config'],
                    $campos,
                    $payloadActual,
                    $resultadoGuardado->observaciones,
                    $resultadoGuardado
                );

                return $this->payloadsEquivalentes($normalizado['payload_json'], $resultadoGuardado->payload_json ?? []);
            });

            if (! $payloadGuardado) {
                throw ValidationException::withMessages([
                    'payload' => 'Guarda el marcador antes de terminar el encuentro.',
                ]);
            }
        }

        $this->validarGanadorGrupoEnfrentamiento($sorteo, $detalle, $contexto['config']);
        $this->marcarGrupoSorteoCompletado($sorteo, $detalle);
        $rondaCompleta = $this->rondaCompleta((int) $contexto['ronda']->id);
        $publicarEnVivo = (bool) $contexto['config']->visible_publico_en_vivo;
        $estadoPublicacion = $publicarEnVivo
            ? ($rondaCompleta ? 'cerrado' : 'visible')
            : 'borrador';

        $this->consolidacionService->consolidarYPublicarAutomaticamente(
            (int) $contexto['categoria']->competencia_id,
            (int) $contexto['categoria']->id,
            (int) $contexto['ronda']->id,
            $juez,
            $estadoPublicacion
        );

        $categoriaCompleta = false;

        if ($rondaCompleta) {
            Ronda::query()
                ->where('id', $contexto['ronda']->id)
                ->where('estado', '!=', 'cerrada')
                ->update(['estado' => 'cerrada']);

            $this->avanzarLlaveEnfrentamientoAutomaticamente(
                $contexto['ronda']->fresh(),
                $contexto['config'],
                $sorteo->fresh('detalles.inscripcion.equipo'),
                $juez
            );

            $this->sincronizarPodioFinalEnfrentamiento(
                (int) $contexto['categoria']->id,
                (int) $contexto['categoria']->competencia_id,
                $contexto['config'],
                $juez
            );

            $categoriaCompleta = $this->marcarCategoriaFinalizadaSiCompleta((int) $contexto['categoria']->id, $juez);
        }

        return [
            'terminado' => true,
            'ronda_completa' => $rondaCompleta,
            'categoria_completa' => $categoriaCompleta,
            'estado_publicacion' => $estadoPublicacion,
        ];
    }

    public function corregirEvaluacionAdministrativa(User $admin, Resultado $resultado, array $data): Resultado
    {
        $resultado->loadMissing('categoria.configCalificacion.mecanismo');
        $config = $resultado->categoria?->configCalificacion;

        if (! $config) {
            throw ValidationException::withMessages([
                'resultado' => 'La evaluación no tiene configuración de calificación.',
            ]);
        }

        if (trim((string) ($data['motivo_cambio'] ?? '')) === '') {
            throw ValidationException::withMessages([
                'motivo_cambio' => 'El motivo de corrección es obligatorio.',
            ]);
        }

        $campos = $this->normalizarCampos($config);
        $validated = $this->validarPayloadSegunConfig([
            'ronda_id' => (int) $resultado->ronda_id,
            'equipo_id' => (int) $resultado->equipo_id,
            'version' => (int) $resultado->version,
            'observaciones' => $data['observaciones'] ?? null,
            'motivo_cambio' => $data['motivo_cambio'] ?? null,
            'payload' => $data['payload'] ?? [],
        ], $campos, $config);

        return DB::transaction(function () use ($resultado, $config, $campos, $validated, $admin) {
            $actual = Resultado::query()
                ->whereKey($resultado->id)
                ->lockForUpdate()
                ->firstOrFail();

            $anterior = new Resultado();
            $anterior->setRawAttributes($actual->getRawOriginal(), true);

            $normalizado = $this->normalizarValoresEvaluacion(
                $config,
                $campos,
                Arr::get($validated, 'payload', []),
                $validated['observaciones'] ?? null,
                $actual,
            );

            $actual->fill([
                'puntaje' => $normalizado['puntaje'],
                'tiempo' => $normalizado['tiempo'],
                'penalizaciones' => $normalizado['penalizaciones'],
                'estado' => 'registrado',
                'valor_principal' => $normalizado['valor_principal'],
                'valor_secundario' => $normalizado['valor_secundario'],
                'payload_json' => $normalizado['payload_json'],
                'observaciones' => $normalizado['observaciones'],
                'version' => ((int) $actual->version) + 1,
            ]);
            $actual->save();

            $this->registrarHistorial(
                $actual,
                $anterior,
                $admin,
                (string) $validated['motivo_cambio']
            );

            $this->publicarResultadoAutomaticamente($actual, $admin);

            return $actual->fresh();
        });
    }

    private function publicarResultadoAutomaticamente(Resultado $resultado, User $juez): array
    {
        try {
            if (! $this->resultadoEsEnfrentamiento($resultado)) {
                $this->marcarSorteoCompletado($resultado);
            }

            $rondaCompleta = $this->rondaCompleta((int) $resultado->ronda_id);
            $publicarEnVivo = (bool) ConfigCalificacion::query()
                ->where('categoria_id', $resultado->categoria_id)
                ->value('visible_publico_en_vivo');
            $estadoPublicacion = $publicarEnVivo
                ? ($rondaCompleta ? 'cerrado' : 'visible')
                : 'borrador';

            $vistaClasificacion = $this->consolidacionService->consolidarYPublicarAutomaticamente(
                (int) $resultado->competencia_id,
                (int) $resultado->categoria_id,
                (int) $resultado->ronda_id,
                $juez,
                $estadoPublicacion
            );

            if ($rondaCompleta) {
                $this->clasificarSiguienteRondaAutomaticamente($resultado, $vistaClasificacion['rows'] ?? [], $juez);

                Ronda::query()
                    ->where('id', $resultado->ronda_id)
                    ->where('estado', '!=', 'cerrada')
                    ->update(['estado' => 'cerrada']);

                $categoriaCompleta = $this->marcarCategoriaFinalizadaSiCompleta((int) $resultado->categoria_id, $juez);
            } else {
                $categoriaCompleta = false;
            }

            return [
                'publicada' => $publicarEnVivo,
                'ronda_completa' => $rondaCompleta,
                'categoria_completa' => $categoriaCompleta,
                'estado_publicacion' => $estadoPublicacion,
            ];
        } catch (Throwable $exception) {
            Log::warning('No se pudo publicar automaticamente la evaluacion del juez.', [
                'resultado_id' => (int) $resultado->id,
                'ronda_id' => (int) $resultado->ronda_id,
                'categoria_id' => (int) $resultado->categoria_id,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            return [
                'publicada' => false,
                'ronda_completa' => false,
                'categoria_completa' => false,
                'estado_publicacion' => null,
            ];
        }
    }

    private function marcarCategoriaFinalizadaSiCompleta(int $categoriaId, ?User $actor = null): bool
    {
        $rondas = Ronda::query()
            ->where('categoria_id', $categoriaId)
            ->get(['id', 'estado']);

        if ($rondas->isEmpty()) {
            return false;
        }

        $categoriaCompleta = $rondas->every(fn (Ronda $ronda) => (string) $ronda->estado === 'cerrada');

        if (! $categoriaCompleta) {
            return false;
        }

        Categoria::query()
            ->where('id', $categoriaId)
            ->where('estado_resultados', '!=', 'finalizada')
            ->update([
                'estado_resultados' => 'finalizada',
                'resultados_finalizados_at' => now(),
            ]);

        $categoria = Categoria::query()
            ->with('competencia:id,nombre')
            ->find($categoriaId);

        if ($categoria) {
            $this->notificaciones()->notificarResultadosCategoriaFinalizada($categoria, $actor);
        }

        return true;
    }

    private function notificaciones(): NotificacionService
    {
        return $this->notificacionService ?? app(NotificacionService::class);
    }

    private function clasificarSiguienteRondaAutomaticamente(Resultado $resultado, array $filasClasificacion, User $juez): void
    {
        $ronda = Ronda::query()->find($resultado->ronda_id);

        if (! $ronda || ! $ronda->clasifican_cantidad || (int) $ronda->clasifican_cantidad < 1) {
            return;
        }

        $siguiente = Ronda::query()
            ->where('categoria_id', $resultado->categoria_id)
            ->where('orden', '>', (int) $ronda->orden)
            ->orderBy('orden')
            ->first();

        if (! $siguiente || $this->rondaTieneResultadosRegistrados((int) $siguiente->id)) {
            return;
        }

        $clasificados = collect($filasClasificacion)
            ->sortBy(fn (array $fila) => (int) ($fila['posicion'] ?? 999999))
            ->take((int) $ronda->clasifican_cantidad)
            ->values();

        if ($clasificados->isEmpty()) {
            return;
        }

        DB::transaction(function () use ($resultado, $siguiente, $clasificados) {
            RondaParticipante::query()
                ->where('ronda_id', $siguiente->id)
                ->delete();

            foreach ($clasificados as $fila) {
                $inscripcionId = Inscripcion::query()
                    ->where('categoria_id', $resultado->categoria_id)
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
                    'origen_clasificacion_id' => Clasificacion::query()
                        ->where('ronda_id', $resultado->ronda_id)
                        ->where('equipo_id', (int) $fila['equipo_id'])
                        ->value('id'),
                ]);
            }

            $siguiente->update(['estado' => 'activa']);
        });

        try {
            $this->generarSorteo($juez, (int) $siguiente->id, true);
        } catch (Throwable $exception) {
            Log::warning('No se pudo generar automaticamente el sorteo de la siguiente ronda.', [
                'ronda_id' => (int) $siguiente->id,
                'categoria_id' => (int) $resultado->categoria_id,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    private function avanzarLlaveEnfrentamientoAutomaticamente(
        ?Ronda $ronda,
        ConfigCalificacion $config,
        ?Sorteo $sorteo,
        User $juez
    ): void {
        if (! $ronda || ! $sorteo || $sorteo->tipo_sorteo !== 'enfrentamiento') {
            return;
        }

        if ((bool) $ronda->es_final || (string) $ronda->tipo === 'tercer_lugar') {
            return;
        }

        $resueltos = $this->resolverResultadosGruposEnfrentamiento($sorteo, $config);

        if ($resueltos->isEmpty()) {
            return;
        }

        $directos = $sorteo->detalles
            ->filter(fn (SorteoDetalle $detalle) => (string) $detalle->estado === 'directo')
            ->map(fn (SorteoDetalle $detalle) => $detalle->inscripcion)
            ->filter(fn (?Inscripcion $inscripcion) => $this->inscripcionAprobada($inscripcion))
            ->values();

        $ganadores = $resueltos->pluck('ganador')->filter()->values();
        $participantesSiguiente = $directos->concat($ganadores)->unique('id')->values();
        $cantidadSiguiente = $participantesSiguiente->count();

        if ($cantidadSiguiente === 2 && $directos->isEmpty() && $resueltos->count() === 2) {
            $perdedoresSemifinal = $resueltos->pluck('perdedor')->filter()->values();

            $siguienteOrden = $this->siguienteOrdenRondaCategoria((int) $ronda->categoria_id);
            $tercerLugar = $this->prepararRondaAutomaticaEnfrentamiento($ronda, 'tercer_lugar', $perdedoresSemifinal, false, $siguienteOrden);
            $final = $this->prepararRondaAutomaticaEnfrentamiento($ronda, 'final', $participantesSiguiente, true, $siguienteOrden + 1);

            $this->generarSorteoSeguro($juez, $tercerLugar);
            $this->generarSorteoSeguro($juez, $final);

            return;
        }

        if ($cantidadSiguiente < 2) {
            return;
        }

        $tipo = $cantidadSiguiente === 4 ? 'semifinal' : 'libre';
        $siguiente = $this->prepararRondaAutomaticaEnfrentamiento($ronda, $tipo, $participantesSiguiente, false);

        $this->generarSorteoSeguro($juez, $siguiente);
    }

    private function prepararRondaAutomaticaEnfrentamiento(
        Ronda $origen,
        string $tipo,
        $inscripciones,
        bool $esFinal,
        ?int $ordenDeseado = null
    ): Ronda {
        $inscripciones = collect($inscripciones)
            ->filter(fn (?Inscripcion $inscripcion) => $this->inscripcionAprobada($inscripcion))
            ->unique('id')
            ->values();

        $ronda = Ronda::query()
            ->where('categoria_id', $origen->categoria_id)
            ->where('ronda_origen_id', $origen->id)
            ->where('tipo', $tipo)
            ->first();

        if (! $ronda) {
            $ordenRonda = $ordenDeseado ?? $this->siguienteOrdenRondaCategoria((int) $origen->categoria_id);
            $ronda = Ronda::create([
                'categoria_id' => $origen->categoria_id,
                'nombre' => $this->nombreRondaAutomaticaEnfrentamiento($tipo, $inscripciones->count(), $ordenRonda),
                'tipo' => $tipo,
                'orden' => $ordenRonda,
                'cantidad_intentos' => 1,
                'intentos_consecutivos' => false,
                'clasifican_cantidad' => null,
                'criterio_clasificacion' => 'ganador_enfrentamiento',
                'ronda_origen_id' => $origen->id,
                'es_final' => $esFinal,
                'estado' => 'activa',
            ]);
        } else {
            $payload = [
                'nombre' => $this->nombreRondaAutomaticaEnfrentamiento($tipo, $inscripciones->count(), (int) ($ordenDeseado ?? $ronda->orden)),
                'es_final' => $esFinal,
                'estado' => 'activa',
            ];

            if ($ordenDeseado !== null) {
                $payload['orden'] = $ordenDeseado;
            }

            $ronda->update($payload);
        }

        DB::transaction(function () use ($ronda, $inscripciones, $origen) {
            RondaParticipante::query()
                ->where('ronda_id', $ronda->id)
                ->delete();

            foreach ($inscripciones as $inscripcion) {
                RondaParticipante::create([
                    'ronda_id' => $ronda->id,
                    'inscripcion_id' => (int) $inscripcion->id,
                    'equipo_id' => (int) $inscripcion->equipo_id,
                    'estado' => 'clasificado',
                    'origen_clasificacion_id' => Clasificacion::query()
                        ->where('ronda_id', $origen->id)
                        ->where('equipo_id', (int) $inscripcion->equipo_id)
                        ->value('id'),
                ]);
            }
        });

        return $ronda->fresh();
    }

    private function resolverResultadosGruposEnfrentamiento(Sorteo $sorteo, ConfigCalificacion $config)
    {
        return $sorteo->detalles
            ->filter(fn (SorteoDetalle $detalle) => $detalle->estado !== 'directo' && $detalle->grupo !== null)
            ->groupBy(fn (SorteoDetalle $detalle) => (int) $detalle->grupo)
            ->map(function ($grupoDetalles) use ($sorteo, $config) {
                return $this->resolverGanadorGrupoEnfrentamiento($sorteo, $grupoDetalles, $config);
            })
            ->filter()
            ->values();
    }

    private function validarGanadorGrupoEnfrentamiento(Sorteo $sorteo, SorteoDetalle $detalle, ConfigCalificacion $config): void
    {
        $grupoDetalles = $sorteo->detalles
            ->filter(fn (SorteoDetalle $item) => (int) $item->grupo === (int) $detalle->grupo && $item->estado !== 'directo')
            ->values();

        if (! $this->resolverGanadorGrupoEnfrentamiento($sorteo, $grupoDetalles, $config)) {
            throw ValidationException::withMessages([
                'payload' => 'El encuentro necesita un ganador claro antes de finalizar. Revisa el marcador o los criterios.',
            ]);
        }
    }

    private function resolverGanadorGrupoEnfrentamiento(Sorteo $sorteo, $grupoDetalles, ConfigCalificacion $config): ?array
    {
        $ordenados = collect($grupoDetalles)->sortBy('orden')->values();
        $detalleA = $ordenados->firstWhere('lado', 'A') ?? $ordenados->get(0);
        $detalleB = $ordenados->firstWhere('lado', 'B') ?? $ordenados->get(1);

        if (! $detalleA?->inscripcion || ! $detalleB?->inscripcion) {
            return null;
        }

        $resultado = Resultado::query()
            ->where('ronda_id', $sorteo->ronda_id)
            ->whereIn('inscripcion_id', [
                (int) $detalleA->inscripcion->id,
                (int) $detalleB->inscripcion->id,
            ])
            ->whereIn('estado', ['registrado', 'publicado'])
            ->latest('updated_at')
            ->first();

        if (! $resultado) {
            return null;
        }

        $payload = is_array($resultado->payload_json) ? $resultado->payload_json : [];
        $plantilla = $this->plantillaResultado($config);

        if ($plantilla === 'marcador') {
            $valorA = $this->numberFromPayload($payload, 'marcador_equipo_a');
            $valorB = $this->numberFromPayload($payload, 'marcador_equipo_b');
        } elseif ($plantilla === 'tabla_enfrentamiento_criterios') {
            $campos = $this->normalizarCampos($config);
            $subtotalA = $this->sumWeightedConfiguredFields($payload, $campos, false, 'a');
            $penalA = $this->sumWeightedConfiguredFields($payload, $campos, true, 'a') ?? 0.0;
            $subtotalB = $this->sumWeightedConfiguredFields($payload, $campos, false, 'b');
            $penalB = $this->sumWeightedConfiguredFields($payload, $campos, true, 'b') ?? 0.0;
            $valorA = $subtotalA !== null ? round($subtotalA - $penalA, 3) : null;
            $valorB = $subtotalB !== null ? round($subtotalB - $penalB, 3) : null;
        } else {
            $resultados = Resultado::query()
                ->where('ronda_id', $sorteo->ronda_id)
                ->whereIn('inscripcion_id', [
                    (int) $detalleA->inscripcion->id,
                    (int) $detalleB->inscripcion->id,
                ])
                ->whereIn('estado', ['registrado', 'publicado'])
                ->get()
                ->keyBy(fn (Resultado $item) => (int) ($item->inscripcion_id ?? 0));
            $valorA = $resultados->get((int) $detalleA->inscripcion->id)?->valor_principal;
            $valorB = $resultados->get((int) $detalleB->inscripcion->id)?->valor_principal;
        }

        if ($valorA === null || $valorB === null || (float) $valorA === (float) $valorB) {
            return null;
        }

        $menorValorGana = ! in_array($plantilla, ['marcador', 'tabla_enfrentamiento_criterios'], true)
            && ($plantilla === 'tiempo' || (string) $config->orden_ranking === 'asc');
        $ganaA = $menorValorGana
            ? (float) $valorA < (float) $valorB
            : (float) $valorA > (float) $valorB;

        return [
            'ganador' => $ganaA ? $detalleA->inscripcion : $detalleB->inscripcion,
            'perdedor' => $ganaA ? $detalleB->inscripcion : $detalleA->inscripcion,
            'valor_a' => $valorA,
            'valor_b' => $valorB,
        ];
    }

    private function generarSorteoSeguro(User $juez, Ronda $ronda): void
    {
        try {
            $this->generarSorteo($juez, (int) $ronda->id, true);
        } catch (Throwable $exception) {
            Log::warning('No se pudo generar automaticamente el sorteo de la ronda de enfrentamiento.', [
                'ronda_id' => (int) $ronda->id,
                'categoria_id' => (int) $ronda->categoria_id,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    public function repararPodioFinalEnfrentamiento(int $competenciaId, int $categoriaId, User $juez): array
    {
        $categoria = Categoria::query()
            ->with(['configCalificacion.mecanismo'])
            ->where('competencia_id', $competenciaId)
            ->whereKey($categoriaId)
            ->firstOrFail();

        $config = $categoria->configCalificacion;

        if (! $config) {
            throw ValidationException::withMessages([
                'categoria_id' => 'La categoría seleccionada no tiene configuración de calificación.',
            ]);
        }

        $finalExistente = Ronda::query()
            ->where('categoria_id', $categoriaId)
            ->where('es_final', true)
            ->orderByDesc('orden')
            ->first()
            ?? Ronda::query()
                ->where('categoria_id', $categoriaId)
                ->orderByDesc('orden')
                ->first();

        if ($finalExistente) {
            $vistaCampeonUnico = $this->consolidacionService->consolidarCampeonUnicoEnfrentamiento(
                $competenciaId,
                $categoriaId,
                (int) $finalExistente->id,
                $juez,
                'cerrado'
            );

            if ($vistaCampeonUnico) {
                $this->marcarCategoriaFinalizadaSiCompleta($categoriaId, $juez);

                return $vistaCampeonUnico;
            }
        }

        $this->sincronizarTercerLugarEnfrentamiento($categoriaId, $competenciaId, $config, $juez);
        $this->sincronizarPodioFinalEnfrentamiento($categoriaId, $competenciaId, $config, $juez);

        $final = Ronda::query()
            ->where('categoria_id', $categoriaId)
            ->where('es_final', true)
            ->orderByDesc('orden')
            ->firstOrFail();

        return $this->consolidacionService->obtenerVista($competenciaId, $categoriaId, (int) $final->id);
    }

    private function sincronizarPodioFinalEnfrentamiento(
        int $categoriaId,
        int $competenciaId,
        ConfigCalificacion $config,
        User $juez
    ): void
    {
        $final = Ronda::query()
            ->where('categoria_id', $categoriaId)
            ->where('es_final', true)
            ->orderByDesc('orden')
            ->first();

        if (! $final || (string) $final->estado !== 'cerrada') {
            return;
        }

        $tercerLugar = Ronda::query()
            ->where('categoria_id', $categoriaId)
            ->where('tipo', 'tercer_lugar')
            ->orderByDesc('orden')
            ->first();

        $finalistas = Clasificacion::query()
            ->where('competencia_id', $competenciaId)
            ->where('categoria_id', $categoriaId)
            ->where('ronda_id', $final->id)
            ->orderBy('posicion')
            ->limit(2)
            ->get();

        if ($finalistas->isEmpty()) {
            return;
        }

        $podio = $finalistas->values();

        $tercero = null;

        if ($tercerLugar && (string) $tercerLugar->estado === 'cerrada') {
            $clasificacionesTercerLugar = Clasificacion::query()
                ->where('competencia_id', $competenciaId)
                ->where('categoria_id', $categoriaId)
                ->where('ronda_id', $tercerLugar->id)
                ->whereIn('estado_publicacion', ['visible', 'cerrado'])
                ->with(['equipo:id,nombre,institucion', 'inscripcion:id,equipo_id,nombre_prototipo'])
                ->orderBy('posicion')
                ->get();

            $tercero = $this->resolverGanadorEncuentroEnfrentamiento($tercerLugar, $config, $clasificacionesTercerLugar)
                ?? $clasificacionesTercerLugar->first();
        }

        $tercero ??= $this->resolverTercerLugarPorLlaveDeTres($final, $competenciaId, $categoriaId, $config);

        if ($tercero) {
            $terceroInscripcionId = $this->clasificacionInscripcionId($tercero);
            $yaExisteEnPodio = $podio->contains(
                fn (Clasificacion $clasificacion) => $terceroInscripcionId > 0
                    ? $this->clasificacionInscripcionId($clasificacion) === $terceroInscripcionId
                    : (int) $clasificacion->equipo_id === (int) $tercero->equipo_id
            );

            if (! $yaExisteEnPodio) {
                $podio->push($tercero);
            }
        }

        DB::transaction(function () use ($final, $podio, $competenciaId, $categoriaId, $juez) {
            Clasificacion::query()
                ->where('competencia_id', $competenciaId)
                ->where('categoria_id', $categoriaId)
                ->where('ronda_id', $final->id)
                ->delete();

            foreach ($podio->take(3)->values() as $index => $clasificacion) {
                Clasificacion::create([
                    'competencia_id' => $competenciaId,
                    'categoria_id' => $categoriaId,
                    'equipo_id' => (int) $clasificacion->equipo_id,
                    'inscripcion_id' => $this->clasificacionInscripcionId($clasificacion) ?: null,
                    'ronda_id' => (int) $final->id,
                    'puntaje_total' => $clasificacion->puntaje_total ?? 0,
                    'tiempo_total' => $clasificacion->tiempo_total,
                    'penal_total' => $clasificacion->penal_total ?? 0,
                    'posicion' => $index + 1,
                    'estado_publicacion' => 'cerrado',
                    'publicado_at' => now(),
                    'publicado_por' => $juez->id,
                    'origen_version' => (int) ($clasificacion->origen_version ?? 0),
                    'detalle_json' => $clasificacion->detalle_json,
                ]);
            }
        });

        event(new ResultadosActualizados($competenciaId, $categoriaId, (int) $final->id, 'cerrado', true));
    }

    private function sincronizarTercerLugarEnfrentamiento(
        int $categoriaId,
        int $competenciaId,
        ConfigCalificacion $config,
        User $juez
    ): void {
        $tercerLugar = Ronda::query()
            ->where('categoria_id', $categoriaId)
            ->where('tipo', 'tercer_lugar')
            ->orderByDesc('orden')
            ->first();

        if (! $tercerLugar || (string) $tercerLugar->estado !== 'cerrada') {
            return;
        }

        $clasificacionesTercerLugar = Clasificacion::query()
            ->where('competencia_id', $competenciaId)
            ->where('categoria_id', $categoriaId)
            ->where('ronda_id', $tercerLugar->id)
            ->whereIn('estado_publicacion', ['visible', 'cerrado'])
            ->with(['equipo:id,nombre,institucion', 'inscripcion:id,equipo_id,nombre_prototipo'])
            ->orderBy('posicion')
            ->get();

        if ($clasificacionesTercerLugar->count() < 2) {
            return;
        }

        $ganador = $this->resolverGanadorEncuentroEnfrentamiento($tercerLugar, $config, $clasificacionesTercerLugar)
            ?? $clasificacionesTercerLugar->first();

        if (! $ganador) {
            return;
        }

        $podio = collect([$ganador]);
        $ganadorInscripcionId = $this->clasificacionInscripcionId($ganador);

        $perdedor = $clasificacionesTercerLugar
            ->first(fn (Clasificacion $clasificacion) => $this->clasificacionInscripcionId($clasificacion) !== $ganadorInscripcionId)
            ?? $clasificacionesTercerLugar->first();

        if ($perdedor) {
            $podio->push($perdedor);
        }

        DB::transaction(function () use ($tercerLugar, $podio, $competenciaId, $categoriaId, $juez): void {
            Clasificacion::query()
                ->where('competencia_id', $competenciaId)
                ->where('categoria_id', $categoriaId)
                ->where('ronda_id', $tercerLugar->id)
                ->delete();

            foreach ($podio->take(2)->values() as $index => $clasificacion) {
                Clasificacion::create([
                    'competencia_id' => $competenciaId,
                    'categoria_id' => $categoriaId,
                    'equipo_id' => (int) $clasificacion->equipo_id,
                    'inscripcion_id' => $this->clasificacionInscripcionId($clasificacion) ?: null,
                    'ronda_id' => (int) $tercerLugar->id,
                    'puntaje_total' => $clasificacion->puntaje_total ?? 0,
                    'tiempo_total' => $clasificacion->tiempo_total,
                    'penal_total' => $clasificacion->penal_total ?? 0,
                    'posicion' => $index + 1,
                    'estado_publicacion' => 'cerrado',
                    'publicado_at' => now(),
                    'publicado_por' => $juez->id,
                    'origen_version' => (int) ($clasificacion->origen_version ?? 0),
                    'detalle_json' => $clasificacion->detalle_json,
                ]);
            }
        });

        event(new ResultadosActualizados($competenciaId, $categoriaId, (int) $tercerLugar->id, 'cerrado', true));
    }

    private function resolverTercerLugarPorLlaveDeTres(
        Ronda $final,
        int $competenciaId,
        int $categoriaId,
        ConfigCalificacion $config
    ): ?Clasificacion {
        if (! $final->ronda_origen_id) {
            return null;
        }

        $origen = Ronda::query()
            ->whereKey((int) $final->ronda_origen_id)
            ->where('categoria_id', $categoriaId)
            ->first();

        if (! $origen || (string) $origen->estado !== 'cerrada') {
            return null;
        }

        $sorteoOrigen = Sorteo::query()
            ->with('detalles.inscripcion.equipo')
            ->where('ronda_id', (int) $origen->id)
            ->where('estado', '!=', 'anulado')
            ->first();

        if (! $sorteoOrigen || (string) $sorteoOrigen->tipo_sorteo !== 'enfrentamiento') {
            return null;
        }

        $detalles = $sorteoOrigen->detalles
            ->filter(fn (SorteoDetalle $detalle) => $this->inscripcionAprobada($detalle->inscripcion))
            ->unique('inscripcion_id')
            ->values();
        $directos = $detalles->filter(fn (SorteoDetalle $detalle) => (string) $detalle->estado === 'directo')->values();
        $resueltos = $this->resolverResultadosGruposEnfrentamiento($sorteoOrigen, $config);

        if ($detalles->count() !== 3 || $directos->count() !== 1 || $resueltos->count() !== 1) {
            return null;
        }

        $perdedor = $resueltos->first()['perdedor'] ?? null;

        if (! $this->inscripcionAprobada($perdedor)) {
            return null;
        }

        $finalistas = Sorteo::query()
            ->with('detalles.inscripcion')
            ->where('ronda_id', (int) $final->id)
            ->where('estado', '!=', 'anulado')
            ->first()
            ?->detalles
            ?->pluck('inscripcion_id')
            ?->map(fn ($id) => (int) $id)
            ?->filter()
            ?->values() ?? collect();

        if ($finalistas->contains((int) $perdedor->id)) {
            return null;
        }

        return Clasificacion::query()
            ->with(['equipo:id,nombre,institucion', 'inscripcion:id,equipo_id,nombre_prototipo'])
            ->where('competencia_id', $competenciaId)
            ->where('categoria_id', $categoriaId)
            ->where('ronda_id', (int) $origen->id)
            ->where(function ($query) use ($perdedor) {
                $query->where('inscripcion_id', (int) $perdedor->id)
                    ->orWhere(function ($subquery) use ($perdedor) {
                        $subquery->whereNull('inscripcion_id')
                            ->where('equipo_id', (int) $perdedor->equipo_id);
                    });
            })
            ->orderBy('posicion')
            ->first();
    }

    private function resolverGanadorEncuentroEnfrentamiento(
        Ronda $ronda,
        ConfigCalificacion $config,
        Collection $clasificaciones
    ): ?Clasificacion {
        if ($clasificaciones->isEmpty()) {
            return null;
        }

        $sorteo = Sorteo::query()
            ->with('detalles.inscripcion.equipo')
            ->where('ronda_id', $ronda->id)
            ->where('estado', '!=', 'anulado')
            ->first();

        if (! $sorteo || $sorteo->tipo_sorteo !== 'enfrentamiento') {
            return $clasificaciones->first();
        }

        $grupo = $sorteo->detalles
            ->filter(fn ($detalle) => $detalle->estado !== 'directo')
            ->groupBy(fn ($detalle) => $detalle->grupo ?? $detalle->orden)
            ->first();

        if (! $grupo || $grupo->count() < 2) {
            return $clasificaciones->first();
        }

        $resuelto = $this->resolverGanadorGrupoEnfrentamiento($sorteo, $grupo, $config);

        if (! $resuelto || ! ($resuelto['ganador'] ?? null)) {
            return $clasificaciones->first();
        }

        $ganadorInscripcionId = (int) ($resuelto['ganador']->id ?? 0);

        if ($ganadorInscripcionId <= 0) {
            return $clasificaciones->first();
        }

        return $clasificaciones->first(
            fn (Clasificacion $clasificacion) => $this->clasificacionInscripcionId($clasificacion) === $ganadorInscripcionId
        ) ?? $clasificaciones->first();
    }

    private function valoresResultadoEnfrentamiento(Resultado $resultado, ConfigCalificacion $config): ?array
    {
        $payload = is_array($resultado->payload_json) ? $resultado->payload_json : [];
        $plantilla = $this->plantillaRegistroEnfrentamiento($config);

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
            [$valorA, $valorB] = $this->totalesTablaEnfrentamientoEvaluacion($payload, $config);

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
            'label' => $this->normalizarEtiquetaResultado($valorA) . ' - ' . $this->normalizarEtiquetaResultado($valorB),
            'reverse_label' => $this->normalizarEtiquetaResultado($valorB) . ' - ' . $this->normalizarEtiquetaResultado($valorA),
        ];
    }

    private function plantillaRegistroEnfrentamiento(ConfigCalificacion $config): ?string
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

    private function totalesTablaEnfrentamientoEvaluacion(array $payload, ConfigCalificacion $config): array
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

    private function normalizarEtiquetaResultado(float|int|string $valor): string
    {
        if (is_numeric($valor) && (float) $valor === (float) (int) $valor) {
            return (string) (int) $valor;
        }

        return (string) $valor;
    }

    private function clasificacionInscripcionId(Clasificacion $clasificacion): int
    {
        $inscripcionId = (int) ($clasificacion->inscripcion_id ?? 0);

        if ($inscripcionId > 0) {
            return $inscripcionId;
        }

        $evaluacion = $clasificacion->detalle_json['evaluaciones'][0] ?? [];

        return (int) ($evaluacion['inscripcion_id'] ?? 0);
    }

    private function actualizarMetadataRondaEnfrentamiento(Ronda $ronda, int $participantes): void
    {
        if ((string) $ronda->tipo === 'tercer_lugar') {
            $ronda->update([
                'nombre' => 'Tercer lugar',
                'criterio_clasificacion' => 'ganador_enfrentamiento',
                'es_final' => false,
            ]);

            return;
        }

        // Preserve the admin-configured base round. Automatic labels such as
        // "Semifinal" or "Final" should only be applied to rounds created by
        // the system from a previous round.
        if (! $ronda->ronda_origen_id) {
            if ($participantes <= 2) {
                $ronda->update([
                    'tipo' => 'final',
                    'nombre' => $this->nombreRondaAutomaticaEnfrentamiento('final', $participantes, (int) ($ronda->orden ?? 1)),
                    'criterio_clasificacion' => 'ganador_enfrentamiento',
                    'es_final' => true,
                ]);

                return;
            }

            $ronda->update([
                'criterio_clasificacion' => 'ganador_enfrentamiento',
                'es_final' => (bool) ($ronda->es_final ?? false),
            ]);

            return;
        }

        $tipo = match (true) {
            $participantes <= 2 => 'final',
            $participantes === 4 => 'semifinal',
            default => (string) ($ronda->tipo ?: 'libre'),
        };

        $ronda->update([
            'tipo' => $tipo,
            'nombre' => $this->nombreRondaAutomaticaEnfrentamiento($tipo, $participantes, (int) ($ronda->orden ?? 1)),
            'criterio_clasificacion' => 'ganador_enfrentamiento',
            'es_final' => $tipo === 'final',
        ]);
    }

    private function nombreRondaAutomaticaEnfrentamiento(string $tipo, int $participantes, int $orden): string
    {
        return match ($tipo) {
            'final' => 'Final',
            'tercer_lugar' => 'Tercer lugar',
            'semifinal' => 'Semifinal',
            default => match ($participantes) {
                16 => 'Octavos de final',
                8 => 'Cuartos de final',
                4 => 'Semifinal',
                2 => 'Final',
                default => 'Ronda ' . $orden,
            },
        };
    }

    private function siguienteOrdenRondaCategoria(int $categoriaId): int
    {
        return ((int) Ronda::query()
            ->where('categoria_id', $categoriaId)
            ->max('orden')) + 1;
    }

    private function rondaTieneResultadosRegistrados(int $rondaId): bool
    {
        return Resultado::query()
            ->where('ronda_id', $rondaId)
            ->whereIn('estado', ['registrado', 'publicado'])
            ->exists();
    }

    private function marcarSorteoCompletado(Resultado $resultado): void
    {
        $inscripcionId = (int) ($resultado->inscripcion_id ?? 0);
        [$sorteo, $detalle] = $this->obtenerSorteoYDetalle((int) $resultado->ronda_id, $inscripcionId);

        if (! $detalle || $detalle->estado === 'directo') {
            return;
        }

        if ($sorteo?->tipo_sorteo !== 'enfrentamiento') {
            $cantidadIntentos = max(1, (int) (Ronda::query()
                ->whereKey($resultado->ronda_id)
                ->value('cantidad_intentos') ?? 1));

            $intentosRegistrados = collect(range(1, $cantidadIntentos))
                ->filter(fn (int $intentoNumero) => $this->evaluacionInscripcionIntentoCompleta(
                    (int) $resultado->categoria_id,
                    (int) $resultado->ronda_id,
                    $inscripcionId,
                    $intentoNumero,
                    ConfigCalificacion::query()->where('categoria_id', $resultado->categoria_id)->first()
                ))
                ->count();

            if ($intentosRegistrados < $cantidadIntentos) {
                return;
            }
        }

        $this->marcarGrupoSorteoCompletado($sorteo, $detalle);
    }

    private function marcarGrupoSorteoCompletado(?Sorteo $sorteo, SorteoDetalle $detalle): void
    {
        if (! $sorteo) {
            return;
        }

        $query = SorteoDetalle::query()
            ->where('sorteo_id', $sorteo->id)
            ->where('estado', '!=', 'directo');

        if ($sorteo->tipo_sorteo === 'enfrentamiento' && $detalle->grupo !== null) {
            $query->where('grupo', $detalle->grupo);
        } else {
            $query->where('id', $detalle->id);
        }

        $query->update(['estado' => 'completado']);
    }

    private function resultadoEsEnfrentamiento(Resultado $resultado): bool
    {
        [$sorteo, $detalle] = $this->obtenerSorteoYDetalle((int) $resultado->ronda_id, (int) ($resultado->inscripcion_id ?? 0));

        return $sorteo?->tipo_sorteo === 'enfrentamiento' && $detalle?->grupo !== null;
    }

    private function obtenerSorteoYDetalle(int $rondaId, int $inscripcionId): array
    {
        $sorteo = Sorteo::query()
            ->with('detalles.inscripcion')
            ->where('ronda_id', $rondaId)
            ->where('estado', '!=', 'anulado')
            ->first();

        if (! $sorteo) {
            return [null, null];
        }

        $detalle = $sorteo->detalles
            ->first(fn (SorteoDetalle $item) => (int) ($item->inscripcion_id ?? 0) === $inscripcionId);

        return [$sorteo, $detalle];
    }

    private function payloadsEquivalentes(array $actual, array $guardado): bool
    {
        $normalizar = function (array $payload): array {
            ksort($payload);

            return collect($payload)
                ->map(function ($value) {
                    if ($value === null || $value === '') {
                        return null;
                    }

                    return is_numeric($value) ? round((float) $value, 3) : (string) $value;
                })
                ->all();
        };

        return $normalizar($actual) === $normalizar($guardado);
    }

    private function rondaCompleta(int $rondaId): bool
    {
        $sorteo = Sorteo::query()
            ->with('detalles.inscripcion')
            ->where('ronda_id', $rondaId)
            ->where('estado', '!=', 'anulado')
            ->first();

        if (! $sorteo) {
            return false;
        }

        $detalles = $sorteo->detalles
            ->filter(fn (SorteoDetalle $detalle) => $this->inscripcionAprobada($detalle->inscripcion))
            ->values();

        $detallesEvaluables = $detalles
            ->filter(fn (SorteoDetalle $detalle) => $detalle->estado !== 'directo')
            ->values();

        if ($detallesEvaluables->isEmpty()) {
            return false;
        }

        if ($sorteo->tipo_sorteo === 'enfrentamiento') {
            return $detallesEvaluables
                ->groupBy(fn (SorteoDetalle $detalle) => $detalle->grupo ?? $detalle->orden)
                ->every(function ($grupo) {
                    return $grupo->every(fn (SorteoDetalle $detalle) => (string) $detalle->estado === 'completado');
                });
        }

        $inscripcionesEsperadas = $detallesEvaluables
            ->map(fn (SorteoDetalle $detalle) => (int) $detalle->inscripcion->id)
            ->unique()
            ->values();

        $cantidadIntentos = max(1, (int) (Ronda::query()
            ->whereKey($rondaId)
            ->value('cantidad_intentos') ?? 1));

        $config = ConfigCalificacion::query()
            ->where('categoria_id', (int) ($sorteo->categoria_id ?? Ronda::query()->whereKey($rondaId)->value('categoria_id')))
            ->first();

        return $inscripcionesEsperadas->every(
            fn (int $inscripcionId) => collect(range(1, $cantidadIntentos))->every(
                fn (int $intentoNumero) => $this->evaluacionInscripcionIntentoCompleta(
                    (int) ($config?->categoria_id ?? 0),
                    $rondaId,
                    $inscripcionId,
                    $intentoNumero,
                    $config
                )
            )
        );
    }

    private function resolverContextoEvaluacion(User $juez, int $rondaId, int $inscripcionId, int $intentoNumero = 1): array
    {
        $ronda = Ronda::query()->with('categoria.competencia')->find($rondaId);

        if (! $ronda || ! $ronda->categoria) {
            throw ValidationException::withMessages([
                'ronda_id' => 'La ronda seleccionada no existe o no tiene categoría asociada.',
            ]);
        }

        $categoria = $ronda->categoria;
        $intentoNumero = max(1, min((int) ($ronda->cantidad_intentos ?? 1), $intentoNumero));
        $config = ConfigCalificacion::query()
            ->with('mecanismo')
            ->where('categoria_id', $categoria->id)
            ->first();

        if (! $config || ! $config->mecanismo) {
            throw ValidationException::withMessages([
                'ronda_id' => 'La categoría de la ronda no tiene configuración de calificación activa.',
            ]);
        }

        $asignacion = AsignacionJuezCategoria::query()
            ->where('categoria_id', $categoria->id)
            ->where('juez_user_id', $juez->id)
            ->first();

        if (! $asignacion) {
            throw ValidationException::withMessages([
                'ronda_id' => 'No tienes asignación activa para evaluar esta categoría.',
            ]);
        }

        $inscripcion = Inscripcion::query()
            ->with('equipo')
            ->whereKey($inscripcionId)
            ->where('competencia_id', $categoria->competencia_id)
            ->where('categoria_id', $categoria->id)
            ->aprobadas()
            ->first();

        if (! $inscripcion) {
            throw ValidationException::withMessages([
                'inscripcion_id' => 'El participante no tiene una inscripción aprobada en la categoría seleccionada.',
            ]);
        }

        $rondaTieneParticipantes = RondaParticipante::query()
            ->where('ronda_id', $ronda->id)
            ->exists();

        if ($rondaTieneParticipantes) {
            $participanteValido = $this->participanteHabilitadoParaRonda(
                (int) $ronda->id,
                (int) $inscripcion->id
            );

            if (! $participanteValido) {
                throw ValidationException::withMessages([
                    'inscripcion_id' => 'El participante no esta habilitado para esta ronda.',
                ]);
            }
        }

        if (! $rondaTieneParticipantes && $ronda->ronda_origen_id) {
            throw ValidationException::withMessages([
                'inscripcion_id' => 'Esta ronda todavia no tiene participantes clasificados asignados.',
            ]);
        }

        $resultado = $this->buscarResultadoPorInscripcionSegunEsquema(
            $juez,
            $config,
            (int) $ronda->id,
            (int) $inscripcion->id,
            (int) $intentoNumero
        );

        $inscripcionEnSorteo = Sorteo::query()
            ->where('ronda_id', $ronda->id)
            ->where('estado', '!=', 'anulado')
            ->whereHas('detalles', fn ($query) => $query
                ->where('inscripcion_id', $inscripcion->id)
                ->where('estado', '!=', 'directo'))
            ->exists();

        if (! $inscripcionEnSorteo) {
            throw ValidationException::withMessages([
                'inscripcion_id' => 'Genera el sorteo de esta ronda antes de registrar resultados o selecciona un participante de ronda previa.',
            ]);
        }

        return compact('ronda', 'categoria', 'config', 'asignacion', 'inscripcion', 'resultado') + [
            'intento_numero' => $intentoNumero,
        ];
    }

    private function getEquiposPorEvaluar(User $juez, int $categoriaId, int $rondaId): array
    {
        $ronda = Ronda::query()->find($rondaId);
        $config = ConfigCalificacion::query()
            ->with('mecanismo')
            ->where('categoria_id', $categoriaId)
            ->first();
        $cantidadIntentos = max(1, (int) ($ronda?->cantidad_intentos ?? 1));
        $intentosConsecutivos = (bool) ($ronda?->intentos_consecutivos ?? false);

        $participantes = $this->getParticipantesSorteo($categoriaId, $rondaId);
        $participantesCollection = collect($participantes);
        $participantesActivos = $participantesCollection
            ->filter(fn (array $participante) => ($participante['estado_participacion'] ?? 'pendiente') !== 'excluido')
            ->pluck('inscripcion_id')
            ->map(fn ($id) => (int) $id)
            ->all();
        $participantesExcluidos = $participantesCollection
            ->filter(fn (array $participante) => ($participante['estado_participacion'] ?? 'pendiente') === 'excluido')
            ->pluck('inscripcion_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        if ($ronda?->ronda_origen_id && empty($participantesActivos)) {
            return [];
        }

        $equipos = Inscripcion::query()
            ->with('equipo')
            ->where('categoria_id', $categoriaId)
            ->aprobadas()
            ->when(! empty($participantesActivos), fn ($query) => $query->whereIn('id', $participantesActivos))
            ->when(empty($participantesActivos) && ! empty($participantesExcluidos), function ($query) use ($participantesExcluidos) {
                $query->whereNotIn('id', $participantesExcluidos);
            })
            ->orderBy('id')
            ->get()
            ->map(function (Inscripcion $inscripcion) {
                return [
                    'inscripcion_id' => (int) $inscripcion->id,
                    'equipo_id' => (int) $inscripcion->equipo_id,
                    'equipo_nombre' => (string) ($inscripcion->equipo?->nombre ?? ''),
                    'institucion' => (string) ($inscripcion->equipo?->institucion ?? ''),
                    'nombre_prototipo' => $inscripcion->nombre_prototipo,
                ];
            })
            ->values();

        $sorteo = Sorteo::query()
            ->with('detalles')
            ->where('ronda_id', $rondaId)
            ->where('estado', '!=', 'anulado')
            ->first();

        if (! $sorteo) {
            return $this->expandirEquiposPorIntentos($equipos, $juez, $rondaId, $cantidadIntentos, $intentosConsecutivos, $config);
        }

        $detallesPorInscripcion = $sorteo->detalles->keyBy('inscripcion_id');

        $equiposOrdenados = $equipos
            ->filter(function (array $equipo) use ($detallesPorInscripcion) {
                $detalle = $detallesPorInscripcion->get($equipo['inscripcion_id']);

                return $detalle && $detalle->estado !== 'directo';
            })
            ->map(function (array $equipo) use ($detallesPorInscripcion) {
                $detalle = $detallesPorInscripcion->get($equipo['inscripcion_id']);

                return $equipo + [
                    'sorteo_orden' => (int) $detalle->orden,
                    'sorteo_grupo' => $detalle->grupo !== null ? (int) $detalle->grupo : null,
                    'sorteo_lado' => $detalle->lado,
                    'sorteo_estado' => (string) $detalle->estado,
                ];
            })
            ->sortBy('sorteo_orden')
            ->values()
            ->values();

        if ($sorteo->tipo_sorteo === 'enfrentamiento') {
            return $equiposOrdenados
                ->map(function (array $equipo) use ($juez, $rondaId, $config, $categoriaId) {
                    $resultado = $this->buscarResultadoPorInscripcionSegunEsquema(
                        $juez,
                        $config,
                        $rondaId,
                        (int) $equipo['inscripcion_id'],
                        1
                    );

                    return $equipo + [
                        'intento_numero' => 1,
                        'intento_label' => 'Intento 1',
                        'resultado_id' => $resultado?->id,
                        'resultado_estado' => $resultado?->estado,
                        'resultado_version' => $resultado?->version,
                        'resultado_juez_user_id' => $resultado?->juez_user_id,
                        'resultado_juez_nombre' => $this->nombreJuezResultado($resultado),
                        'resultado_registrado_por_otro_juez' => $resultado
                            ? (int) $resultado->juez_user_id !== (int) $juez->id
                            : false,
                        ...$this->resumenEvaluacionInscripcionIntento($categoriaId, $rondaId, (int) $equipo['inscripcion_id'], 1, $config),
                        'actualizado_at' => optional($resultado?->updated_at)?->toIso8601String(),
                    ];
                })
                ->values()
                ->all();
        }

        return $this->expandirEquiposPorIntentos($equiposOrdenados, $juez, $rondaId, $cantidadIntentos, $intentosConsecutivos, $config);
    }

    private function expandirEquiposPorIntentos($equipos, User $juez, int $rondaId, int $cantidadIntentos, bool $intentosConsecutivos, ?ConfigCalificacion $config = null): array
    {
        $equipos = collect($equipos)->values();
        $resultadosQuery = Resultado::query()
            ->with('juez:id,name,last_name,email')
            ->where('ronda_id', $rondaId)
            ->whereIn('estado', ['registrado', 'publicado']);

        if (! $config || ! $this->usaRegistroCualquierJuez($config)) {
            $resultadosQuery->where('juez_user_id', $juez->id);
        }

        $resultados = $resultadosQuery
            ->get()
            ->keyBy(fn (Resultado $resultado) => ((int) ($resultado->inscripcion_id ?? 0)) . ':' . ((int) ($resultado->intento_numero ?? 1)));

        $items = collect();
        $flujoOrden = 1;

        $categoriaId = (int) ($config?->categoria_id ?? 0);

        $agregar = function (array $equipo, int $intentoNumero) use ($items, $resultados, $juez, $rondaId, $config, $categoriaId, &$flujoOrden) {
            $resultado = $resultados->get(((int) $equipo['inscripcion_id']) . ':' . $intentoNumero);

            $items->push($equipo + [
                'flujo_orden' => $flujoOrden++,
                'intento_numero' => $intentoNumero,
                'intento_label' => 'Intento ' . $intentoNumero,
                'resultado_id' => $resultado?->id,
                'resultado_estado' => $resultado?->estado,
                'resultado_version' => $resultado?->version,
                'resultado_juez_user_id' => $resultado?->juez_user_id,
                'resultado_juez_nombre' => $this->nombreJuezResultado($resultado),
                'resultado_registrado_por_otro_juez' => $resultado
                    ? (int) $resultado->juez_user_id !== (int) $juez->id
                    : false,
                ...$this->resumenEvaluacionInscripcionIntento($categoriaId, $rondaId, (int) $equipo['inscripcion_id'], $intentoNumero, $config),
                'actualizado_at' => optional($resultado?->updated_at)?->toIso8601String(),
            ]);
        };

        if ($intentosConsecutivos) {
            foreach ($equipos as $equipo) {
                for ($intento = 1; $intento <= $cantidadIntentos; $intento++) {
                    $agregar($equipo, $intento);
                }
            }
        } else {
            for ($intento = 1; $intento <= $cantidadIntentos; $intento++) {
                foreach ($equipos as $equipo) {
                    $agregar($equipo, $intento);
                }
            }
        }

        return $items->values()->all();
    }

    public function normalizarCampos(ConfigCalificacion $config): array
    {
        $campos = collect($config->campos_json ?? [])
            ->map(function (array $campo) {
                return [
                    'key' => (string) ($campo['key'] ?? ''),
                    'type' => (string) ($campo['type'] ?? 'text'),
                    'label' => (string) ($campo['label'] ?? $campo['key'] ?? 'Campo'),
                    'required' => (bool) ($campo['required'] ?? false),
                    'options' => $campo['options'] ?? [],
                    'max' => $campo['max'] ?? null,
                    'valor_unitario' => $campo['valor_unitario'] ?? null,
                    'es_penalizacion' => (bool) ($campo['es_penalizacion'] ?? false),
                ];
            })
            ->filter(fn (array $campo) => $campo['key'] !== '')
            ->values()
            ->all();

        $registroMeta = $this->registroMeta($config);

        if ($this->plantillaResultado($config) === 'tiempo') {
            return $this->camposTiempo($campos);
        }

        return $campos;
    }

    private function camposTiempo(array $campos = []): array
    {
        $porClave = collect($campos)->keyBy('key');
        $penalizaciones = $porClave->get('penalizaciones', []);
        $observaciones = $porClave->get('observaciones', []);

        return [
            [
                'key' => 'tiempo',
                'type' => 'duration',
                'label' => 'Tiempo final',
                'required' => true,
                'options' => [],
                'max' => null,
                'valor_unitario' => null,
                'es_penalizacion' => false,
            ],
            [
                'key' => 'penalizaciones',
                'type' => 'number',
                'label' => (string) ($penalizaciones['label'] ?? 'Penalizaciones'),
                'required' => (bool) ($penalizaciones['required'] ?? false),
                'options' => $penalizaciones['options'] ?? [],
                'max' => $penalizaciones['max'] ?? null,
                'valor_unitario' => $penalizaciones['valor_unitario'] ?? null,
                'es_penalizacion' => (bool) ($penalizaciones['es_penalizacion'] ?? false),
            ],
            [
                'key' => 'observaciones',
                'type' => 'textarea',
                'label' => (string) ($observaciones['label'] ?? 'Observaciones'),
                'required' => (bool) ($observaciones['required'] ?? false),
                'options' => $observaciones['options'] ?? [],
                'max' => $observaciones['max'] ?? null,
                'valor_unitario' => $observaciones['valor_unitario'] ?? null,
                'es_penalizacion' => (bool) ($observaciones['es_penalizacion'] ?? false),
            ],
        ];
    }

    private function registroMeta(ConfigCalificacion $config): array
    {
        $registro = is_array($config->reglas_json ?? null)
            ? (array) ($config->reglas_json['registro'] ?? [])
            : [];

        if (isset($registro['tipo_registro'], $registro['modalidad_competencia'])) {
            return $registro + [
                'plantilla_resultado' => $registro['plantilla_resultado']
                    ?? ($registro['tipo_registro'] === 'tabla_evaluacion' ? 'tabla_individual_criterios' : 'tiempo'),
                'esquema_jueces' => 'registro_cualquier_juez',
                'promediar_jueces' => false,
                'promediar_resultado_final' => false,
            ];
        }

        $codigo = (string) ($config->mecanismo?->codigo ?? '');

        return match ($codigo) {
            'tabla_evaluacion', 'puntaje', 'puntaje_jueces', 'dron_destreza', 'mixto' => [
                'tipo_registro' => 'tabla_evaluacion',
                'modalidad_competencia' => 'participacion_individual',
                'plantilla_resultado' => 'tabla_individual_criterios',
                'esquema_jueces' => 'registro_cualquier_juez',
                'promediar_jueces' => false,
                'promediar_resultado_final' => false,
            ],
            'soccer_goles' => [
                'tipo_registro' => 'registro_resultado',
                'modalidad_competencia' => 'enfrentamiento_directo',
                'plantilla_resultado' => 'marcador',
                'esquema_jueces' => 'registro_cualquier_juez',
                'promediar_jueces' => false,
                'promediar_resultado_final' => false,
            ],
            'combate', 'combate_llaves' => [
                'tipo_registro' => 'registro_resultado',
                'modalidad_competencia' => 'enfrentamiento_directo',
                'plantilla_resultado' => 'tiempo',
                'esquema_jueces' => 'registro_cualquier_juez',
                'promediar_jueces' => false,
                'promediar_resultado_final' => false,
            ],
            default => [
                'tipo_registro' => 'registro_resultado',
                'modalidad_competencia' => 'participacion_individual',
                'plantilla_resultado' => 'tiempo',
                'esquema_jueces' => 'registro_cualquier_juez',
                'promediar_jueces' => false,
                'promediar_resultado_final' => false,
            ],
        };
    }

    private function esquemaJueces(?ConfigCalificacion $config): string
    {
        if (! $config) {
            return 'registro_cualquier_juez';
        }

        $registro = $this->registroMeta($config);
        $esquema = (string) ($registro['esquema_jueces'] ?? 'registro_cualquier_juez');

        return $esquema === 'evaluacion_multi_juez'
            || filter_var($registro['promediar_jueces'] ?? false, FILTER_VALIDATE_BOOLEAN)
            ? 'evaluacion_multi_juez'
            : 'registro_cualquier_juez';
    }

    private function usaRegistroCualquierJuez(?ConfigCalificacion $config): bool
    {
        return $this->esquemaJueces($config) === 'registro_cualquier_juez';
    }

    public function renovarBloqueoRegistro(User $juez, int $categoriaId, ?string $sessionId = null): array
    {
        $contexto = $this->contextoBloqueoRegistro($juez, $categoriaId);

        if (! $contexto) {
            return ['activo' => false, 'bloqueado' => false];
        }

        return $this->registroLockService->renovar(
            $juez,
            $categoriaId,
            (int) $contexto['asignacion']->id,
            $sessionId
        );
    }

    public function liberarBloqueoRegistro(User $juez, int $categoriaId, ?string $sessionId = null, string $motivo = 'manual'): void
    {
        $this->registroLockService->liberarCategoria($juez, $categoriaId, $sessionId, $motivo);
    }

    public function liberarBloqueosRegistroDelJuez(User $juez, string $motivo = 'logout'): void
    {
        $this->registroLockService->liberarActivosDelJuez($juez, $motivo);
    }

    private function tomarBloqueoRegistroSiAplica(User $juez, ?int $categoriaId, ?string $sessionId = null): array
    {
        if (! $categoriaId) {
            return ['activo' => false, 'bloqueado' => false];
        }

        $contexto = $this->contextoBloqueoRegistro($juez, $categoriaId);

        if (! $contexto) {
            return ['activo' => false, 'bloqueado' => false];
        }

        return $this->registroLockService->tomar(
            $juez,
            $categoriaId,
            (int) $contexto['asignacion']->id,
            $sessionId
        );
    }

    private function asegurarBloqueoRegistroSiAplica(User $juez, array $contexto, ?string $sessionId = null): void
    {
        if (! $this->usaRegistroCualquierJuez($contexto['config'] ?? null)) {
            return;
        }

        $this->registroLockService->asegurarDisponibleParaJuez(
            $juez,
            (int) $contexto['categoria']->id,
            (int) $contexto['asignacion']->id,
            $sessionId
        );
    }

    private function contextoBloqueoRegistro(User $juez, int $categoriaId): ?array
    {
        $config = ConfigCalificacion::query()
            ->where('categoria_id', $categoriaId)
            ->first();

        if (! $this->usaRegistroCualquierJuez($config)) {
            return null;
        }

        $asignacion = AsignacionJuezCategoria::query()
            ->where('categoria_id', $categoriaId)
            ->where('juez_user_id', $juez->id)
            ->first();

        if (! $asignacion) {
            throw ValidationException::withMessages([
                'categoria_id' => 'No tienes asignacion activa para registrar esta categoria.',
            ]);
        }

        return compact('config', 'asignacion');
    }

    private function promediaJueces(?ConfigCalificacion $config): bool
    {
        if (! $config || $this->esquemaJueces($config) !== 'evaluacion_multi_juez') {
            return false;
        }

        $registro = $this->registroMeta($config);

        return filter_var($registro['promediar_jueces'] ?? false, FILTER_VALIDATE_BOOLEAN);
    }

    private function juecesRequeridosCategoria(int $categoriaId): int
    {
        return max(1, AsignacionJuezCategoria::query()
            ->where('categoria_id', $categoriaId)
            ->distinct('juez_user_id')
            ->count('juez_user_id'));
    }

    private function registrosEvaluacionInscripcionIntento(int $rondaId, int $inscripcionId, int $intentoNumero): int
    {
        return Resultado::query()
            ->where('ronda_id', $rondaId)
            ->where('inscripcion_id', $inscripcionId)
            ->where('intento_numero', $intentoNumero)
            ->whereIn('estado', ['registrado', 'publicado'])
            ->distinct('juez_user_id')
            ->count('juez_user_id');
    }

    private function resumenEvaluacionInscripcionIntento(
        int $categoriaId,
        int $rondaId,
        int $inscripcionId,
        int $intentoNumero,
        ?ConfigCalificacion $config
    ): array {
        $requeridas = $this->esquemaJueces($config) === 'evaluacion_multi_juez'
            ? $this->juecesRequeridosCategoria($categoriaId)
            : 1;
        $registradas = $this->registrosEvaluacionInscripcionIntento($rondaId, $inscripcionId, $intentoNumero);
        $pendientes = max(0, $requeridas - $registradas);

        return [
            'evaluaciones_requeridas' => $requeridas,
            'evaluaciones_registradas' => min($registradas, $requeridas),
            'evaluaciones_pendientes' => $pendientes,
            'evaluacion_completa' => $pendientes === 0,
        ];
    }

    private function evaluacionInscripcionIntentoCompleta(
        int $categoriaId,
        int $rondaId,
        int $inscripcionId,
        int $intentoNumero,
        ?ConfigCalificacion $config
    ): bool {
        return (bool) $this->resumenEvaluacionInscripcionIntento($categoriaId, $rondaId, $inscripcionId, $intentoNumero, $config)['evaluacion_completa'];
    }

    private function validarTurnoActualSorteo(User $juez, array $contexto): void
    {
        /** @var \App\Models\ConfigCalificacion $config */
        $config = $contexto['config'];

        $items = collect($this->getEquiposPorEvaluar(
            $juez,
            (int) $contexto['categoria']->id,
            (int) $contexto['ronda']->id
        ));
        $inscripcionSolicitada = (int) $contexto['inscripcion']->id;
        $intentoSolicitado = (int) $contexto['intento_numero'];

        $itemSolicitado = $items->first(function (array $item) use ($inscripcionSolicitada, $intentoSolicitado) {
            return (int) ($item['inscripcion_id'] ?? 0) === $inscripcionSolicitada
                && (int) ($item['intento_numero'] ?? 1) === $intentoSolicitado;
        });

        if (! $itemSolicitado) {
            throw ValidationException::withMessages([
                'inscripcion_id' => 'El participante seleccionado no pertenece al sorteo actual.',
            ]);
        }

        if ((bool) ($itemSolicitado['evaluacion_completa'] ?? false)) {
            return;
        }

        $turnoActual = $items->first(function (array $item) {
            return ($item['sorteo_estado'] ?? null) !== 'completado'
                && ! (bool) ($item['evaluacion_completa'] ?? false);
        });

        if (! $turnoActual) {
            throw ValidationException::withMessages([
                'inscripcion_id' => 'Ya no hay participantes pendientes para esta ronda.',
            ]);
        }

        $inscripcionActual = (int) ($turnoActual['inscripcion_id'] ?? 0);
        $intentoActual = (int) ($turnoActual['intento_numero'] ?? 1);
        if ($inscripcionActual === $inscripcionSolicitada && $intentoActual === $intentoSolicitado) {
            return;
        }

        throw ValidationException::withMessages([
            'inscripcion_id' => 'Debes registrar el participante actual segun el orden del sorteo antes de avanzar.',
        ]);
    }

    private function buscarResultadoPorInscripcionSegunEsquema(
        User $juez,
        ?ConfigCalificacion $config,
        int $rondaId,
        int $inscripcionId,
        int $intentoNumero = 1,
        bool $lockForUpdate = false
    ): ?Resultado {
        $query = Resultado::query()
            ->with('juez:id,name,last_name,email')
            ->where('ronda_id', $rondaId)
            ->where('inscripcion_id', $inscripcionId)
            ->where('intento_numero', $intentoNumero);

        if ($this->esquemaJueces($config) === 'evaluacion_multi_juez') {
            $query->where('juez_user_id', $juez->id);
        } else {
            $query->whereIn('estado', ['registrado', 'publicado']);
        }

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        $resultado = $query
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->first();

        if ($resultado || ! $this->usaRegistroCualquierJuez($config)) {
            return $resultado;
        }

        $fallback = Resultado::query()
            ->with('juez:id,name,last_name,email')
            ->where('ronda_id', $rondaId)
            ->where('inscripcion_id', $inscripcionId)
            ->where('juez_user_id', $juez->id)
            ->where('intento_numero', $intentoNumero);

        if ($lockForUpdate) {
            $fallback->lockForUpdate();
        }

        return $fallback
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->first();
    }

    private function bloquearResultadoOficialPorInscripcionSiAplica(?ConfigCalificacion $config, int $rondaId, int $inscripcionId, int $intentoNumero): void
    {
        if (! $this->usaRegistroCualquierJuez($config) || DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('SELECT pg_advisory_xact_lock(hashtext(?))', [
            "resultado-oficial:{$rondaId}:{$inscripcionId}:{$intentoNumero}",
        ]);
    }

    private function validarMotivoParaEdicionDeOtroJuez(?Resultado $existente, User $juez, ?string $motivoCambio): void
    {
        if (! $existente || (int) $existente->juez_user_id === (int) $juez->id) {
            return;
        }

        if (trim((string) $motivoCambio) !== '') {
            return;
        }

        throw ValidationException::withMessages([
            'motivo_cambio' => 'Ingresa el motivo para actualizar un resultado registrado por otro juez.',
        ]);
    }

    private function validarResultadoPerteneceAJuezMultiJuez(?Resultado $existente, User $juez, ?ConfigCalificacion $config): void
    {
        if (! $existente || $this->esquemaJueces($config) !== 'evaluacion_multi_juez') {
            return;
        }

        if ((int) $existente->juez_user_id === (int) $juez->id) {
            return;
        }

        throw ValidationException::withMessages([
            'inscripcion_id' => 'Esta evaluacion pertenece a otro juez. Recarga la pagina antes de registrar tu calificacion.',
        ]);
    }

    private function nombreJuezResultado(?Resultado $resultado): ?string
    {
        if (! $resultado) {
            return null;
        }

        $nombre = trim((string) ($resultado->juez?->name ?? '') . ' ' . (string) ($resultado->juez?->last_name ?? ''));

        return $nombre !== '' ? $nombre : ($resultado->juez?->email ?: null);
    }

    private function participanteHabilitadoParaRonda(int $rondaId, int $inscripcionId): bool
    {
        $estadosPermitidos = ['pendiente', 'clasificado', 'agregado_manual'];

        $estadoActual = RondaParticipante::query()
            ->where('ronda_id', $rondaId)
            ->where('inscripcion_id', $inscripcionId)
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->value('estado');

        return in_array((string) $estadoActual, $estadosPermitidos, true);
    }

    private function sincronizarParticipantesSorteo(Ronda $ronda, array $detalles): void
    {
        $inscripcionesEvaluables = collect($detalles)
            ->pluck('inscripcion_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($inscripcionesEvaluables->isEmpty()) {
            return;
        }

        $existentes = RondaParticipante::query()
            ->where('ronda_id', $ronda->id)
            ->whereIn('inscripcion_id', $inscripcionesEvaluables)
            ->get()
            ->groupBy('inscripcion_id');

        foreach ($inscripcionesEvaluables as $inscripcionId) {
            $existente = $existentes->get($inscripcionId)?->sortByDesc('updated_at')->first();

            if ($existente?->estado === 'excluido') {
                continue;
            }

            if ($existente) {
                if ($existente->estado !== 'clasificado' && $existente->estado !== 'agregado_manual') {
                    $existente->update([
                        'equipo_id' => (int) ($existente->equipo_id ?? 0),
                        'estado' => 'pendiente',
                    ]);
                }

                continue;
            }

            $inscripcion = Inscripcion::query()
                ->where('id', $inscripcionId)
                ->value('equipo_id');

            if (! $inscripcion) {
                continue;
            }

            RondaParticipante::create([
                'ronda_id' => $ronda->id,
                'inscripcion_id' => $inscripcionId,
                'equipo_id' => (int) $inscripcion,
                'estado' => 'pendiente',
                'origen_clasificacion_id' => null,
            ]);
        }
    }

    private function inscripcionesSorteables(int $categoriaId, int $rondaId)
    {
        $participantesExcluidos = RondaParticipante::query()
            ->where('ronda_id', $rondaId)
            ->where('estado', 'excluido')
            ->pluck('inscripcion_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $sorteoVigente = Sorteo::query()
            ->with('detalles')
            ->where('ronda_id', $rondaId)
            ->where('estado', '!=', 'anulado')
            ->latest('id')
            ->first();

        if ($sorteoVigente && ! $this->rondaTieneResultadosRegistrados($rondaId)) {
            $inscripcionesDelSorteo = $sorteoVigente->detalles
                ->pluck('inscripcion_id')
                ->map(fn ($id) => (int) $id)
                ->reject(fn ($id) => in_array((int) $id, $participantesExcluidos, true))
                ->unique()
                ->values()
                ->all();

            if (! empty($inscripcionesDelSorteo)) {
                return Inscripcion::query()
                    ->with('equipo')
                    ->where('categoria_id', $categoriaId)
                    ->aprobadas()
                    ->whereIn('id', $inscripcionesDelSorteo)
                    ->orderBy('id')
                    ->get();
            }
        }

        $ronda = Ronda::query()->find($rondaId);
        $participantes = $this->getParticipantesSorteo($categoriaId, $rondaId);
        $participantesCollection = collect($participantes);
        $participantesActivos = $participantesCollection
            ->filter(fn (array $participante) => ($participante['estado_participacion'] ?? 'pendiente') !== 'excluido')
            ->pluck('inscripcion_id')
            ->map(fn ($id) => (int) $id)
            ->all();
        $participantesExcluidos = $participantesCollection
            ->filter(fn (array $participante) => ($participante['estado_participacion'] ?? 'pendiente') === 'excluido')
            ->pluck('inscripcion_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        if ($ronda?->ronda_origen_id && empty($participantesActivos)) {
            return collect();
        }

        return Inscripcion::query()
            ->with('equipo')
            ->where('categoria_id', $categoriaId)
            ->aprobadas()
            ->when(! empty($participantesActivos), fn ($query) => $query->whereIn('id', $participantesActivos))
            ->when(empty($participantesActivos) && ! empty($participantesExcluidos), function ($query) use ($participantesExcluidos) {
                $query->whereNotIn('id', $participantesExcluidos);
            })
            ->orderBy('id')
            ->get();
    }

    private function getParticipantesSorteo(int $categoriaId, int $rondaId): array
    {
        $ronda = Ronda::query()->find($rondaId);
        $allowedStates = ['pendiente', 'clasificado', 'agregado_manual'];

        $allowedIds = RondaParticipante::query()
            ->where('ronda_id', $rondaId)
            ->whereIn('estado', $allowedStates)
            ->pluck('inscripcion_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $excludedIds = RondaParticipante::query()
            ->where('ronda_id', $rondaId)
            ->where('estado', 'excluido')
            ->pluck('inscripcion_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $baseIds = ! empty($allowedIds)
            ? array_values(array_unique(array_merge($allowedIds, $excludedIds)))
            : null;

        if ($ronda?->ronda_origen_id && empty($allowedIds) && empty($excludedIds)) {
            return [];
        }

        $inscripciones = Inscripcion::query()
            ->with('equipo')
            ->where('categoria_id', $categoriaId)
            ->aprobadas()
            ->when($baseIds !== null, fn ($query) => $query->whereIn('id', $baseIds))
            ->orderBy('id')
            ->get();

        $estadosPorInscripcion = RondaParticipante::query()
            ->where('ronda_id', $rondaId)
            ->whereIn('estado', [...$allowedStates, 'excluido'])
            ->get()
            ->groupBy('inscripcion_id')
            ->map(fn ($items) => (string) ($items->sortByDesc('updated_at')->first()?->estado ?? 'pendiente'))
            ->all();

        return $inscripciones
            ->map(function (Inscripcion $inscripcion) use ($estadosPorInscripcion) {
                return [
                    'inscripcion_id' => (int) $inscripcion->id,
                    'equipo_id' => (int) $inscripcion->equipo_id,
                    'equipo_nombre' => (string) ($inscripcion->equipo?->nombre ?? ''),
                    'institucion' => (string) ($inscripcion->equipo?->institucion ?? ''),
                    'nombre_prototipo' => $inscripcion->nombre_prototipo,
                    'estado_participacion' => (string) ($estadosPorInscripcion[$inscripcion->id] ?? 'pendiente'),
                ];
            })
            ->values()
            ->all();
    }

    private function crearDetallesIndividual($inscripciones): array
    {
        return $inscripciones
            ->shuffle()
            ->values()
            ->map(fn (Inscripcion $inscripcion, int $index) => [
                'inscripcion_id' => (int) $inscripcion->id,
                'orden' => $index + 1,
                'grupo' => null,
                'lado' => null,
                'estado' => 'pendiente',
            ])
            ->all();
    }

    private function crearDetallesEnfrentamiento($inscripciones): array
    {
        $pendientes = $inscripciones->shuffle()->values();
        $total = $pendientes->count();
        $objetivoLlave = $this->potenciaDeDosInferior($total);
        $combatesPrevios = max(0, $total - $objetivoLlave);
        $participantesPrevios = $combatesPrevios * 2;
        $parejas = [];
        $grupo = 1;
        $directos = collect();

        if ($combatesPrevios > 0) {
            $directos = $pendientes->splice($participantesPrevios)->values();
        }

        while ($pendientes->count() > 1 && ($combatesPrevios === 0 || count($parejas) < $combatesPrevios)) {
            /** @var Inscripcion $primero */
            $primero = $pendientes->shift();
            $institucion = $this->institucionNormalizada($primero);
            $rivalIndex = $pendientes->search(
                fn (Inscripcion $item) => $this->institucionNormalizada($item) !== $institucion
            );

            if ($rivalIndex === false) {
                $rivalIndex = 0;
            }

            /** @var Inscripcion $segundo */
            $segundo = $pendientes->splice((int) $rivalIndex, 1)->first();

            $parejas[] = [
                'grupo' => $grupo++,
                'items' => [
                    ['inscripcion' => $primero, 'lado' => 'A', 'estado' => 'pendiente'],
                    ['inscripcion' => $segundo, 'lado' => 'B', 'estado' => 'pendiente'],
                ],
            ];
        }

        if ($pendientes->isNotEmpty()) {
            $directos = $directos->concat($pendientes)->values();
        }

        $orden = 1;
        $detalles = [];

        foreach ($parejas as $pareja) {
            foreach ($pareja['items'] as $item) {
                $detalles[] = [
                    'inscripcion_id' => (int) $item['inscripcion']->id,
                    'orden' => $orden++,
                    'grupo' => $pareja['grupo'],
                    'lado' => $item['lado'],
                    'estado' => $item['estado'],
                ];
            }
        }

        foreach ($directos as $inscripcion) {
            $detalles[] = [
                'inscripcion_id' => (int) $inscripcion->id,
                'orden' => $orden++,
                'grupo' => null,
                'lado' => null,
                'estado' => 'directo',
            ];
        }

        return $detalles;
    }

    private function potenciaDeDosInferior(int $total): int
    {
        if ($total < 2) {
            return 1;
        }

        $potencia = 1;

        while (($potencia * 2) <= $total) {
            $potencia *= 2;
        }

        return $potencia;
    }

    private function institucionNormalizada(Inscripcion $inscripcion): string
    {
        return mb_strtolower(trim((string) ($inscripcion->equipo?->institucion ?? '')));
    }

    private function getSorteoVigente(int $rondaId): ?array
    {
        $sorteo = Sorteo::query()
            ->with('detalles.inscripcion.equipo')
            ->where('ronda_id', $rondaId)
            ->where('estado', '!=', 'anulado')
            ->first();

        return $sorteo ? $this->serializarSorteo($sorteo) : null;
    }

    private function serializarSorteo(Sorteo $sorteo): array
    {
        $detalles = $sorteo->detalles
            ->filter(fn ($detalle) => $this->inscripcionAprobada($detalle->inscripcion))
            ->sortBy('orden')
            ->values()
            ->map(function ($detalle) {
                $inscripcion = $detalle->inscripcion;

                return [
                    'id' => (int) $detalle->id,
                    'inscripcion_id' => (int) $detalle->inscripcion_id,
                    'equipo_id' => (int) ($inscripcion?->equipo_id ?? 0),
                    'equipo_nombre' => (string) ($inscripcion?->equipo?->nombre ?? ''),
                    'institucion' => (string) ($inscripcion?->equipo?->institucion ?? ''),
                    'nombre_prototipo' => $inscripcion?->nombre_prototipo,
                    'orden' => (int) $detalle->orden,
                    'grupo' => $detalle->grupo !== null ? (int) $detalle->grupo : null,
                    'lado' => $detalle->lado,
                    'estado' => (string) $detalle->estado,
                ];
            });

        return [
            'id' => (int) $sorteo->id,
            'ronda_id' => (int) $sorteo->ronda_id,
            'tipo_sorteo' => (string) $sorteo->tipo_sorteo,
            'estado' => (string) $sorteo->estado,
            'reglas_json' => $sorteo->reglas_json ?? [],
            'detalles' => $detalles->all(),
        ];
    }

    private function inscripcionAprobada(?Inscripcion $inscripcion): bool
    {
        return $inscripcion !== null
            && $inscripcion->estado === 'confirmado'
            && $inscripcion->estado_comprobante === 'aprobado';
    }

    private function validarPayloadSegunConfig(array $payload, array $campos, ?ConfigCalificacion $config = null): array
    {
        $rules = [
            'ronda_id' => ['required', 'integer', 'min:1'],
            'equipo_id' => ['nullable', 'integer', 'min:1'],
            'inscripcion_id' => ['nullable', 'integer', 'min:1'],
            'intento_numero' => ['nullable', 'integer', 'min:1', 'max:10'],
            'version' => ['nullable', 'integer', 'min:0'],
            'motivo_cambio' => ['nullable', 'string', 'max:255'],
            'observaciones' => ['nullable', 'string'],
            'payload' => ['nullable', 'array'],
            'payload.no_participa' => ['nullable', 'boolean'],
            'payload.sin_tiempo_valido' => ['nullable', 'boolean'],
        ];

        $plantilla = $config ? $this->plantillaResultado($config) : null;
        $esTablaEnfrentamiento = $plantilla === 'tabla_enfrentamiento_criterios';
        $esTablaPuntajeMaximo = $plantilla === 'tabla_individual_puntaje_maximo';
        $sinTiempoValido = $this->payloadSinTiempoValido((array) data_get($payload, 'payload', []));

        foreach ($campos as $campo) {
            $fieldRules = [($campo['required'] && ! $esTablaEnfrentamiento && ! $sinTiempoValido) ? 'required' : 'nullable'];

            switch ($campo['type']) {
                case 'number':
                    if ($esTablaPuntajeMaximo) {
                        $fieldRules[] = 'integer';
                        $fieldRules[] = 'min:0';
                    } else {
                        $fieldRules[] = 'numeric';
                    }
                    if (isset($campo['max']) && is_numeric($campo['max'])) {
                        $fieldRules[] = 'max:' . (float) $campo['max'];
                    } elseif ($esTablaPuntajeMaximo && isset($campo['valor_unitario']) && is_numeric($campo['valor_unitario'])) {
                        $fieldRules[] = 'max:' . (float) $campo['valor_unitario'];
                    }
                    break;
                case 'select':
                    $options = collect($campo['options'] ?? [])
                        ->pluck('value')
                        ->filter(fn ($value) => $value !== null && $value !== '')
                        ->map(fn ($value) => (string) $value)
                        ->all();

                    $fieldRules[] = 'string';

                    if (! empty($options)) {
                        $fieldRules[] = 'in:' . implode(',', $options);
                    }
                    break;
                case 'checkbox':
                case 'boolean':
                    $fieldRules[] = 'boolean';
                    break;
                case 'duration':
                    $fieldRules[] = function ($attribute, $value, $fail) use ($sinTiempoValido) {
                        if ($value === null || $value === '') {
                            return;
                        }

                        $seconds = $this->parseDurationToSeconds($value);

                        if ($seconds !== null && ! $sinTiempoValido && $seconds <= 0) {
                            $fail('El tiempo debe ser mayor a cero. Si no hay tiempo válido, usa el botón Sin tiempo válido.');
                            return;
                        }

                        if ($this->parseDurationToSeconds($value) === null) {
                            $fail('El tiempo ingresado no tiene un formato válido.');
                        }
                    };
                    break;
                case 'textarea':
                case 'text':
                default:
                    $fieldRules[] = 'string';
                    break;
            }

            $rules["payload.{$campo['key']}"] = $fieldRules;

            if ($esTablaEnfrentamiento && ($campo['type'] ?? null) === 'number') {
                $sideRules = ['nullable', 'numeric'];

                foreach (['a', 'b'] as $lado) {
                    $rules["payload.{$campo['key']}_{$lado}"] = $sideRules;
                }
            }
        }

        return Validator::make(
            $payload,
            $rules,
            [
                'ronda_id.required' => 'La ronda es obligatoria.',
                'inscripcion_id.required' => 'El participante es obligatorio.',
                'payload.array' => 'Los datos de evaluación deben enviarse como objeto.',
            ]
        )->after(function ($validator) use ($payload) {
            if (! filled($payload['equipo_id'] ?? null) && ! filled($payload['inscripcion_id'] ?? null)) {
                $validator->errors()->add('inscripcion_id', 'El participante es obligatorio.');
            }
        })->validate();
    }

    private function normalizarValoresEvaluacion(
        ConfigCalificacion $config,
        array $campos,
        array $payload,
        ?string $observaciones,
        ?Resultado $existente,
        ?string $ladoEnfrentamiento = null
    ): array {
        $persistedPayload = is_array($existente?->payload_json) ? $existente->payload_json : [];
        $columnas = [
            'puntaje' => null,
            'tiempo' => null,
            'penalizaciones' => 0,
            'valor_principal' => null,
            'valor_secundario' => null,
            'observaciones' => $observaciones,
        ];

        foreach ($campos as $campo) {
            $key = $campo['key'];
            $value = $payload[$key] ?? null;

            if ($value === null || $value === '') {
                $persistedPayload[$key] = null;
                continue;
            }

            if ($campo['type'] === 'duration') {
                $value = $this->parseDurationToSeconds($value);
            } elseif ($campo['type'] === 'number') {
                $value = round((float) $value, 3);
            } elseif (in_array($campo['type'], ['checkbox', 'boolean'], true)) {
                $value = filter_var($value, FILTER_VALIDATE_BOOLEAN);
            } else {
                $value = trim((string) $value);
            }

            if (in_array($key, ['puntaje', 'tiempo', 'penalizaciones'], true)) {
                $columnas[$key] = $value;
            }

            if (in_array($key, ['valor_principal', 'valor_secundario'], true) && is_numeric($value)) {
                $columnas[$key] = round((float) $value, 3);
            }

            if ($key === 'observaciones') {
                $columnas['observaciones'] = (string) $value;
            }

            $persistedPayload[$key] = $value;
        }

        $registroMeta = $this->registroMeta($config);
        $plantilla = $this->plantillaResultado($config);
        $esTablaEnfrentamiento = $plantilla === 'tabla_enfrentamiento_criterios';
        $esTablaIndividual = $plantilla === 'tabla_individual_criterios';
        $esTablaPuntajeMaximo = $plantilla === 'tabla_individual_puntaje_maximo';
        $sinTiempoValido = $this->payloadSinTiempoValido($payload);

        if ($plantilla === 'marcador') {
            $persistedPayload = Arr::only($persistedPayload, ['marcador_equipo_a', 'marcador_equipo_b']);
        }

        if ($plantilla === 'tiempo') {
            $persistedPayload = Arr::only($persistedPayload, [
                'tiempo',
                'penalizaciones',
                'observaciones',
                'no_participa',
                'sin_tiempo_valido',
                'motivo_sin_tiempo_valido',
            ]);
        }

        if ($esTablaEnfrentamiento) {
            foreach ($campos as $campo) {
                if (($campo['type'] ?? null) !== 'number') {
                    continue;
                }

                foreach (['a', 'b'] as $lado) {
                    $sideKey = "{$campo['key']}_{$lado}";
                    $sideValue = $payload[$sideKey] ?? null;

                    $persistedPayload[$sideKey] = ($sideValue === null || $sideValue === '' || ! is_numeric($sideValue))
                        ? 0.0
                        : round((float) $sideValue, 3);
                }
            }
        }

        if ($esTablaIndividual || $esTablaPuntajeMaximo) {
            foreach ($campos as $campo) {
                $key = (string) ($campo['key'] ?? '');

                if ($key === '' || $key === 'penalizaciones' || ($campo['type'] ?? null) !== 'number') {
                    continue;
                }

                $value = $persistedPayload[$key] ?? null;

                $persistedPayload[$key] = ($value === null || $value === '' || ! is_numeric($value))
                    ? ($esTablaPuntajeMaximo ? 0 : 0.0)
                    : ($esTablaPuntajeMaximo ? (int) $value : round((float) $value, 3));
            }
        }

        if ($plantilla === 'marcador') {
            $marcadorA = $this->numberFromPayload($persistedPayload, 'marcador_equipo_a');
            $marcadorB = $this->numberFromPayload($persistedPayload, 'marcador_equipo_b');

            $columnas['puntaje'] = $marcadorA;
            $columnas['penalizaciones'] = 0;
            $columnas['valor_principal'] = $marcadorA !== null && $marcadorB !== null
                ? round($marcadorA <=> $marcadorB, 3)
                : null;
            $columnas['valor_secundario'] = $marcadorB;

            return $columnas + ['payload_json' => $persistedPayload];
        }

        if ($plantilla === 'tiempo') {
            if ($sinTiempoValido) {
                $motivo = trim((string) ($payload['motivo_sin_tiempo_valido'] ?? $columnas['observaciones'] ?? 'Sin tiempo válido'));

                $persistedPayload = [
                    'tiempo' => null,
                    'penalizaciones' => 0,
                    'observaciones' => $columnas['observaciones'] ?: $motivo,
                    'sin_tiempo_valido' => true,
                    'motivo_sin_tiempo_valido' => $motivo,
                ];

                $columnas['tiempo'] = null;
                $columnas['puntaje'] = 0;
                $columnas['penalizaciones'] = 0;
                $columnas['valor_principal'] = null;
                $columnas['valor_secundario'] = null;
                $columnas['observaciones'] = $persistedPayload['observaciones'];

                return $columnas + ['payload_json' => $persistedPayload];
            }

            unset($persistedPayload['no_participa']);
            unset($persistedPayload['sin_tiempo_valido']);
            unset($persistedPayload['motivo_sin_tiempo_valido']);

            $tiempo = $this->numberFromPayload($persistedPayload, 'tiempo');
            $penalizaciones = $this->numberFromPayload($persistedPayload, 'penalizaciones', 0.0);

            $columnas['tiempo'] = $tiempo;
            $columnas['puntaje'] = $tiempo;
            $columnas['penalizaciones'] = $penalizaciones;
            $columnas['valor_principal'] = $tiempo !== null
                ? round($tiempo + $penalizaciones, 3)
                : null;
            $columnas['valor_secundario'] = $penalizaciones;

            return $columnas + ['payload_json' => $persistedPayload];
        }

        if ($plantilla === 'tabla_individual_criterios') {
            $subtotal = $this->sumWeightedConfiguredFields($persistedPayload, $campos, false, null, true);
            $penalizaciones = $this->sumWeightedConfiguredFields($persistedPayload, $campos, true, null, true) ?? 0.0;
            $tiempoDesempate = $this->numberFromPayload($persistedPayload, 'tiempo');

            $columnas['puntaje'] = $subtotal;
            $columnas['tiempo'] = $tiempoDesempate;
            $columnas['penalizaciones'] = $penalizaciones;
            $columnas['valor_principal'] = $subtotal !== null
                ? round($subtotal - $penalizaciones, 3)
                : null;
            $columnas['valor_secundario'] = $tiempoDesempate ?? $penalizaciones;

            return $columnas + ['payload_json' => $persistedPayload];
        }

        if ($plantilla === 'tabla_individual_puntaje_maximo') {
            $subtotal = $this->sumDirectConfiguredFields($persistedPayload, $campos);

            $columnas['puntaje'] = $subtotal;
            $columnas['penalizaciones'] = 0;
            $columnas['valor_principal'] = $subtotal;
            $columnas['valor_secundario'] = $this->sumMaxConfiguredFields($campos);

            return $columnas + ['payload_json' => $persistedPayload];
        }

        if ($plantilla === 'tabla_enfrentamiento_criterios') {
            $lado = $ladoEnfrentamiento === 'B' ? 'b' : 'a';
            $otroLado = $lado === 'b' ? 'a' : 'b';
            $subtotal = $this->sumWeightedConfiguredFields($persistedPayload, $campos, false, $lado, true);
            $penalizaciones = $this->sumWeightedConfiguredFields($persistedPayload, $campos, true, $lado, true) ?? 0.0;
            $subtotalRival = $this->sumWeightedConfiguredFields($persistedPayload, $campos, false, $otroLado, true);
            $penalizacionesRival = $this->sumWeightedConfiguredFields($persistedPayload, $campos, true, $otroLado, true) ?? 0.0;
            $total = $subtotal !== null ? round($subtotal - $penalizaciones, 3) : null;
            $totalRival = $subtotalRival !== null ? round($subtotalRival - $penalizacionesRival, 3) : null;

            $columnas['puntaje'] = $subtotal;
            $columnas['penalizaciones'] = $penalizaciones;
            $columnas['valor_principal'] = $total;
            $columnas['valor_secundario'] = $totalRival;

            return $columnas + ['payload_json' => $persistedPayload];
        }

        switch ($config->mecanismo?->codigo) {
            case 'registro_resultado':
                if (($registroMeta['plantilla_resultado'] ?? '') === 'marcador') {
                    $marcadorA = $this->numberFromPayload($persistedPayload, 'marcador_equipo_a');
                    $marcadorB = $this->numberFromPayload($persistedPayload, 'marcador_equipo_b');

                    $columnas['puntaje'] = $marcadorA;
                    $columnas['penalizaciones'] = 0;
                    $columnas['valor_principal'] = $marcadorA !== null && $marcadorB !== null
                        ? round($marcadorA <=> $marcadorB, 3)
                        : null;
                    $columnas['valor_secundario'] = $marcadorB;
                    break;
                }

                if (($registroMeta['plantilla_resultado'] ?? '') === 'tiempo') {
                    $tiempo = $this->numberFromPayload($persistedPayload, 'tiempo');
                    $penalizaciones = $this->numberFromPayload($persistedPayload, 'penalizaciones', 0.0);

                    $columnas['tiempo'] = $tiempo;
                    $columnas['puntaje'] = $tiempo;
                    $columnas['penalizaciones'] = $penalizaciones;
                    $columnas['valor_principal'] = $tiempo !== null
                        ? round($tiempo + $penalizaciones, 3)
                        : null;
                    $columnas['valor_secundario'] = $penalizaciones;
                    break;
                }

                if (($registroMeta['modalidad_competencia'] ?? '') === 'enfrentamiento_directo') {
                    $resultado = (string) ($persistedPayload['resultado'] ?? '');
                    $puntos = $this->numberFromPayload($persistedPayload, 'puntos', 0.0);
                    $penalizaciones = $this->numberFromPayload($persistedPayload, 'penalizaciones', 0.0);
                    $base = match ($resultado) {
                        'victoria' => 3.0,
                        'empate' => 1.0,
                        'derrota' => 0.0,
                        default => null,
                    };

                    $columnas['puntaje'] = $base !== null ? round($base + $puntos, 3) : $this->numberFromPayload($persistedPayload, 'puntaje');
                    $columnas['penalizaciones'] = $penalizaciones;
                    $columnas['valor_principal'] = $base !== null
                        ? round(($base * 1000) + $puntos - $penalizaciones, 3)
                        : $this->numberFromPayload($persistedPayload, 'valor_principal');
                    $columnas['valor_secundario'] = $puntos;
                    break;
                }

                $penalizaciones = $this->numberFromPayload($persistedPayload, 'penalizaciones', 0.0);
                $columnas['penalizaciones'] = $penalizaciones;

                if ($columnas['tiempo'] !== null) {
                    $columnas['valor_principal'] = round((float) $columnas['tiempo'] + $penalizaciones, 3);
                    $columnas['valor_secundario'] = $penalizaciones;
                    break;
                }

                $valorPrincipal = $this->numberFromPayload($persistedPayload, 'valor_principal')
                    ?? $this->numberFromPayload($persistedPayload, 'puntaje')
                    ?? $this->sumNumericConfiguredFields($persistedPayload, $campos, ['penalizaciones']);
                $columnas['puntaje'] = $valorPrincipal;
                $columnas['valor_principal'] = $valorPrincipal !== null
                    ? round($valorPrincipal - $penalizaciones, 3)
                    : null;
                $columnas['valor_secundario'] = $penalizaciones;
                break;
            case 'tabla_evaluacion':
                if ($esTablaIndividual) {
                    $subtotal = $this->sumWeightedConfiguredFields($persistedPayload, $campos, false, null, true);
                    $penalizaciones = $this->sumWeightedConfiguredFields($persistedPayload, $campos, true, null, true) ?? 0.0;
                    $tiempoDesempate = $this->numberFromPayload($persistedPayload, 'tiempo');

                    $columnas['puntaje'] = $subtotal;
                    $columnas['tiempo'] = $tiempoDesempate;
                    $columnas['penalizaciones'] = $penalizaciones;
                    $columnas['valor_principal'] = $subtotal !== null
                        ? round($subtotal - $penalizaciones, 3)
                        : null;
                    $columnas['valor_secundario'] = $tiempoDesempate ?? $penalizaciones;
                    break;
                }

                if ($esTablaPuntajeMaximo) {
                    $subtotal = $this->sumDirectConfiguredFields($persistedPayload, $campos);

                    $columnas['puntaje'] = $subtotal;
                    $columnas['penalizaciones'] = 0;
                    $columnas['valor_principal'] = $subtotal;
                    $columnas['valor_secundario'] = $this->sumMaxConfiguredFields($campos);
                    break;
                }

                if ($esTablaEnfrentamiento) {
                    $lado = $ladoEnfrentamiento === 'B' ? 'b' : 'a';
                    $otroLado = $lado === 'b' ? 'a' : 'b';
                    $subtotal = $this->sumWeightedConfiguredFields($persistedPayload, $campos, false, $lado, true);
                    $penalizaciones = $this->sumWeightedConfiguredFields($persistedPayload, $campos, true, $lado, true) ?? 0.0;
                    $subtotalRival = $this->sumWeightedConfiguredFields($persistedPayload, $campos, false, $otroLado, true);
                    $penalizacionesRival = $this->sumWeightedConfiguredFields($persistedPayload, $campos, true, $otroLado, true) ?? 0.0;
                    $total = $subtotal !== null ? round($subtotal - $penalizaciones, 3) : null;
                    $totalRival = $subtotalRival !== null ? round($subtotalRival - $penalizacionesRival, 3) : null;

                    $columnas['puntaje'] = $subtotal;
                    $columnas['penalizaciones'] = $penalizaciones;
                    $columnas['valor_principal'] = $total;
                    $columnas['valor_secundario'] = $totalRival;
                    break;
                }

                $puntaje = $this->numberFromPayload($persistedPayload, 'puntaje')
                    ?? $this->sumNumericConfiguredFields($persistedPayload, $campos, ['penalizaciones']);
                $penalizaciones = $this->numberFromPayload($persistedPayload, 'penalizaciones', 0.0);
                $columnas['puntaje'] = $puntaje;
                $columnas['penalizaciones'] = $penalizaciones;
                $columnas['valor_principal'] = $puntaje !== null
                    ? round($puntaje - $penalizaciones, 3)
                    : null;
                $columnas['valor_secundario'] = $penalizaciones;
                break;
            case 'cronometro':
                $penalizaciones = $this->numberFromPayload($persistedPayload, 'penalizaciones', 0.0);
                $columnas['penalizaciones'] = $penalizaciones;
                $columnas['valor_principal'] = $columnas['tiempo'] !== null
                    ? round((float) $columnas['tiempo'] + $penalizaciones, 3)
                    : null;
                $columnas['valor_secundario'] = $columnas['penalizaciones'];
                break;
            case 'puntaje':
            case 'puntaje_jueces':
                $puntaje = $this->numberFromPayload($persistedPayload, 'puntaje')
                    ?? $this->sumNumericConfiguredFields($persistedPayload, $campos, ['penalizaciones']);
                $penalizaciones = $this->numberFromPayload($persistedPayload, 'penalizaciones', 0.0);
                $columnas['puntaje'] = $puntaje;
                $columnas['penalizaciones'] = $penalizaciones;
                $columnas['valor_principal'] = $puntaje !== null
                    ? round($puntaje - $penalizaciones, 3)
                    : null;
                $columnas['valor_secundario'] = $penalizaciones;
                break;
            case 'combate':
                $columnas['valor_principal'] = $persistedPayload['victorias'] ?? null;
                $columnas['valor_secundario'] = $persistedPayload['derrotas'] ?? null;
                $columnas['puntaje'] = isset($persistedPayload['victorias'])
                    ? round((float) $persistedPayload['victorias'], 2)
                    : null;
                break;
            case 'combate_llaves':
                $resultado = (string) ($persistedPayload['resultado'] ?? '');
                $puntos = $this->numberFromPayload($persistedPayload, 'puntos', 0.0);
                $amonestaciones = $this->numberFromPayload($persistedPayload, 'amonestaciones', 0.0);
                $descalificado = (bool) ($persistedPayload['descalificado'] ?? false);
                $base = match ($resultado) {
                    'victoria' => 3.0,
                    'empate' => 1.0,
                    'derrota' => 0.0,
                    default => null,
                };

                $columnas['puntaje'] = $base !== null ? round($base + $puntos, 3) : null;
                $columnas['penalizaciones'] = $amonestaciones;
                $columnas['valor_principal'] = $descalificado
                    ? -1000.0
                    : ($base !== null ? round(($base * 1000) + $puntos - $amonestaciones, 3) : null);
                $columnas['valor_secundario'] = $puntos;
                break;
            case 'soccer_goles':
                $marcadorA = $this->numberFromPayload($persistedPayload, 'marcador_equipo_a');
                $marcadorB = $this->numberFromPayload($persistedPayload, 'marcador_equipo_b');

                $columnas['puntaje'] = $marcadorA;
                $columnas['penalizaciones'] = 0;
                $columnas['valor_principal'] = $marcadorA !== null && $marcadorB !== null
                    ? round($marcadorA <=> $marcadorB, 3)
                    : null;
                $columnas['valor_secundario'] = $marcadorB;
                break;
            case 'dron_carrera':
                $tiempo = $this->numberFromPayload($persistedPayload, 'tiempo');
                $penalizaciones = $this->numberFromPayload($persistedPayload, 'penalizaciones_segundos', 0.0);
                $columnas['tiempo'] = $tiempo;
                $columnas['penalizaciones'] = $penalizaciones;
                $columnas['valor_principal'] = $tiempo !== null
                    ? round($tiempo + $penalizaciones, 3)
                    : null;
                $columnas['valor_secundario'] = $this->numberFromPayload($persistedPayload, 'porcentaje_recorrido');
                break;
            case 'dron_destreza':
                $puntaje = $this->numberFromPayload($persistedPayload, 'puntaje');
                $penalizaciones = $this->numberFromPayload($persistedPayload, 'penalizaciones', 0.0);
                $columnas['puntaje'] = $puntaje;
                $columnas['penalizaciones'] = $penalizaciones;
                $columnas['valor_principal'] = $puntaje !== null
                    ? round($puntaje - $penalizaciones, 3)
                    : null;
                $columnas['valor_secundario'] = $this->numberFromPayload($persistedPayload, 'obstaculos_superados');
                break;
            case 'mixto':
                $puntaje = $this->numberFromPayload($persistedPayload, 'puntaje');
                $penalizaciones = $this->numberFromPayload($persistedPayload, 'penalizaciones', 0.0);
                $columnas['puntaje'] = $puntaje;
                $columnas['penalizaciones'] = $penalizaciones;
                $columnas['valor_principal'] = $puntaje !== null
                    ? round($puntaje - $penalizaciones, 3)
                    : null;
                $columnas['valor_secundario'] = $columnas['tiempo'];
                break;
            case 'solo_registro':
                if (($persistedPayload['valor_principal'] ?? null) !== null && is_numeric($persistedPayload['valor_principal'])) {
                    $columnas['valor_principal'] = round((float) $persistedPayload['valor_principal'], 3);
                }
                break;
        }

        if (
            $columnas['puntaje'] === null
            && $columnas['valor_principal'] !== null
            && in_array($config->mecanismo?->codigo, ['registro_resultado', 'cronometro', 'dron_carrera', 'solo_registro'], true)
        ) {
            $columnas['puntaje'] = round((float) $columnas['valor_principal'], 3);
        }

        return $columnas + ['payload_json' => $persistedPayload];
    }

    private function registrarHistorial(
        Resultado $resultado,
        ?Resultado $existente,
        User $juez,
        ?string $motivoCambio
    ): void {
        ResultadoHist::create([
            'resultado_id' => $resultado->id,
            'version' => $resultado->version,
            'version_anterior' => $existente?->version ?? 0,
            'version_nueva' => $resultado->version,
            'puntaje_old' => $existente?->puntaje,
            'puntaje_new' => $resultado->puntaje,
            'tiempo_old' => $existente?->tiempo,
            'tiempo_new' => $resultado->tiempo,
            'penal_old' => $existente?->penalizaciones,
            'penal_new' => $resultado->penalizaciones,
            'estado_old' => $existente?->estado,
            'estado_new' => $resultado->estado,
            'payload_old' => $existente?->payload_json,
            'payload_new' => $resultado->payload_json,
            'motivo_cambio' => $motivoCambio,
            'editado_por' => $juez->id,
            'editado_en' => now(),
        ]);
    }

    private function serializarResultado(Resultado $resultado, array $campos): array
    {
        $valores = [];
        $payload = is_array($resultado->payload_json) ? $resultado->payload_json : [];

        foreach ($campos as $campo) {
            $key = $campo['key'];
            $payloadValue = $payload[$key] ?? null;

            $valores[$key] = match ($key) {
                'puntaje', 'tiempo', 'penalizaciones', 'observaciones' => $resultado->{$key},
                'valor_principal', 'valor_secundario' => in_array($campo['type'], ['text', 'textarea'], true)
                    ? $payloadValue
                    : $resultado->{$key},
                default => $payloadValue,
            };

            if (($campo['type'] ?? null) === 'number') {
                foreach (['a', 'b'] as $lado) {
                    $sideKey = "{$key}_{$lado}";

                    if (array_key_exists($sideKey, $payload)) {
                        $valores[$sideKey] = $payload[$sideKey];
                    }
                }
            }
        }

        return [
            'id' => (int) $resultado->id,
            'intento_numero' => (int) ($resultado->intento_numero ?? 1),
            'juez_user_id' => (int) $resultado->juez_user_id,
            'juez_nombre' => $this->nombreJuezResultado($resultado),
            'version' => (int) $resultado->version,
            'estado' => (string) $resultado->estado,
            'observaciones' => $resultado->observaciones,
            'payload' => $valores,
            'updated_at' => optional($resultado->updated_at)?->toIso8601String(),
        ];
    }

    private function parseDurationToSeconds(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return round((float) $value, 3);
        }

        $raw = strtolower(trim((string) $value));
        $raw = str_replace(',', '.', $raw);
        $raw = preg_replace('/\s+/', '', $raw);
        $raw = preg_replace('/s$/', '', $raw);

        if (is_numeric($raw)) {
            return round((float) $raw, 3);
        }

        if (preg_match('/^(?<m>\d+):(?<s>\d{1,2})(?:\.(?<ms>\d{1,3}))?$/', $raw, $matches)) {
            $seconds = ((int) $matches['m'] * 60) + (int) $matches['s'];
            $milliseconds = isset($matches['ms']) ? (float) ('0.' . str_pad($matches['ms'], 3, '0')) : 0.0;

            return round($seconds + $milliseconds, 3);
        }

        if (preg_match('/^(?<h>\d+):(?<m>\d{1,2}):(?<s>\d{1,2})(?:\.(?<ms>\d{1,3}))?$/', $raw, $matches)) {
            $seconds = ((int) $matches['h'] * 3600) + ((int) $matches['m'] * 60) + (int) $matches['s'];
            $milliseconds = isset($matches['ms']) ? (float) ('0.' . str_pad($matches['ms'], 3, '0')) : 0.0;

            return round($seconds + $milliseconds, 3);
        }

        return null;
    }

    private function numberFromPayload(array $payload, string $key, ?float $default = null): ?float
    {
        $value = $payload[$key] ?? null;

        if ($value === null || $value === '') {
            return $default;
        }

        return is_numeric($value) ? round((float) $value, 3) : $default;
    }

    private function payloadSinTiempoValido(array $payload): bool
    {
        return filter_var($payload['sin_tiempo_valido'] ?? false, FILTER_VALIDATE_BOOLEAN)
            || filter_var($payload['no_participa'] ?? false, FILTER_VALIDATE_BOOLEAN);
    }

    private function sumNumericConfiguredFields(array $payload, array $campos, array $exclude = []): ?float
    {
        $sum = 0.0;
        $hasValue = false;

        foreach ($campos as $campo) {
            $key = (string) ($campo['key'] ?? '');

            if ($key === '' || in_array($key, $exclude, true) || ($campo['type'] ?? null) !== 'number') {
                continue;
            }

            $value = $payload[$key] ?? null;

            if ($value === null || $value === '' || ! is_numeric($value)) {
                continue;
            }

            $sum += (float) $value;
            $hasValue = true;
        }

        return $hasValue ? round($sum, 3) : null;
    }

    private function configEsTablaEnfrentamiento(ConfigCalificacion $config): bool
    {
        return $this->plantillaResultado($config) === 'tabla_enfrentamiento_criterios';
    }

    private function plantillaResultado(ConfigCalificacion $config): ?string
    {
        return $this->registroMeta($config)['plantilla_resultado'] ?? null;
    }

    private function promediaResultadoFinal(ConfigCalificacion $config): bool
    {
        return false;
    }

    private function sumDirectConfiguredFields(array $payload, array $campos): float
    {
        $sum = 0.0;

        foreach ($campos as $campo) {
            $key = (string) ($campo['key'] ?? '');

            if ($key === '' || $key === 'penalizaciones' || ($campo['type'] ?? null) !== 'number') {
                continue;
            }

            $value = $payload[$key] ?? 0;
            $sum += is_numeric($value) ? (float) $value : 0.0;
        }

        return round($sum, 3);
    }

    private function sumMaxConfiguredFields(array $campos): float
    {
        $sum = 0.0;

        foreach ($campos as $campo) {
            if (($campo['type'] ?? null) !== 'number') {
                continue;
            }

            $sum += (float) ($campo['valor_unitario'] ?? 0);
        }

        return round($sum, 3);
    }

    private function averageWeightedConfiguredFields(array $payload, array $campos): array
    {
        $sum = 0.0;
        $count = 0;

        foreach ($campos as $campo) {
            $key = (string) ($campo['key'] ?? '');

            if ($key === '' || $key === 'penalizaciones' || ($campo['type'] ?? null) !== 'number') {
                continue;
            }

            $value = $payload[$key] ?? 0;
            $quantity = is_numeric($value) ? (float) $value : 0.0;
            $sum += $quantity * (float) ($campo['valor_unitario'] ?? 1);
            $count++;
        }

        $average = $count > 0 ? round($sum / $count, 2) : 0.0;

        return [round($sum, 3), $count, $average];
    }

    private function sumWeightedConfiguredFields(
        array $payload,
        array $campos,
        bool $onlyPenalties,
        ?string $suffix = null,
        bool $emptyAsZero = false
    ): ?float
    {
        $sum = 0.0;
        $hasValue = false;

        foreach ($campos as $campo) {
            $key = (string) ($campo['key'] ?? '');

            if ($key === '' || ($campo['type'] ?? null) !== 'number') {
                continue;
            }

            if ((bool) ($campo['es_penalizacion'] ?? false) !== $onlyPenalties) {
                continue;
            }

            $payloadKey = $suffix ? "{$key}_{$suffix}" : $key;
            $value = $payload[$payloadKey] ?? null;

            if ($value === null || $value === '' || ! is_numeric($value)) {
                if ($emptyAsZero) {
                    $hasValue = true;
                }

                continue;
            }

            $sum += (float) $value * (float) ($campo['valor_unitario'] ?? 1);
            $hasValue = true;
        }

        return $hasValue ? round($sum, 3) : null;
    }
}
