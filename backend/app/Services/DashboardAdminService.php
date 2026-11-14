<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DashboardAdminService
{
    public function __construct(private readonly AnalisisHistoricoService $analisis) {}

    public function competencias(): Collection
    {
        return DB::table('catalogo.competencias as c')
            ->leftJoin('catalogo.temporadas as t', 't.id', '=', 'c.temporada_id')
            ->orderByDesc('c.estado')
            ->orderByDesc('c.id')
            ->get(['c.id', 'c.nombre', 'c.estado', 'c.temporada_id', 't.nombre as temporada_nombre', 't.anio'])
            ->map(fn ($item) => [
                'id' => (int) $item->id,
                'nombre' => (string) $item->nombre,
                'estado' => (bool) $item->estado,
                'temporada_id' => $item->temporada_id ? (int) $item->temporada_id : null,
                'temporada_nombre' => $item->temporada_nombre,
                'anio' => $item->anio ? (int) $item->anio : null,
            ]);
    }

    public function obtenerPanel(?array $competencia, int $userId, int $pagina = 1): array
    {
        $competenciaId = $competencia['id'] ?? null;
        $noLeidas = DB::table('comunicacion.notificaciones')
            ->where('user_id', $userId)->where('leido', false)->count();

        $panel = [
            'indicadores' => ['categorias_activas' => 0, 'inscripciones' => 0, 'jueces_activos' => 0],
            'inscripciones' => ['total' => 0, 'aprobadas' => 0, 'pendientes' => 0, 'rechazadas' => 0],
            'pendientes' => ['inscripciones' => 0, 'asignaciones' => 0, 'notificaciones' => $noLeidas, 'actas' => 0],
            'analisis' => [
                'categorias_evaluables' => 0, 'categorias_cerradas' => 0,
                'categorias_pendientes' => 0, 'porcentaje' => 0, 'estado' => 'sin_datos',
            ],
            'rondas' => ['data' => [], 'total' => 0, 'from' => null, 'to' => null, 'current_page' => 1, 'last_page' => 1, 'previous' => null, 'next' => null],
            'enlaces' => $this->enlaces($competencia),
        ];

        if (! $competenciaId) {
            return $panel;
        }

        $categorias = DB::table('catalogo.categorias')->where('competencia_id', $competenciaId)
            ->get(['id', 'estado']);
        $activas = $categorias->where('estado', true);

        // Misma prioridad de estados que la vista administrativa de inscripciones.
        $estados = DB::table('vinculaciones.inscripciones')->where('competencia_id', $competenciaId)
            ->selectRaw("CASE
                WHEN LOWER(COALESCE(estado_comprobante, 'no_subido')) = 'rechazado' THEN 'rechazadas'
                WHEN LOWER(estado) = 'confirmado' THEN 'aprobadas'
                ELSE 'pendientes' END as estado_dashboard, COUNT(*) as cantidad")
            ->groupBy('estado_dashboard')->get()->pluck('cantidad', 'estado_dashboard');
        foreach (['aprobadas', 'pendientes', 'rechazadas'] as $estado) {
            $panel['inscripciones'][$estado] = (int) ($estados[$estado] ?? 0);
        }
        $panel['inscripciones']['total'] = array_sum($panel['inscripciones']);

        $config = DB::table('catalogo.config_jueces_competencia')->where('competencia_id', $competenciaId)->first();
        $asignaciones = DB::table('vinculaciones.asignaciones_juez_categoria')
            ->whereIn('categoria_id', $activas->pluck('id'))
            ->selectRaw('categoria_id, rol, COUNT(DISTINCT juez_user_id) as cantidad')
            ->groupBy('categoria_id', 'rol')->get()->groupBy('categoria_id');
        $incompletas = $activas->filter(function ($categoria) use ($asignaciones, $config) {
            $roles = collect($asignaciones->get($categoria->id, []))->pluck('cantidad', 'rol');

            return (int) ($roles['principal'] ?? 0) < (int) ($config?->jueces_principales_requeridos ?? 1)
                || (int) ($roles['apoyo'] ?? 0) < (int) ($config?->jueces_apoyo_requeridos ?? 2);
        })->count();

        $juecesActivos = DB::table('vinculaciones.asignaciones_juez_categoria as a')
            ->join('catalogo.categorias as c', 'c.id', '=', 'a.categoria_id')
            ->join('seguridad.users as u', 'u.id', '=', 'a.juez_user_id')
            ->join('seguridad.roles as rol', 'rol.id', '=', 'u.role_id')
            ->where('c.competencia_id', $competenciaId)->where('u.estado', true)->where('rol.nombre', 'juez')
            ->distinct()->count('u.id');

        $progreso = $this->analisis->progresoCierreCompetencia($competenciaId);
        $cerrable = $progreso['categorias_evaluables'] > 0 && $progreso['categorias_pendientes'] === 0;
        $tieneCierre = $cerrable && DB::table('resultados.analisis_historico_cierres')
            ->where('competencia_id', $competenciaId)->where('tipo_cierre', 'competencia')->exists();

        $panel['indicadores'] = [
            'categorias_activas' => $activas->count(),
            'inscripciones' => $panel['inscripciones']['total'],
            'jueces_activos' => $juecesActivos,
        ];
        $panel['pendientes']['inscripciones'] = $panel['inscripciones']['pendientes'];
        $panel['pendientes']['asignaciones'] = $incompletas;
        $panel['pendientes']['actas'] = DB::table('resultados.actas_reportes')
            ->where('competencia_id', $competenciaId)->where('estado', 'generado')->count();
        $panel['analisis'] = [
            'categorias_evaluables' => (int) $progreso['categorias_evaluables'],
            'categorias_cerradas' => (int) $progreso['categorias_cerradas'],
            'categorias_pendientes' => (int) $progreso['categorias_pendientes'],
            'porcentaje' => (float) $progreso['porcentaje'],
            'estado' => $tieneCierre ? 'oficial' : ($cerrable ? 'listo' : ($progreso['categorias_evaluables'] > 0 ? 'preliminar' : 'sin_datos')),
        ];
        $panel['rondas'] = $this->rondas($competenciaId, $pagina);

        return $panel;
    }

    private function rondas(int $competenciaId, int $pagina): array
    {
        $publicaciones = DB::table('resultados.clasificaciones')->where('competencia_id', $competenciaId)
            ->whereNotNull('ronda_id')->groupBy('categoria_id', 'ronda_id')
            ->selectRaw("categoria_id, ronda_id, CASE WHEN COUNT(DISTINCT estado_publicacion) = 1
                THEN MIN(estado_publicacion) ELSE 'mixto' END as estado_publicacion");

        $rows = DB::table('catalogo.categorias as c')
            ->leftJoin('catalogo.rondas as r', 'r.categoria_id', '=', 'c.id')
            ->leftJoinSub($publicaciones, 'p', function ($join) {
                $join->on('p.categoria_id', '=', 'c.id')->on('p.ronda_id', '=', 'r.id');
            })
            ->where('c.competencia_id', $competenciaId)
            ->orderBy('c.nombre')->orderBy('c.id')->orderBy('r.orden')->orderBy('r.id')
            ->paginate(10, ['c.id as categoria_id', 'c.nombre as categoria', 'r.id as ronda_id', 'r.nombre as ronda', 'r.estado', 'p.estado_publicacion'], 'rondas_page', $pagina)
            ->appends(['competencia_id' => $competenciaId]);

        return [
            'data' => $rows->getCollection()->map(fn ($row) => [
                'categoria_id' => (int) $row->categoria_id,
                'categoria' => (string) $row->categoria,
                'ronda_id' => $row->ronda_id ? (int) $row->ronda_id : null,
                'ronda' => $row->ronda ?? 'Sin rondas',
                'estado' => $row->estado ?? 'sin_rondas',
                'publicacion' => $row->estado_publicacion ?? 'sin_consolidar',
            ])->all(),
            'total' => $rows->total(), 'from' => $rows->firstItem(), 'to' => $rows->lastItem(),
            'current_page' => $rows->currentPage(), 'last_page' => $rows->lastPage(),
            'previous' => $rows->previousPageUrl(), 'next' => $rows->nextPageUrl(),
        ];
    }

    private function enlaces(?array $competencia): array
    {
        $scope = $competencia ? ['competencia_id' => $competencia['id']] : [];
        $url = fn (string $path, array $extra = []) => $path.($scope || $extra ? '?'.http_build_query(array_merge($scope, $extra)) : '');

        return [
            'competencias' => '/admin/competencias',
            'comite' => $competencia ? '/admin/competencias?comite='.$competencia['id'] : null,
            'categorias' => $competencia ? $url('/admin/categorias') : null,
            'inscripciones' => $competencia ? $url('/admin/inscripciones') : null,
            'asignaciones' => $competencia ? $url('/admin/asignacion-jueces') : null,
            'notificaciones' => '/admin/notificaciones?tab=recibidas',
            'resultados' => $competencia ? $url('/admin/resultados') : null,
            'reportes' => $competencia ? $url('/admin/reportes') : null,
            'plantillas' => $competencia ? $url('/admin/certificados', ['tab' => 'plantillas']) : null,
            'certificados_manuales' => $competencia ? $url('/admin/certificados', ['tab' => 'manual']) : null,
            'analisis' => $competencia && $competencia['temporada_id'] ? $url('/admin/analisis-historico', ['temporada_id' => $competencia['temporada_id']]) : null,
            'usuarios' => '/admin/control-acceso',
        ];
    }
}
