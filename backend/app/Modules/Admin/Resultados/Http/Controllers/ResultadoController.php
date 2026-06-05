<?php

namespace App\Modules\Admin\Resultados\Http\Controllers;

use App\Services\ClasificacionConsolidacionService;
use App\Services\EvaluacionJuezService;
use App\Http\Controllers\Controller;
use App\Models\Resultado;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\JsonResponse;

class ResultadoController extends Controller
{
    public function __construct(
        private readonly ClasificacionConsolidacionService $service,
        private readonly EvaluacionJuezService $evaluacionService
    ) {
    }

    public function index(Request $request): Response
    {
        $competenciaId = (int) (
            $request->integer('competencia_id')
            ?: DB::table('catalogo.competencias')->where('estado', true)->orderByDesc('id')->value('id')
            ?: DB::table('catalogo.competencias')->orderByDesc('id')->value('id')
        );

        $competencias = DB::table('catalogo.competencias')
            ->select('id', 'nombre', 'estado')
            ->orderByDesc('estado')
            ->orderBy('nombre')
            ->get()
            ->map(fn ($item) => [
                'id' => (int) $item->id,
                'nombre' => (string) $item->nombre,
                'estado' => (bool) $item->estado,
            ]);

        $categorias = DB::table('catalogo.categorias as c')
            ->leftJoin('catalogo.config_calificacion as cc', 'cc.categoria_id', '=', 'c.id')
            ->leftJoin('catalogo.mecanismos_calificacion as mc', 'mc.id', '=', 'cc.mecanismo_calificacion_id')
            ->leftJoin('catalogo.rondas as r', 'r.categoria_id', '=', 'c.id')
            ->where('c.competencia_id', $competenciaId)
            ->select(
                'c.id',
                'c.nombre',
                'c.estado',
                'cc.orden_ranking',
                'cc.unidad_resultado',
                'mc.codigo as mecanismo_codigo',
                'mc.nombre as mecanismo_nombre',
                'r.id as ronda_id',
                'r.nombre as ronda_nombre',
                'r.orden as ronda_orden'
            )
            ->orderBy('c.nombre')
            ->orderBy('r.orden')
            ->get()
            ->groupBy('id')
            ->map(function ($rows) {
                $item = $rows->first();

                return [
                    'id' => (int) $item->id,
                    'nombre' => (string) $item->nombre,
                    'estado' => (bool) $item->estado,
                    'config_calificacion' => $item->mecanismo_codigo ? [
                        'mecanismo_codigo' => (string) $item->mecanismo_codigo,
                        'mecanismo_nombre' => (string) $item->mecanismo_nombre,
                        'orden_ranking' => (string) ($item->orden_ranking ?? 'desc'),
                        'unidad_resultado' => $item->unidad_resultado,
                    ] : null,
                    'rondas' => collect($rows)
                        ->filter(fn ($row) => $row->ronda_id)
                        ->map(fn ($row) => [
                            'id' => (int) $row->ronda_id,
                            'nombre' => (string) $row->ronda_nombre,
                            'orden' => (int) ($row->ronda_orden ?? 1),
                        ])
                        ->values()
                        ->all(),
                ];
            })
            ->values();

        return Inertia::render('Admin/Resultados', [
            'competenciaId' => $competenciaId,
            'competencias' => $competencias,
            'categorias' => $categorias,
        ]);
    }

