<?php

namespace App\Services;

use App\Exceptions\EvaluacionConcurrencyException;
use App\Models\AsignacionJuezCategoria;
use App\Models\ConfigCalificacion;
use App\Models\Inscripcion;
use App\Models\Resultado;
use App\Models\ResultadoHist;
use App\Models\Ronda;
use App\Models\Sorteo;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class EvaluacionJuezService
{
    public function getContextoJuez(User $juez, ?int $categoriaId = null, ?int $rondaId = null): array
    {
        $asignaciones = AsignacionJuezCategoria::query()
            ->with([
                'categoria.competencia:id,nombre',
                'categoria.configCalificacion.mecanismo:id,codigo,nombre',
                'categoria.rondas:id,categoria_id,nombre,tipo,estado,fecha_hora',
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
                    ],
                    'config_calificacion' => $config ? [
                        'id' => (int) $config->id,
                        'mecanismo_codigo' => (string) ($mecanismo?->codigo ?? ''),
                        'mecanismo_nombre' => (string) ($mecanismo?->nombre ?? ''),
                        'unidad_resultado' => $config->unidad_resultado,
                        'orden_ranking' => (string) $config->orden_ranking,
                        'plantilla_resultado' => $this->registroMeta($config)['plantilla_resultado'] ?? null,
                        'requiere_aprobacion_admin' => (bool) $config->requiere_aprobacion_admin,
                        'visible_publico_en_vivo' => (bool) $config->visible_publico_en_vivo,
                        'permite_edicion_juez' => (bool) $config->permite_edicion_juez,
                    ] : null,
                    'rondas' => $categoria->rondas
                        ->filter(fn (Ronda $ronda) => ($ronda->estado ?? 'activa') === 'activa')
                        ->sortBy('fecha_hora')
                        ->values()
                        ->map(fn (Ronda $ronda) => [
                            'id' => (int) $ronda->id,
                            'nombre' => (string) $ronda->nombre,
                            'fecha_hora' => optional($ronda->fecha_hora)?->toIso8601String(),
                        ])
                        ->all(),
                ];
            })
            ->values();

        $categoriaSeleccionada = $categorias->firstWhere('categoria.id', $categoriaId)
            ?? $categorias->first();

        $rondas = collect($categoriaSeleccionada['rondas'] ?? []);
        $rondaSeleccionada = $rondas->firstWhere('id', $rondaId) ?? $rondas->first();

        $categoriaSeleccionadaId = $categoriaSeleccionada['categoria']['id'] ?? null;
        $rondaSeleccionadaId = $rondaSeleccionada['id'] ?? null;

        return [
            'categorias' => $categorias->all(),
            'seleccion' => [
                'categoria_id' => $categoriaSeleccionadaId,
                'ronda_id' => $rondaSeleccionadaId,
            ],
            'sorteo' => $rondaSeleccionadaId
                ? $this->getSorteoVigente((int) $rondaSeleccionadaId)
                : null,
            'equipos' => $categoriaSeleccionadaId && $rondaSeleccionadaId
                ? $this->getEquiposPorEvaluar(
                    $juez,
                    (int) $categoriaSeleccionadaId,
                    (int) $rondaSeleccionadaId
                )
                : [],
        ];
    }

    public function generarSorteo(User $juez, int $rondaId, bool $regenerar = false): array
    {
        $ronda = Ronda::query()->with('categoria.configCalificacion.mecanismo')->find($rondaId);

        if (! $ronda || ! $ronda->categoria) {
            throw ValidationException::withMessages([
                'ronda_id' => 'La ronda seleccionada no existe o no tiene categoria asociada.',
            ]);
        }

        $categoria = $ronda->categoria;
        $config = $categoria->configCalificacion;

        if (! $config) {
            throw ValidationException::withMessages([
                'ronda_id' => 'La categoria de la ronda no tiene configuracion de calificacion.',
            ]);
        }

        $asignado = AsignacionJuezCategoria::query()
            ->where('categoria_id', $categoria->id)
            ->where('juez_user_id', $juez->id)
            ->exists();

        if (! $asignado) {
            throw ValidationException::withMessages([
                'ronda_id' => 'No tienes asignacion activa para esta categoria.',
            ]);
        }

        $existente = Sorteo::query()
            ->where('ronda_id', $ronda->id)
            ->where('estado', '!=', 'anulado')
            ->first();

        if ($existente && ! $regenerar) {
            return $this->serializarSorteo($existente->load('detalles.inscripcion.equipo'));
        }

        if ($ronda->resultados()->exists()) {
            throw ValidationException::withMessages([
                'ronda_id' => $regenerar
                    ? 'No se puede generar nuevamente el sorteo porque esta ronda ya tiene resultados registrados.'
                    : 'No se puede generar el sorteo porque esta ronda ya tiene resultados registrados.',
            ]);
        }

        $inscripciones = $this->inscripcionesSorteables((int) $categoria->id);

        if ($inscripciones->isEmpty()) {
            throw ValidationException::withMessages([
                'ronda_id' => 'No hay participantes inscritos para sortear en esta categoria.',
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
                    'permitir_bye' => $tipoSorteo === 'enfrentamiento',
                ],
            ]);

            $sorteo->detalles()->createMany($detalles);

            return $sorteo->load('detalles.inscripcion.equipo');
        });

        return $this->serializarSorteo($sorteo);
    }

    public function construirFormulario(User $juez, int $rondaId, int $equipoId): array
    {
        $contexto = $this->resolverContextoEvaluacion($juez, $rondaId, $equipoId);
        $resultado = $contexto['resultado'];
        $config = $contexto['config'];
        $campos = $this->normalizarCampos($config);

        return [
            'ronda' => [
                'id' => (int) $contexto['ronda']->id,
                'nombre' => (string) $contexto['ronda']->nombre,
                'fecha_hora' => optional($contexto['ronda']->fecha_hora)?->toIso8601String(),
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
                'permite_edicion_juez' => (bool) $config->permite_edicion_juez,
                'requiere_aprobacion_admin' => (bool) $config->requiere_aprobacion_admin,
                'campos' => $campos,
            ],
            'resultado_actual' => $resultado ? $this->serializarResultado($resultado, $campos) : null,
        ];
    }

    public function guardarEvaluacion(User $juez, array $payload): array
    {
        $contexto = $this->resolverContextoEvaluacion(
            $juez,
            (int) $payload['ronda_id'],
            (int) $payload['equipo_id']
        );

        $config = $contexto['config'];
        $campos = $this->normalizarCampos($config);
        $validated = $this->validarPayloadSegunConfig($payload, $campos);

        $resultado = DB::transaction(function () use ($contexto, $config, $campos, $validated, $juez) {
            $existente = Resultado::query()
                ->where('ronda_id', $contexto['ronda']->id)
                ->where('equipo_id', $contexto['inscripcion']->equipo_id)
                ->where('juez_user_id', $juez->id)
                ->lockForUpdate()
                ->first();

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

            $normalizado = $this->normalizarValoresEvaluacion(
                $config,
                $campos,
                Arr::get($validated, 'payload', []),
                $validated['observaciones'] ?? null,
                $existente
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

        return $this->construirFormulario($juez, (int) $payload['ronda_id'], (int) $payload['equipo_id'])
            + ['guardado' => true, 'resultado' => $this->serializarResultado($resultado, $campos)];
    }

    private function resolverContextoEvaluacion(User $juez, int $rondaId, int $equipoId): array
    {
        $ronda = Ronda::query()->with('categoria.competencia')->find($rondaId);

        if (! $ronda || ! $ronda->categoria) {
            throw ValidationException::withMessages([
                'ronda_id' => 'La ronda seleccionada no existe o no tiene categoría asociada.',
            ]);
        }

        $categoria = $ronda->categoria;
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
            ->where('competencia_id', $categoria->competencia_id)
            ->where('categoria_id', $categoria->id)
            ->where('equipo_id', $equipoId)
            ->first();

        if (! $inscripcion) {
            throw ValidationException::withMessages([
                'equipo_id' => 'El equipo no está inscrito en la categoría seleccionada.',
            ]);
        }

        $resultado = Resultado::query()
            ->where('ronda_id', $ronda->id)
            ->where('equipo_id', $equipoId)
            ->where('juez_user_id', $juez->id)
            ->first();

        $inscripcionEnSorteo = Sorteo::query()
            ->where('ronda_id', $ronda->id)
            ->where('estado', '!=', 'anulado')
            ->whereHas('detalles', fn ($query) => $query
                ->where('inscripcion_id', $inscripcion->id)
                ->where('estado', '!=', 'bye'))
            ->exists();

        if (! $inscripcionEnSorteo) {
            throw ValidationException::withMessages([
                'equipo_id' => 'Genera el sorteo de esta ronda antes de registrar resultados o selecciona un participante que no tenga bye.',
            ]);
        }

        return compact('ronda', 'categoria', 'config', 'asignacion', 'inscripcion', 'resultado');
    }

    private function getEquiposPorEvaluar(User $juez, int $categoriaId, int $rondaId): array
    {
        $equipos = Inscripcion::query()
            ->with('equipo')
            ->where('categoria_id', $categoriaId)
            ->orderBy('id')
            ->get()
            ->map(function (Inscripcion $inscripcion) use ($juez, $rondaId) {
                $resultado = Resultado::query()
                    ->where('ronda_id', $rondaId)
                    ->where('equipo_id', $inscripcion->equipo_id)
                    ->where('juez_user_id', $juez->id)
                    ->first();

                return [
                    'inscripcion_id' => (int) $inscripcion->id,
                    'equipo_id' => (int) $inscripcion->equipo_id,
                    'equipo_nombre' => (string) ($inscripcion->equipo?->nombre ?? ''),
                    'institucion' => (string) ($inscripcion->equipo?->institucion ?? ''),
                    'nombre_prototipo' => $inscripcion->nombre_prototipo,
                    'resultado_id' => $resultado?->id,
                    'resultado_estado' => $resultado?->estado,
                    'resultado_version' => $resultado?->version,
                    'actualizado_at' => optional($resultado?->updated_at)?->toIso8601String(),
                ];
            })
            ->values();

        $sorteo = Sorteo::query()
            ->with('detalles')
            ->where('ronda_id', $rondaId)
            ->where('estado', '!=', 'anulado')
            ->first();

        if (! $sorteo) {
            return $equipos->all();
        }

        $detallesPorInscripcion = $sorteo->detalles->keyBy('inscripcion_id');

        return $equipos
            ->filter(function (array $equipo) use ($detallesPorInscripcion) {
                $detalle = $detallesPorInscripcion->get($equipo['inscripcion_id']);

                return $detalle && $detalle->estado !== 'bye';
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
            ->all();
    }

    private function normalizarCampos(ConfigCalificacion $config): array
    {
        return collect($config->campos_json ?? [])
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
            ];
        }

        $codigo = (string) ($config->mecanismo?->codigo ?? '');

        return match ($codigo) {
            'tabla_evaluacion', 'puntaje', 'puntaje_jueces', 'dron_destreza', 'mixto' => [
                'tipo_registro' => 'tabla_evaluacion',
                'modalidad_competencia' => 'participacion_individual',
                'plantilla_resultado' => 'tabla_individual_criterios',
                'esquema_jueces' => 'registro_cualquier_juez',
            ],
            'soccer_goles' => [
                'tipo_registro' => 'registro_resultado',
                'modalidad_competencia' => 'enfrentamiento_directo',
                'plantilla_resultado' => 'goles',
                'esquema_jueces' => 'registro_cualquier_juez',
            ],
            'combate', 'combate_llaves' => [
                'tipo_registro' => 'registro_resultado',
                'modalidad_competencia' => 'enfrentamiento_directo',
                'plantilla_resultado' => 'ganador',
                'esquema_jueces' => 'registro_cualquier_juez',
            ],
            default => [
                'tipo_registro' => 'registro_resultado',
                'modalidad_competencia' => 'participacion_individual',
                'plantilla_resultado' => 'tiempo',
                'esquema_jueces' => 'registro_cualquier_juez',
            ],
        };
    }

    private function inscripcionesSorteables(int $categoriaId)
    {
        return Inscripcion::query()
            ->with('equipo')
            ->where('categoria_id', $categoriaId)
            ->orderBy('id')
            ->get();
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
        $parejas = [];
        $grupo = 1;

        while ($pendientes->count() > 1) {
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

        if ($pendientes->count() === 1) {
            $parejas[] = [
                'grupo' => $grupo,
                'items' => [
                    ['inscripcion' => $pendientes->first(), 'lado' => 'BYE', 'estado' => 'bye'],
                ],
            ];
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

        return $detalles;
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

    private function validarPayloadSegunConfig(array $payload, array $campos): array
    {
        $rules = [
            'ronda_id' => ['required', 'integer', 'min:1'],
            'equipo_id' => ['required', 'integer', 'min:1'],
            'version' => ['nullable', 'integer', 'min:0'],
            'motivo_cambio' => ['nullable', 'string', 'max:255'],
            'observaciones' => ['nullable', 'string'],
            'payload' => ['nullable', 'array'],
        ];

        foreach ($campos as $campo) {
            $fieldRules = [$campo['required'] ? 'required' : 'nullable'];

            switch ($campo['type']) {
                case 'number':
                    $fieldRules[] = 'numeric';
                    if (isset($campo['max']) && is_numeric($campo['max'])) {
                        $fieldRules[] = 'max:' . (float) $campo['max'];
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
                    $fieldRules[] = function ($attribute, $value, $fail) {
                        if ($value === null || $value === '') {
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
        }

        return Validator::make(
            $payload,
            $rules,
            [
                'ronda_id.required' => 'La ronda es obligatoria.',
                'equipo_id.required' => 'El equipo es obligatorio.',
                'payload.array' => 'Los datos de evaluación deben enviarse como objeto.',
            ]
        )->validate();
    }

    private function normalizarValoresEvaluacion(
        ConfigCalificacion $config,
        array $campos,
        array $payload,
        ?string $observaciones,
        ?Resultado $existente
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
        $esTablaEnfrentamiento = $config->mecanismo?->codigo === 'tabla_evaluacion'
            && ($registroMeta['plantilla_resultado'] ?? '') === 'tabla_enfrentamiento_criterios';
        $esTablaIndividual = $config->mecanismo?->codigo === 'tabla_evaluacion'
            && ($registroMeta['plantilla_resultado'] ?? '') === 'tabla_individual_criterios';

        if ($esTablaEnfrentamiento) {
            foreach ($campos as $campo) {
                if (($campo['type'] ?? null) !== 'number') {
                    continue;
                }

                foreach (['a', 'b'] as $lado) {
                    $sideKey = "{$campo['key']}_{$lado}";
                    $sideValue = $payload[$sideKey] ?? null;

                    $persistedPayload[$sideKey] = ($sideValue === null || $sideValue === '' || ! is_numeric($sideValue))
                        ? null
                        : round((float) $sideValue, 3);
                }
            }
        }

        switch ($config->mecanismo?->codigo) {
            case 'registro_resultado':
                if (($registroMeta['plantilla_resultado'] ?? '') === 'goles') {
                    $golesFavor = $this->numberFromPayload($persistedPayload, 'goles_favor');
                    $golesContra = $this->numberFromPayload($persistedPayload, 'goles_contra');
                    $faltas = $this->numberFromPayload($persistedPayload, 'faltas', 0.0);
                    $amonestaciones = $this->numberFromPayload($persistedPayload, 'amonestaciones', 0.0);

                    $columnas['puntaje'] = $golesFavor;
                    $columnas['penalizaciones'] = round($faltas + $amonestaciones, 3);
                    $columnas['valor_principal'] = $golesFavor !== null && $golesContra !== null
                        ? round($golesFavor - $golesContra, 3)
                        : null;
                    $columnas['valor_secundario'] = $golesFavor;
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
                    $subtotal = $this->sumWeightedConfiguredFields($persistedPayload, $campos, false);
                    $penalizaciones = $this->sumWeightedConfiguredFields($persistedPayload, $campos, true) ?? 0.0;

                    $columnas['puntaje'] = $subtotal;
                    $columnas['penalizaciones'] = $penalizaciones;
                    $columnas['valor_principal'] = $subtotal !== null
                        ? round($subtotal - $penalizaciones, 3)
                        : null;
                    $columnas['valor_secundario'] = $penalizaciones;
                    break;
                }

                if ($esTablaEnfrentamiento) {
                    $subtotal = $this->sumWeightedConfiguredFields($persistedPayload, $campos, false);
                    $penalizaciones = $this->sumWeightedConfiguredFields($persistedPayload, $campos, true) ?? 0.0;

                    $columnas['puntaje'] = $subtotal;
                    $columnas['penalizaciones'] = $penalizaciones;
                    $columnas['valor_principal'] = $subtotal !== null
                        ? round($subtotal - $penalizaciones, 3)
                        : null;
                    $columnas['valor_secundario'] = $penalizaciones;
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
                $golesFavor = $this->numberFromPayload($persistedPayload, 'goles_favor');
                $golesContra = $this->numberFromPayload($persistedPayload, 'goles_contra');
                $faltas = $this->numberFromPayload($persistedPayload, 'faltas', 0.0);
                $amonestaciones = $this->numberFromPayload($persistedPayload, 'amonestaciones', 0.0);

                $columnas['puntaje'] = $golesFavor;
                $columnas['penalizaciones'] = round($faltas + $amonestaciones, 3);
                $columnas['valor_principal'] = $golesFavor !== null && $golesContra !== null
                    ? round($golesFavor - $golesContra, 3)
                    : null;
                $columnas['valor_secundario'] = $golesFavor;
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

    private function sumWeightedConfiguredFields(array $payload, array $campos, bool $onlyPenalties): ?float
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

            $value = $payload[$key] ?? null;

            if ($value === null || $value === '' || ! is_numeric($value)) {
                continue;
            }

            $sum += (float) $value * (float) ($campo['valor_unitario'] ?? 1);
            $hasValue = true;
        }

        return $hasValue ? round($sum, 3) : null;
    }
}
