<?php

namespace App\Modules\Admin\Resultados\Http\Controllers;

use App\Services\ClasificacionConsolidacionService;
use App\Http\Controllers\Controller;
use App\Models\Resultado;
use App\Models\ResultadoHist;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\JsonResponse;

class ResultadoController extends Controller
{
    public function __construct(
        private readonly ClasificacionConsolidacionService $service
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
                'r.fecha_hora as ronda_fecha_hora'
            )
            ->orderBy('c.nombre')
            ->orderBy('r.fecha_hora')
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
                            'fecha_hora' => $row->ronda_fecha_hora,
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

    public function cambiarEstadoEvaluacion(Request $request, int $resultadoId): JsonResponse
    {
        $validated = $request->validate([
            'estado' => ['required', 'string', 'in:registrado,anulado'],
            'motivo_cambio' => ['nullable', 'string', 'max:255'],
        ]);

        $resultado = Resultado::query()
            ->with(['categoria.configCalificacion.mecanismo', 'equipo:id,nombre,institucion', 'juez:id,name,last_name,email'])
            ->findOrFail($resultadoId);

        if ((string) $resultado->estado === (string) $validated['estado']) {
            throw ValidationException::withMessages([
                'estado' => 'La evaluacion ya tiene ese estado.',
            ]);
        }

        $estadoAnterior = (string) $resultado->estado;
        $versionAnterior = (int) $resultado->version;

        DB::transaction(function () use ($resultado, $validated, $request, $estadoAnterior, $versionAnterior) {
            $resultado->estado = (string) $validated['estado'];
            $resultado->version = $versionAnterior + 1;
            $resultado->save();

            ResultadoHist::create([
                'resultado_id' => $resultado->id,
                'version' => $resultado->version,
                'version_anterior' => $versionAnterior,
                'version_nueva' => $resultado->version,
                'puntaje_old' => $resultado->puntaje,
                'puntaje_new' => $resultado->puntaje,
                'tiempo_old' => $resultado->tiempo,
                'tiempo_new' => $resultado->tiempo,
                'penal_old' => $resultado->penalizaciones,
                'penal_new' => $resultado->penalizaciones,
                'estado_old' => $estadoAnterior,
                'estado_new' => $resultado->estado,
                'payload_old' => $resultado->payload_json,
                'payload_new' => $resultado->payload_json,
                'motivo_cambio' => $validated['motivo_cambio'] ?? null,
                'editado_por' => $request->user()->id,
                'editado_en' => now(),
            ]);
        });

        return response()->json([
            'message' => $resultado->estado === 'anulado'
                ? 'Evaluacion anulada correctamente.'
                : 'Evaluacion restaurada correctamente.',
            'resultado' => $this->serializarEvaluacion($resultado->fresh([
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
        $campos = collect($config?->campos_json ?? []);
        $payload = is_array($resultado->payload_json) ? $resultado->payload_json : [];

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
            'observaciones' => $resultado->observaciones,
            'updated_at' => optional($resultado->updated_at)?->toIso8601String(),
            'created_at' => optional($resultado->created_at)?->toIso8601String(),
        ];
    }

    private function formatearPayloadValue(mixed $value, string $type): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (in_array($type, ['checkbox', 'boolean'], true)) {
            return filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 'Si' : 'No';
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
            'registro_resultado' => ($payload['resultado'] ?? null)
                ? ucfirst((string) $payload['resultado']) . ($resultado->valor_secundario !== null ? ' / ' . number_format((float) $resultado->valor_secundario, 0) . ' pts' : '')
                : ($resultado->valor_principal !== null ? number_format((float) $resultado->valor_principal, 3) . $suffix : 'Sin resultado'),
            'tabla_evaluacion' => $resultado->valor_principal !== null
                ? number_format((float) $resultado->valor_principal, 2) . $suffix
                : 'Sin puntaje',
            'cronometro', 'dron_carrera' => $resultado->valor_principal !== null
                ? number_format((float) $resultado->valor_principal, 3) . $suffix
                : 'Sin tiempo',
            'puntaje', 'puntaje_jueces', 'mixto', 'dron_destreza' => $resultado->valor_principal !== null
                ? number_format((float) $resultado->valor_principal, 2) . $suffix
                : 'Sin puntaje',
            'soccer_goles' => isset($payload['goles_favor'], $payload['goles_contra'])
                ? (int) $payload['goles_favor'] . ' - ' . (int) $payload['goles_contra']
                : ($resultado->valor_principal !== null ? number_format((float) $resultado->valor_principal, 0) . ' dif.' : 'Sin marcador'),
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
}
