<?php

namespace App\Services;

use App\Models\AnalisisHistoricoCierre;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AnalisisHistoricoService
{
    public function temporadas(): Collection
    {
        return DB::table('catalogo.temporadas as t')
            ->leftJoin('resultados.analisis_historico_cierres as cierre', function ($join) {
                $join->on('cierre.temporada_id', '=', 't.id')
                    ->where('cierre.tipo_cierre', 'temporada')
                    ->whereNull('cierre.competencia_id');
            })
            ->select([
                't.id',
                't.nombre',
                't.anio',
                'cierre.id as cierre_id',
                'cierre.cerrado_at',
            ])
            ->selectSub(function ($query) {
                $query->from('catalogo.competencias as c')
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('c.temporada_id', 't.id');
            }, 'competencias_count')
            ->orderByDesc('t.anio')
            ->orderByDesc('t.id')
            ->get()
            ->map(function ($temporada) {
                $cerrable = $this->temporadaCerrable((int) $temporada->id);

                $tieneCierreVigente = (bool) $temporada->cierre_id && $cerrable;

                return [
                    'id' => (int) $temporada->id,
                    'nombre' => (string) $temporada->nombre,
                    'anio' => (int) $temporada->anio,
                    'competencias_count' => (int) $temporada->competencias_count,
                    'tiene_cierre' => $tieneCierreVigente,
                    'cierre_desactualizado' => (bool) $temporada->cierre_id && ! $cerrable,
                    'cerrable' => $cerrable,
                    'estado_analisis' => $tieneCierreVigente ? 'cerrado' : ($cerrable ? 'listo' : 'pendiente'),
                    'cerrado_at' => $temporada->cerrado_at ? Carbon::parse($temporada->cerrado_at)->toIso8601String() : null,
                ];
            });
    }

    public function competencias(int $temporadaId): Collection
    {
        return DB::table('catalogo.competencias as c')
            ->leftJoin('resultados.analisis_historico_cierres as cierre', function ($join) {
                $join->on('cierre.competencia_id', '=', 'c.id')
                    ->where('cierre.tipo_cierre', 'competencia');
            })
            ->where('c.temporada_id', $temporadaId)
            ->select([
                'c.id',
                'c.nombre',
                'c.fecha_inicio',
                'c.fecha_fin',
                'c.estado',
                'cierre.id as cierre_id',
                'cierre.cerrado_at',
            ])
            ->orderBy('c.fecha_inicio')
            ->orderBy('c.nombre')
            ->get()
            ->map(function ($competencia) {
                $cerrable = $this->competenciaCerrable((int) $competencia->id);

                $tieneCierreVigente = (bool) $competencia->cierre_id && $cerrable;

                return [
                    'id' => (int) $competencia->id,
                    'nombre' => (string) $competencia->nombre,
                    'fecha_inicio' => $competencia->fecha_inicio,
                    'fecha_fin' => $competencia->fecha_fin,
                    'estado' => (bool) $competencia->estado,
                    'tiene_cierre' => $tieneCierreVigente,
                    'cierre_desactualizado' => (bool) $competencia->cierre_id && ! $cerrable,
                    'cerrable' => $cerrable,
                    'estado_analisis' => $tieneCierreVigente ? 'cerrado' : ($cerrable ? 'listo' : 'pendiente'),
                    'cerrado_at' => $competencia->cerrado_at ? Carbon::parse($competencia->cerrado_at)->toIso8601String() : null,
                ];
            });
    }

    public function obtenerCierreTemporada(int $temporadaId, ?User $user = null): ?AnalisisHistoricoCierre
    {
        $cierre = AnalisisHistoricoCierre::query()
            ->where('tipo_cierre', 'temporada')
            ->where('temporada_id', $temporadaId)
            ->whereNull('competencia_id')
            ->first();

        return $cierre;
    }

    public function obtenerCierreCompetencia(int $competenciaId, ?User $user = null): ?AnalisisHistoricoCierre
    {
        $cierre = AnalisisHistoricoCierre::query()
            ->where('tipo_cierre', 'competencia')
            ->where('competencia_id', $competenciaId)
            ->first();

        return $cierre;
    }

    public function obtenerPreliminarTemporada(int $temporadaId): ?array
    {
        if ($temporadaId <= 0) {
            return null;
        }

        return $this->serializarPayload(
            $this->construirPayload('temporada', $temporadaId, null, null, 'preliminar')
        );
    }

    public function obtenerPreliminarCompetencia(int $competenciaId): ?array
    {
        $temporadaId = (int) DB::table('catalogo.competencias')->where('id', $competenciaId)->value('temporada_id');

        if ($temporadaId <= 0) {
            return null;
        }

        return $this->serializarPayload(
            $this->construirPayload('competencia', $temporadaId, $competenciaId, null, 'preliminar')
        );
    }

    public function generarCierre(string $tipoCierre, int $temporadaId, ?int $competenciaId, ?User $user = null): AnalisisHistoricoCierre
    {
        if (! in_array($tipoCierre, ['temporada', 'competencia'], true)) {
            throw ValidationException::withMessages(['tipo_cierre' => 'Tipo de cierre no válido.']);
        }

        if ($tipoCierre === 'temporada' && ! $this->temporadaCerrable($temporadaId)) {
            throw ValidationException::withMessages([
                'temporada_id' => 'La temporada todavia tiene competencias pendientes por finalizar.',
            ]);
        }

        if ($tipoCierre === 'competencia' && (! $competenciaId || ! $this->competenciaCerrable($competenciaId))) {
            throw ValidationException::withMessages([
                'competencia_id' => 'La competencia todavia no ha finalizado.',
            ]);
        }

        $payload = $this->construirPayload($tipoCierre, $temporadaId, $competenciaId, $user, 'cerrado');

        $keys = $tipoCierre === 'temporada'
            ? ['tipo_cierre' => 'temporada', 'temporada_id' => $temporadaId, 'competencia_id' => null]
            : ['tipo_cierre' => 'competencia', 'competencia_id' => $competenciaId];

        return AnalisisHistoricoCierre::query()->updateOrCreate($keys, $payload);
    }

    public function serializar(?AnalisisHistoricoCierre $cierre): ?array
    {
        if (! $cierre) {
            return null;
        }

        return [
            'id' => (int) $cierre->id,
            'tipo_cierre' => (string) $cierre->tipo_cierre,
            'temporada_id' => (int) $cierre->temporada_id,
            'competencia_id' => $cierre->competencia_id ? (int) $cierre->competencia_id : null,
            'anio' => (int) $cierre->anio,
            'estado' => (string) $cierre->estado,
            'fecha_inicio' => optional($cierre->fecha_inicio)?->toDateString(),
            'fecha_fin' => optional($cierre->fecha_fin)?->toDateString(),
            'total_competencias' => (int) $cierre->total_competencias,
            'total_categorias' => (int) $cierre->total_categorias,
            'total_participantes' => (int) $cierre->total_participantes,
            'total_equipos' => (int) $cierre->total_equipos,
            'total_instituciones' => (int) $cierre->total_instituciones,
            'total_inscripciones_aprobadas' => (int) $cierre->total_inscripciones_aprobadas,
            'tasa_crecimiento_participantes' => $this->nullableFloat($cierre->tasa_crecimiento_participantes),
            'tasa_crecimiento_equipos' => $this->nullableFloat($cierre->tasa_crecimiento_equipos),
            'tasa_crecimiento_instituciones' => $this->nullableFloat($cierre->tasa_crecimiento_instituciones),
            'metricas' => $cierre->metricas_json ?? [],
            'generado_at' => optional($cierre->generado_at)?->toIso8601String(),
            'cerrado_at' => optional($cierre->cerrado_at)?->toIso8601String(),
        ];
    }

    private function serializarPayload(array $payload): array
    {
        return [
            'id' => null,
            'tipo_cierre' => (string) $payload['tipo_cierre'],
            'temporada_id' => (int) $payload['temporada_id'],
            'competencia_id' => $payload['competencia_id'] ? (int) $payload['competencia_id'] : null,
            'anio' => (int) $payload['anio'],
            'estado' => (string) $payload['estado'],
            'fecha_inicio' => $payload['fecha_inicio'],
            'fecha_fin' => $payload['fecha_fin'],
            'total_competencias' => (int) $payload['total_competencias'],
            'total_categorias' => (int) $payload['total_categorias'],
            'total_participantes' => (int) $payload['total_participantes'],
            'total_equipos' => (int) $payload['total_equipos'],
            'total_instituciones' => (int) $payload['total_instituciones'],
            'total_inscripciones_aprobadas' => (int) $payload['total_inscripciones_aprobadas'],
            'tasa_crecimiento_participantes' => $this->nullableFloat($payload['tasa_crecimiento_participantes']),
            'tasa_crecimiento_equipos' => $this->nullableFloat($payload['tasa_crecimiento_equipos']),
            'tasa_crecimiento_instituciones' => $this->nullableFloat($payload['tasa_crecimiento_instituciones']),
            'metricas' => $payload['metricas_json'],
            'generado_at' => optional($payload['generado_at'])?->toIso8601String(),
            'cerrado_at' => optional($payload['cerrado_at'])?->toIso8601String(),
        ];
    }

    private function construirPayload(
        string $tipoCierre,
        int $temporadaId,
        ?int $competenciaId,
        ?User $user,
        string $estado
    ): array {
        $temporada = DB::table('catalogo.temporadas')->where('id', $temporadaId)->first();
        abort_unless($temporada, 404);

        $competencias = DB::table('catalogo.competencias')
            ->where('temporada_id', $temporadaId)
            ->when($competenciaId, fn ($query) => $query->where('id', $competenciaId))
            ->orderBy('fecha_inicio')
            ->get();

        if ($competencias->isEmpty()) {
            throw ValidationException::withMessages([
                'temporada_id' => 'No existen competencias asociadas a esta temporada.',
            ]);
        }

        $competenciaIds = $competencias->pluck('id')->map(fn ($id) => (int) $id)->values();
        $resumen = $this->resumenScope($competenciaIds);
        $progreso = $this->progresoCierre($competenciaIds);
        $tasas = $tipoCierre === 'temporada'
            ? $this->tasasCrecimiento((int) $temporada->anio, $resumen)
            : ['participantes' => null, 'equipos' => null, 'instituciones' => null];

        $metricas = [
            'resumen_anual' => array_merge($resumen, [
                'anio' => (int) $temporada->anio,
                'temporada' => (string) $temporada->nombre,
                'tipo_cierre' => $tipoCierre,
                'estado_analisis' => $estado,
                'progreso_cierre' => $progreso,
                'competencias' => $competencias->map(fn ($competencia) => [
                    'id' => (int) $competencia->id,
                    'nombre' => (string) $competencia->nombre,
                    'fecha_inicio' => $competencia->fecha_inicio,
                    'fecha_fin' => $competencia->fecha_fin,
                ])->values()->all(),
            ]),
            'rendimiento_instituciones' => $this->rendimientoInstituciones($competenciaIds),
            'distribucion_categorias' => $this->distribucionCategorias($competenciaIds),
            'indicadores_categorias' => $this->indicadoresCategorias($competenciaIds),
            'comparativo_anual' => $tipoCierre === 'temporada' ? $this->comparativoAnual((int) $temporada->anio, $resumen) : [],
            'proyeccion' => $tipoCierre === 'temporada' ? $this->proyeccion((int) $temporada->anio, $resumen) : null,
            'observaciones' => $this->observaciones($resumen, $competenciaIds),
        ];

        $now = now();

        return [
            'tipo_cierre' => $tipoCierre,
            'temporada_id' => $temporadaId,
            'competencia_id' => $competenciaId,
            'anio' => (int) $temporada->anio,
            'estado' => $estado,
            'fecha_inicio' => $competencias->min('fecha_inicio'),
            'fecha_fin' => $competencias->max('fecha_fin'),
            'total_competencias' => $tipoCierre === 'temporada' ? $competencias->count() : 1,
            'total_categorias' => $resumen['total_categorias'],
            'total_participantes' => $resumen['total_participantes'],
            'total_equipos' => $resumen['total_equipos'],
            'total_instituciones' => $resumen['total_instituciones'],
            'total_inscripciones_aprobadas' => $resumen['total_inscripciones_aprobadas'],
            'tasa_crecimiento_participantes' => $tasas['participantes'],
            'tasa_crecimiento_equipos' => $tasas['equipos'],
            'tasa_crecimiento_instituciones' => $tasas['instituciones'],
            'metricas_json' => $metricas,
            'generado_por' => $user?->id,
            'generado_at' => $now,
            'cerrado_por' => $estado === 'cerrado' ? $user?->id : null,
            'cerrado_at' => $estado === 'cerrado' ? $now : null,
        ];
    }

    public function temporadaCerrable(int $temporadaId): bool
    {
        $competenciaIds = DB::table('catalogo.competencias')
            ->where('temporada_id', $temporadaId)
            ->pluck('id')
            ->map(fn ($id) => (int) $id);

        return $competenciaIds->isNotEmpty() && $this->scopeListoParaCierre($competenciaIds);
    }

    public function competenciaCerrable(int $competenciaId): bool
    {
        return $this->scopeListoParaCierre(collect([(int) $competenciaId]));
    }

    private function scopeListoParaCierre(Collection $competenciaIds): bool
    {
        $progreso = $this->progresoCierre($competenciaIds);

        return (int) $progreso['categorias_evaluables'] > 0
            && (int) $progreso['categorias_pendientes'] === 0;
    }

    private function progresoCierre(Collection $competenciaIds): array
    {
        $categorias = DB::table('catalogo.categorias as c')
            ->whereIn('c.competencia_id', $competenciaIds)
            ->whereExists(function ($query) {
                $query->selectRaw('1')
                    ->from('vinculaciones.inscripciones as i')
                    ->whereColumn('i.categoria_id', 'c.id')
                    ->where('i.estado', 'confirmado')
                    ->where('i.estado_comprobante', 'aprobado');
            })
            ->select('c.id', 'c.nombre', 'c.estado_resultados')
            ->orderBy('c.nombre')
            ->get();

        $rondasPorCategoria = DB::table('catalogo.rondas')
            ->whereIn('categoria_id', $categorias->pluck('id')->map(fn ($id) => (int) $id))
            ->select('id', 'categoria_id', 'nombre', 'estado', 'es_final')
            ->orderBy('orden')
            ->orderBy('id')
            ->get()
            ->groupBy('categoria_id');

        $items = $categorias->map(function ($categoria) use ($rondasPorCategoria) {
            $rondas = collect($rondasPorCategoria->get($categoria->id, collect()));
            $sinRondas = $rondas->isEmpty();
            $rondasPendientes = $rondas
                ->filter(fn ($ronda) => (string) $ronda->estado !== 'cerrada')
                ->map(fn ($ronda) => [
                    'ronda_id' => (int) $ronda->id,
                    'nombre' => (string) $ronda->nombre,
                    'estado' => (string) $ronda->estado,
                    'es_final' => (bool) $ronda->es_final,
                ])
                ->values();
            $cerrada = ! $sinRondas && $rondasPendientes->isEmpty();

            return [
                'categoria_id' => (int) $categoria->id,
                'nombre' => (string) $categoria->nombre,
                'estado_resultados' => (string) ($categoria->estado_resultados ?? 'pendiente'),
                'cerrada' => $cerrada,
                'rondas_total' => $rondas->count(),
                'rondas_cerradas' => $rondas->where('estado', 'cerrada')->count(),
                'rondas_pendientes' => $rondasPendientes->all(),
                'motivo_pendiente' => $sinRondas
                    ? 'No tiene rondas configuradas.'
                    : ($cerrada ? null : 'Tiene rondas pendientes por cerrar.'),
            ];
        });

        return [
            'categorias_evaluables' => $items->count(),
            'categorias_cerradas' => $items->where('cerrada', true)->count(),
            'categorias_pendientes' => $items->where('cerrada', false)->count(),
            'porcentaje' => $items->isEmpty() ? 0 : round(($items->where('cerrada', true)->count() / $items->count()) * 100, 1),
            'pendientes' => $items->where('cerrada', false)->values()->all(),
        ];
    }

    private function resumenScope(Collection $competenciaIds): array
    {
        $ids = $competenciaIds->all();

        $aprobadas = DB::table('vinculaciones.inscripciones as i')
            ->whereIn('i.competencia_id', $ids)
            ->where('i.estado', 'confirmado')
            ->where('i.estado_comprobante', 'aprobado');

        $inscripcionesIds = (clone $aprobadas)->pluck('i.id');
        $inscripcionesAprobadas = $inscripcionesIds->count();
        $participantes = $inscripcionesIds->isEmpty()
            ? 0
            : DB::table('vinculaciones.inscripcion_integrantes')->whereIn('inscripcion_id', $inscripcionesIds)->count();

        return [
            'total_competencias' => count($ids),
            'total_categorias' => (clone $aprobadas)->distinct('i.categoria_id')->count('i.categoria_id'),
            'total_inscripciones_aprobadas' => $inscripcionesAprobadas,
            'total_equipos' => (clone $aprobadas)->distinct('i.equipo_id')->count('i.equipo_id'),
            'total_participantes' => $participantes > 0 ? $participantes : $inscripcionesAprobadas,
            'total_instituciones' => (clone $aprobadas)
                ->join('catalogo.equipos as e', 'e.id', '=', 'i.equipo_id')
                ->whereNotNull('e.institucion')
                ->where('e.institucion', '<>', '')
                ->distinct('e.institucion')
                ->count('e.institucion'),
        ];
    }

    private function rendimientoInstituciones(Collection $competenciaIds): array
    {
        $participacion = DB::table('vinculaciones.inscripciones as i')
            ->join('catalogo.equipos as e', 'e.id', '=', 'i.equipo_id')
            ->whereIn('i.competencia_id', $competenciaIds)
            ->where('i.estado', 'confirmado')
            ->where('i.estado_comprobante', 'aprobado')
            ->whereNotNull('e.institucion')
            ->where('e.institucion', '<>', '')
            ->selectRaw('e.institucion, COUNT(DISTINCT i.competencia_id) as competencias, COUNT(DISTINCT i.categoria_id) as categorias, COUNT(DISTINCT i.equipo_id) as equipos')
            ->groupBy('e.institucion')
            ->get()
            ->keyBy('institucion');

        $podios = DB::table('resultados.clasificaciones as cl')
            ->join('catalogo.equipos as e', 'e.id', '=', 'cl.equipo_id')
            ->whereIn('cl.competencia_id', $competenciaIds)
            ->whereIn('cl.posicion', [1, 2, 3])
            ->whereNotNull('e.institucion')
            ->where('e.institucion', '<>', '')
            ->selectRaw("
                e.institucion,
                SUM(CASE WHEN cl.posicion = 1 THEN 1 ELSE 0 END) as primeros,
                SUM(CASE WHEN cl.posicion = 2 THEN 1 ELSE 0 END) as segundos,
                SUM(CASE WHEN cl.posicion = 3 THEN 1 ELSE 0 END) as terceros
            ")
            ->groupBy('e.institucion')
            ->get()
            ->keyBy('institucion');

        return $participacion
            ->keys()
            ->merge($podios->keys())
            ->unique()
            ->map(function ($institucion) use ($participacion, $podios) {
                $base = $participacion->get($institucion);
                $podio = $podios->get($institucion);
                $primeros = (int) ($podio->primeros ?? 0);
                $segundos = (int) ($podio->segundos ?? 0);
                $terceros = (int) ($podio->terceros ?? 0);

                return [
                    'institucion' => (string) $institucion,
                    'competencias' => (int) ($base->competencias ?? 0),
                    'categorias' => (int) ($base->categorias ?? 0),
                    'equipos' => (int) ($base->equipos ?? 0),
                    'primeros' => $primeros,
                    'segundos' => $segundos,
                    'terceros' => $terceros,
                    'total_podios' => $primeros + $segundos + $terceros,
                    'puntaje_ponderado' => ($primeros * 3) + ($segundos * 2) + $terceros,
                ];
            })
            ->sortByDesc('puntaje_ponderado')
            ->values()
            ->all();
    }

    private function distribucionCategorias(Collection $competenciaIds): array
    {
        $rows = DB::table('catalogo.categorias as c')
            ->join('vinculaciones.inscripciones as i', 'i.categoria_id', '=', 'c.id')
            ->leftJoin('vinculaciones.inscripcion_integrantes as ii', 'ii.inscripcion_id', '=', 'i.id')
            ->whereIn('i.competencia_id', $competenciaIds)
            ->where('i.estado', 'confirmado')
            ->where('i.estado_comprobante', 'aprobado')
            ->selectRaw('c.id, c.nombre, COUNT(DISTINCT i.id) as inscripciones, COUNT(DISTINCT i.equipo_id) as equipos, COUNT(ii.id) as participantes')
            ->groupBy('c.id', 'c.nombre')
            ->orderByDesc('inscripciones')
            ->get();

        $normalizados = $rows->map(function ($row) {
            $inscripciones = (int) $row->inscripciones;
            $participantes = (int) $row->participantes;

            return [
                'categoria_id' => (int) $row->id,
                'nombre' => (string) $row->nombre,
                'inscripciones' => $inscripciones,
                'equipos' => (int) $row->equipos,
                'participantes' => $participantes > 0 ? $participantes : $inscripciones,
            ];
        });

        $totalInscripciones = max(1, (int) $normalizados->sum('inscripciones'));

        return $normalizados
            ->map(fn (array $row) => [
                ...$row,
                'porcentaje' => round(((int) $row['inscripciones'] / $totalInscripciones) * 100, 1),
            ])
            ->values()
            ->all();
    }

    private function indicadoresCategorias(Collection $competenciaIds): array
    {
        return DB::table('catalogo.categorias as c')
            ->leftJoin('resultados.resultados as r', function ($join) use ($competenciaIds) {
                $join->on('r.categoria_id', '=', 'c.id')
                    ->whereIn('r.competencia_id', $competenciaIds)
                    ->whereNotIn('r.estado', ['borrador', 'anulado']);
            })
            ->whereIn('c.competencia_id', $competenciaIds)
            ->selectRaw('
                c.id,
                c.nombre,
                COUNT(r.id) as resultados_registrados,
                MIN(r.tiempo) as mejor_tiempo,
                AVG(r.tiempo) as tiempo_promedio,
                MAX(r.puntaje) as mejor_puntaje,
                AVG(r.puntaje) as puntaje_promedio
            ')
            ->groupBy('c.id', 'c.nombre')
            ->orderBy('c.nombre')
            ->get()
            ->map(fn ($row) => [
                'categoria_id' => (int) $row->id,
                'nombre' => (string) $row->nombre,
                'resultados_registrados' => (int) $row->resultados_registrados,
                'mejor_tiempo' => $this->nullableFloat($row->mejor_tiempo),
                'tiempo_promedio' => $this->nullableFloat($row->tiempo_promedio),
                'mejor_puntaje' => $this->nullableFloat($row->mejor_puntaje),
                'puntaje_promedio' => $this->nullableFloat($row->puntaje_promedio),
            ])
            ->values()
            ->all();
    }

    private function comparativoAnual(int $anioActual, array $resumenActual): array
    {
        $temporadas = DB::table('catalogo.temporadas')
            ->where('anio', '<=', $anioActual)
            ->orderBy('anio')
            ->get(['id', 'nombre', 'anio']);

        return $temporadas
            ->map(function ($temporada) use ($anioActual, $resumenActual) {
                if ((int) $temporada->anio === $anioActual) {
                    $resumen = $resumenActual;
                } else {
                    $ids = DB::table('catalogo.competencias')
                        ->where('temporada_id', $temporada->id)
                        ->pluck('id')
                        ->map(fn ($id) => (int) $id);
                    $resumen = $ids->isEmpty() ? [
                        'total_participantes' => 0,
                        'total_equipos' => 0,
                        'total_instituciones' => 0,
                        'total_inscripciones_aprobadas' => 0,
                    ] : $this->resumenScope($ids);
                }

                return [
                    'temporada_id' => (int) $temporada->id,
                    'anio' => (int) $temporada->anio,
                    'nombre' => (string) $temporada->nombre,
                    'participantes' => (int) $resumen['total_participantes'],
                    'equipos' => (int) $resumen['total_equipos'],
                    'instituciones' => (int) $resumen['total_instituciones'],
                    'inscripciones_aprobadas' => (int) $resumen['total_inscripciones_aprobadas'],
                ];
            })
            ->values()
            ->all();
    }

    private function proyeccion(int $anioActual, array $resumenActual): array
    {
        $comparativo = collect($this->comparativoAnual($anioActual, $resumenActual));
        $anterior = $comparativo->count() > 1 ? $comparativo->slice(-2, 1)->first() : null;

        $growth = fn (string $key) => $anterior && (int) $anterior[$key] > 0
            ? (((int) $resumenActual['total_' . $key] - (int) $anterior[$key]) / (int) $anterior[$key])
            : 0.12;

        $crecimientoParticipantes = $growth('participantes');
        $crecimientoEquipos = $growth('equipos');
        $crecimientoInstituciones = $growth('instituciones');

        return [
            'anio' => $anioActual + 1,
            'participantes_estimados' => max(0, (int) round($resumenActual['total_participantes'] * (1 + $crecimientoParticipantes))),
            'equipos_estimados' => max(0, (int) round($resumenActual['total_equipos'] * (1 + $crecimientoEquipos))),
            'instituciones_estimadas' => max(0, (int) round($resumenActual['total_instituciones'] * (1 + $crecimientoInstituciones))),
            'crecimiento_estimado_participantes' => round($crecimientoParticipantes * 100, 2),
            'metodo' => 'Basado en el crecimiento del último cierre histórico disponible.',
        ];
    }

    private function tasasCrecimiento(int $anioActual, array $resumenActual): array
    {
        $temporadaAnterior = DB::table('catalogo.temporadas')
            ->where('anio', '<', $anioActual)
            ->orderByDesc('anio')
            ->first(['id']);

        if (! $temporadaAnterior) {
            return ['participantes' => null, 'equipos' => null, 'instituciones' => null];
        }

        $ids = DB::table('catalogo.competencias')
            ->where('temporada_id', $temporadaAnterior->id)
            ->pluck('id')
            ->map(fn ($id) => (int) $id);

        if ($ids->isEmpty()) {
            return ['participantes' => null, 'equipos' => null, 'instituciones' => null];
        }

        $anterior = $this->resumenScope($ids);

        return [
            'participantes' => $this->porcentajeCrecimiento($anterior['total_participantes'], $resumenActual['total_participantes']),
            'equipos' => $this->porcentajeCrecimiento($anterior['total_equipos'], $resumenActual['total_equipos']),
            'instituciones' => $this->porcentajeCrecimiento($anterior['total_instituciones'], $resumenActual['total_instituciones']),
        ];
    }

    private function observaciones(array $resumen, Collection $competenciaIds): array
    {
        $categorias = collect($this->distribucionCategorias($competenciaIds));
        $instituciones = collect($this->rendimientoInstituciones($competenciaIds));
        $observaciones = [];

        if ($categorias->isNotEmpty()) {
            $top = $categorias->first();
            $observaciones[] = "La categoría con mayor participación fue {$top['nombre']} con {$top['participantes']} participantes.";
        }

        if ($instituciones->isNotEmpty()) {
            $top = $instituciones->first();
            $observaciones[] = "La institución mejor posicionada fue {$top['institucion']} con {$top['total_podios']} podios.";
        }

        $observaciones[] = "El cierre consolida {$resumen['total_participantes']} participantes, {$resumen['total_equipos']} equipos y {$resumen['total_instituciones']} instituciones.";

        return $observaciones;
    }

    private function porcentajeCrecimiento(int $anterior, int $actual): ?float
    {
        if ($anterior <= 0) {
            return null;
        }

        return round((($actual - $anterior) / $anterior) * 100, 2);
    }

    private function nullableFloat(mixed $value): ?float
    {
        return $value === null ? null : round((float) $value, 2);
    }
}