    public function consolidado(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'competencia_id' => ['required', 'integer', 'min:1'],
            'categoria_id' => ['nullable', 'integer', 'min:1'],
            'ronda_id' => ['nullable', 'integer', 'min:1'],
        ]);

        return response()->json(
            $this->service->obtenerVista(
                (int) $validated['competencia_id'],
                isset($validated['categoria_id']) ? (int) $validated['categoria_id'] : null,
                isset($validated['ronda_id']) ? (int) $validated['ronda_id'] : null,
            )
        );
    }

    public function evaluaciones(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'competencia_id' => ['required', 'integer', 'min:1'],
            'categoria_id' => ['nullable', 'integer', 'min:1'],
            'ronda_id' => ['nullable', 'integer', 'min:1'],
            'estado' => ['nullable', 'string', 'in:borrador,registrado,publicado,anulado'],
            'juez_user_id' => ['nullable', 'integer', 'min:1'],
        ]);

        $query = Resultado::query()
            ->with([
                'categoria.configCalificacion.mecanismo',
                'competencia:id,nombre',
                'equipo:id,nombre,institucion',
                'inscripcion:id,nombre_prototipo',
                'juez:id,name,last_name,email',
                'asignacionJuez:id,rol',
            ])
            ->where('competencia_id', (int) $validated['competencia_id'])
            ->when(isset($validated['categoria_id']), fn ($q) => $q->where('categoria_id', (int) $validated['categoria_id']))
            ->when(isset($validated['ronda_id']), fn ($q) => $q->where('ronda_id', (int) $validated['ronda_id']))
            ->when(isset($validated['estado']), fn ($q) => $q->where('estado', (string) $validated['estado']))
            ->when(isset($validated['juez_user_id']), fn ($q) => $q->where('juez_user_id', (int) $validated['juez_user_id']))
            ->orderByDesc('updated_at')
            ->orderByDesc('id');

        $resultados = $query->get();

        $jueces = Resultado::query()
            ->with('juez:id,name,last_name,email')
            ->where('competencia_id', (int) $validated['competencia_id'])
            ->whereNotNull('juez_user_id')
            ->get()
            ->map(fn (Resultado $resultado) => $resultado->juez)
            ->filter()
            ->unique('id')
            ->sortBy(fn ($juez) => trim((string) $juez->name . ' ' . (string) $juez->last_name))
            ->values()
            ->map(fn ($juez) => [
                'id' => (int) $juez->id,
                'nombre' => trim((string) $juez->name . ' ' . (string) $juez->last_name) ?: (string) $juez->email,
                'email' => (string) $juez->email,
            ]);

        return response()->json([
            'summary' => [
                'total' => $resultados->count(),
                'registradas' => $resultados->where('estado', 'registrado')->count(),
                'publicadas' => $resultados->where('estado', 'publicado')->count(),
                'anuladas' => $resultados->where('estado', 'anulado')->count(),
                'equipos_count' => $resultados->pluck('equipo_id')->unique()->count(),
                'jueces_count' => $resultados->pluck('juez_user_id')->unique()->count(),
            ],
            'jueces' => $jueces,
            'rows' => $resultados
                ->map(fn (Resultado $resultado) => $this->serializarEvaluacion($resultado))
                ->values(),
        ]);
    }

    public function consolidar(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'competencia_id' => ['required', 'integer', 'min:1'],
            'categoria_id' => ['required', 'integer', 'min:1'],
            'ronda_id' => ['required', 'integer', 'min:1'],
        ]);

        return response()->json(
            $this->service->consolidar(
                (int) $validated['competencia_id'],
                (int) $validated['categoria_id'],
                (int) $validated['ronda_id'],
                $request->user()
            )
        );
    }

    public function publicar(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'competencia_id' => ['required', 'integer', 'min:1'],
            'categoria_id' => ['required', 'integer', 'min:1'],
            'ronda_id' => ['required', 'integer', 'min:1'],
            'estado_publicacion' => ['required', 'in:borrador,visible,cerrado'],
        ]);

        return response()->json(
            $this->service->actualizarEstadoPublicacion(
                (int) $validated['competencia_id'],
                (int) $validated['categoria_id'],
                (int) $validated['ronda_id'],
                (string) $validated['estado_publicacion'],
                $request->user()
            )
        );
    }

    public function actualizarEvaluacion(Request $request, int $resultadoId): JsonResponse
    {
        $validated = $request->validate([
            'payload' => ['required', 'array'],
            'observaciones' => ['nullable', 'string'],
            'motivo_cambio' => ['required', 'string', 'max:255'],
        ], [
            'motivo_cambio.required' => 'Ingresa el motivo de corrección.',
            'payload.required' => 'No se recibieron los campos del resultado.',
        ]);

        $resultado = Resultado::query()
            ->with(['categoria.configCalificacion.mecanismo'])
            ->findOrFail($resultadoId);

        $actualizado = $this->evaluacionService->corregirEvaluacionAdministrativa(
            $request->user(),
            $resultado,
            $validated
        );

        return response()->json([
            'message' => 'Evaluación corregida correctamente.',
            'resultado' => $this->serializarEvaluacion($actualizado->fresh([
                'categoria.configCalificacion.mecanismo',
                'competencia:id,nombre',
                'equipo:id,nombre,institucion',
                'inscripcion:id,nombre_prototipo',
                'juez:id,name,last_name,email',
                'asignacionJuez:id,rol',
            ])),
        ]);
    }

    private function serializarEvaluacion(Resultado $resultado): array
    {
        $config = $resultado->categoria?->configCalificacion;
        $mecanismo = (string) ($config?->mecanismo?->codigo ?? '');
        $camposEditables = $config ? $this->evaluacionService->normalizarCampos($config) : [];
        $campos = collect($camposEditables);
        $payload = is_array($resultado->payload_json) ? $resultado->payload_json : [];
        $plantillaResultado = $config ? $this->plantillaResultado($config) : null;

        return [
            'id' => (int) $resultado->id,
            'competencia_id' => (int) $resultado->competencia_id,
            'categoria_id' => (int) $resultado->categoria_id,
            'ronda_id' => (int) $resultado->ronda_id,
            'equipo_id' => (int) $resultado->equipo_id,
            'juez_user_id' => (int) $resultado->juez_user_id,
            'equipo_nombre' => (string) ($resultado->equipo?->nombre ?? ''),
            'institucion' => (string) ($resultado->equipo?->institucion ?? ''),
            'nombre_prototipo' => $resultado->inscripcion?->nombre_prototipo,
            'juez_nombre' => trim((string) ($resultado->juez?->name ?? '') . ' ' . (string) ($resultado->juez?->last_name ?? '')),
            'juez_email' => (string) ($resultado->juez?->email ?? ''),
            'rol_juez' => $resultado->asignacionJuez?->rol,
            'estado' => (string) $resultado->estado,
            'version' => (int) $resultado->version,
            'puntaje' => $resultado->puntaje,
            'tiempo' => $resultado->tiempo,
            'penalizaciones' => $resultado->penalizaciones,
            'valor_principal' => $resultado->valor_principal,
            'valor_secundario' => $resultado->valor_secundario,
            'resultado_label' => $this->formatearResultadoEvaluacion($resultado, $mecanismo, (string) ($config?->unidad_resultado ?? '')),
            'mecanismo_codigo' => $mecanismo,
            'mecanismo_nombre' => (string) ($config?->mecanismo?->nombre ?? 'Sin mecanismo'),
            'plantilla_resultado' => $plantillaResultado,
            'plantilla_nombre' => $this->nombrePlantillaResultado($plantillaResultado, (string) ($config?->mecanismo?->nombre ?? 'Sin mecanismo')),
            'payload_resumen' => $campos
                ->map(function (array $campo) use ($payload) {
                    $key = (string) ($campo['key'] ?? '');

                    return [
                        'key' => $key,
                        'label' => (string) ($campo['label'] ?? $key),
                        'value' => $this->formatearPayloadValue($payload[$key] ?? null, (string) ($campo['type'] ?? 'text')),
                    ];
                })
                ->filter(fn (array $item) => $item['key'] !== '' && $item['value'] !== null && $item['value'] !== '')
                ->values(),
            'campos_edicion' => $camposEditables,
            'payload_actual' => $payload,
            'observaciones' => $resultado->observaciones,
            'updated_at' => optional($resultado->updated_at)?->toIso8601String(),
            'created_at' => optional($resultado->created_at)?->toIso8601String(),
        ];
    }

    private function plantillaResultado($config): ?string
    {
        $reglas = is_array($config->reglas_json ?? null)
            ? (array) ($config->reglas_json['registro'] ?? [])
            : [];

        if (($reglas['plantilla_resultado'] ?? null)) {
            return (string) $reglas['plantilla_resultado'];
        }

        $campos = collect($config->campos_json ?? []);

        if ($campos->contains(fn ($campo) => ($campo['key'] ?? null) === 'tiempo' && ($campo['type'] ?? null) === 'duration')) {
            return 'tiempo';
        }

        if ($campos->contains(fn ($campo) => ($campo['key'] ?? null) === 'marcador_equipo_a')) {
            return 'marcador';
        }

        return null;
    }

    private function nombrePlantillaResultado(?string $plantilla, string $fallback): string
    {
        return match ($plantilla) {
            'marcador' => 'Marcador',
            'tiempo' => 'Tiempo',
            'tabla_enfrentamiento_criterios' => 'Tabla de enfrentamiento por criterios',
            'tabla_individual_criterios' => 'Tabla individual por criterios',
            'tabla_individual_puntaje_maximo' => 'Tabla de puntaje máximo',
            default => $fallback,
        };
    }

    private function formatearPayloadValue(mixed $value, string $type): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (in_array($type, ['checkbox', 'boolean'], true)) {
            return filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 'Sí' : 'No';
        }

        if ($type === 'duration') {
            return $this->formatearTiempoDesdeSegundos($value);
        }

        if (is_float($value) || is_int($value)) {
            return (string) $value;
        }

        return (string) $value;
    }

    private function formatearResultadoEvaluacion(Resultado $resultado, string $mecanismo, string $unidad): string
    {
        $suffix = $unidad !== '' ? ' ' . $unidad : '';
        $payload = is_array($resultado->payload_json) ? $resultado->payload_json : [];

        return match ($mecanismo) {
            'registro_resultado' => isset($payload['tiempo'])
                ? $this->formatearTiempoDesdeSegundos($resultado->tiempo ?? $payload['tiempo'])
                : (($payload['resultado'] ?? null)
                    ? ucfirst((string) $payload['resultado']) . ($resultado->valor_secundario !== null ? ' / ' . number_format((float) $resultado->valor_secundario, 0) . ' pts' : '')
                    : ($resultado->valor_principal !== null ? number_format((float) $resultado->valor_principal, 3) . $suffix : 'Sin resultado')),
            'tabla_evaluacion' => $resultado->valor_principal !== null
                ? number_format((float) $resultado->valor_principal, 2) . $suffix
                : 'Sin puntaje',
            'cronometro', 'dron_carrera' => $resultado->valor_principal !== null
                ? $this->formatearTiempoDesdeSegundos($resultado->tiempo ?? $resultado->valor_principal)
                : 'Sin tiempo',
            'puntaje', 'puntaje_jueces', 'mixto', 'dron_destreza' => $resultado->valor_principal !== null
                ? number_format((float) $resultado->valor_principal, 2) . $suffix
                : 'Sin puntaje',
            'soccer_goles' => isset($payload['marcador_equipo_a'], $payload['marcador_equipo_b'])
                ? 'Equipo A ' . (int) $payload['marcador_equipo_a'] . ' - ' . (int) $payload['marcador_equipo_b'] . ' Equipo B'
                : 'Sin marcador',
            'combate_llaves' => ($payload['resultado'] ?? null)
                ? ucfirst((string) $payload['resultado']) . ($resultado->valor_secundario !== null ? ' / ' . number_format((float) $resultado->valor_secundario, 0) . ' pts' : '')
                : 'Sin resultado',
            'combate' => $resultado->valor_principal !== null
                ? number_format((float) $resultado->valor_principal, 0) . ' victorias'
                : 'Sin resultado',
            'solo_registro' => $resultado->valor_principal !== null
                ? number_format((float) $resultado->valor_principal, 3) . $suffix
                : 'Registro',
            default => $resultado->valor_principal !== null
                ? number_format((float) $resultado->valor_principal, 3) . $suffix
                : 'Sin resultado',
        };
    }

    private function formatearTiempoDesdeSegundos(mixed $value): string
    {
        if ($value === null || $value === '') {
            return 'Sin tiempo';
        }

        $totalCentiseconds = max(0, (int) round((float) $value * 100));
        $minutes = intdiv($totalCentiseconds, 6000);
        $seconds = intdiv($totalCentiseconds % 6000, 100);
        $centiseconds = $totalCentiseconds % 100;

        return sprintf('%02d:%02d.%02d', $minutes, $seconds, $centiseconds);
    }
}
