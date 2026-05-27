<?php

namespace App\Modules\Publico\Resultados\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Services\ClasificacionConsolidacionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ResultadoPublicoController extends Controller
{
    public function __construct(
        private readonly ClasificacionConsolidacionService $service
    ) {
    }

    public function index(Request $request): Response
    {
        $competenciasConPublicacion = DB::table('resultados.clasificaciones')
            ->join('catalogo.rondas as r', 'r.id', '=', 'resultados.clasificaciones.ronda_id')
            ->whereIn('resultados.clasificaciones.estado_publicacion', ['visible', 'cerrado'])
            ->where('r.es_final', true)
            ->pluck('resultados.clasificaciones.competencia_id')
            ->unique()
            ->values();

        $competenciaId = (int) (
            $request->integer('competencia_id')
            ?: DB::table('catalogo.competencias')
                ->whereIn('id', $competenciasConPublicacion)
                ->orderByDesc('estado')
                ->orderBy('nombre')
                ->value('id')
        );

        $competencias = DB::table('catalogo.competencias')
            ->whereIn('id', $competenciasConPublicacion)
            ->select('id', 'nombre', 'estado')
            ->orderByDesc('estado')
            ->orderBy('nombre')
            ->get()
            ->map(fn ($item) => [
                'id' => (int) $item->id,
                'nombre' => (string) $item->nombre,
                'estado' => (bool) $item->estado,
            ])
            ->values();

        $categorias = collect();
        $vista = [
            'scope' => null,
            'summary' => ['equipos_count' => 0, 'updated_at' => null],
            'rows' => [],
            'error' => null,
        ];

        if ($competenciaId > 0) {
            $categoriaIds = DB::table('resultados.clasificaciones')
                ->join('catalogo.rondas as r', 'r.id', '=', 'resultados.clasificaciones.ronda_id')
                ->where('resultados.clasificaciones.competencia_id', $competenciaId)
                ->whereIn('resultados.clasificaciones.estado_publicacion', ['visible', 'cerrado'])
                ->where('r.es_final', true)
                ->pluck('resultados.clasificaciones.categoria_id')
                ->unique()
                ->values();

            $categorias = DB::table('catalogo.categorias as c')
                ->whereIn('c.id', $categoriaIds)
                ->select('c.id', 'c.nombre')
                ->orderBy('c.nombre')
                ->get()
                ->map(fn ($item) => [
                    'id' => (int) $item->id,
                    'nombre' => (string) $item->nombre,
                ])
                ->values();

            try {
                $vista = $this->service->obtenerVistaPublica(
                    $competenciaId,
                    $request->integer('categoria_id') ?: null,
                    null,
                ) + ['error' => null];
            } catch (ValidationException $exception) {
                $vista['error'] = collect($exception->errors())->flatten()->first();
            }
        } else {
            $vista['error'] = 'Todavía no existen clasificaciones publicadas.';
        }

        return Inertia::render('Publico/Resultados', [
            'competenciaId' => $competenciaId > 0 ? $competenciaId : null,
            'competencias' => $competencias,
            'categorias' => $categorias,
            'vista' => $vista,
        ]);
    }

    public function enVivo(Request $request): Response
    {
        $competenciasConVivo = DB::table('catalogo.config_calificacion as cc')
            ->join('catalogo.categorias as c', 'c.id', '=', 'cc.categoria_id')
            ->where('cc.visible_publico_en_vivo', true)
            ->pluck('c.competencia_id')
            ->unique()
            ->values();

        $competenciaId = (int) (
            $request->integer('competencia_id')
            ?: DB::table('catalogo.competencias')
                ->whereIn('id', $competenciasConVivo)
                ->orderByDesc('estado')
                ->orderBy('nombre')
                ->value('id')
        );

        $competencias = DB::table('catalogo.competencias')
            ->whereIn('id', $competenciasConVivo)
            ->select('id', 'nombre', 'estado')
            ->orderByDesc('estado')
            ->orderBy('nombre')
            ->get()
            ->map(fn ($item) => [
                'id' => (int) $item->id,
                'nombre' => (string) $item->nombre,
                'estado' => (bool) $item->estado,
            ])
            ->values();

        return Inertia::render('Publico/ResultadosEnVivo', [
            'competenciaId' => $competenciaId > 0 ? $competenciaId : null,
            'competencias' => $competencias,
        ]);
    }

    public function sorteos(Request $request): Response
    {
        $competenciasConSorteo = DB::table('catalogo.sorteos as s')
            ->join('catalogo.rondas as r', 'r.id', '=', 's.ronda_id')
            ->join('catalogo.categorias as c', 'c.id', '=', 'r.categoria_id')
            ->where('s.estado', '!=', 'anulado')
            ->pluck('c.competencia_id')
            ->unique()
            ->values();

        $competenciaId = (int) (
            $request->integer('competencia_id')
            ?: DB::table('catalogo.competencias')
                ->whereIn('id', $competenciasConSorteo)
                ->orderByDesc('estado')
                ->orderBy('nombre')
                ->value('id')
            ?: DB::table('catalogo.competencias')
                ->orderByDesc('estado')
                ->orderBy('nombre')
                ->value('id')
        );

        $competencias = DB::table('catalogo.competencias')
            ->when($competenciasConSorteo->isNotEmpty(), fn ($query) => $query->whereIn('id', $competenciasConSorteo))
            ->select('id', 'nombre', 'estado')
            ->orderByDesc('estado')
            ->orderBy('nombre')
            ->get()
            ->map(fn ($item) => [
                'id' => (int) $item->id,
                'nombre' => (string) $item->nombre,
                'estado' => (bool) $item->estado,
            ])
            ->values();

        $categorias = collect();
        $vista = [
            'scope' => null,
            'sorteo' => null,
            'error' => null,
        ];

        if ($competenciaId > 0) {
            $categorias = DB::table('catalogo.categorias as c')
                ->leftJoin('catalogo.rondas as r', 'r.categoria_id', '=', 'c.id')
                ->where('c.competencia_id', $competenciaId)
                ->select('c.id', 'c.nombre', 'r.id as ronda_id', 'r.nombre as ronda_nombre', 'r.orden as ronda_orden')
                ->orderBy('c.nombre')
                ->orderBy('r.orden')
                ->orderBy('r.id')
                ->get()
                ->groupBy('id')
                ->map(function ($rows) {
                    $item = $rows->first();

                    return [
                        'id' => (int) $item->id,
                        'nombre' => (string) $item->nombre,
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

            $categoriaSeleccionada = $categorias->firstWhere('id', $request->integer('categoria_id'))
                ?? $categorias->first();
            $rondas = collect($categoriaSeleccionada['rondas'] ?? []);
            $rondaSeleccionada = $rondas->firstWhere('id', $request->integer('ronda_id'))
                ?? $rondas->first();

            if ($categoriaSeleccionada && $rondaSeleccionada) {
                $vista = [
                    'scope' => [
                        'competencia_id' => $competenciaId,
                        'categoria_id' => (int) $categoriaSeleccionada['id'],
                        'categoria_nombre' => (string) $categoriaSeleccionada['nombre'],
                        'ronda_id' => (int) $rondaSeleccionada['id'],
                        'ronda_nombre' => (string) $rondaSeleccionada['nombre'],
                    ],
                    'sorteo' => $this->sorteoPublico((int) $rondaSeleccionada['id']),
                    'error' => null,
                ];
            } else {
                $vista['error'] = 'No hay categorias o rondas disponibles para mostrar sorteos.';
            }
        } else {
            $vista['error'] = 'No hay competencias disponibles para mostrar sorteos.';
        }

        return Inertia::render('Publico/Sorteos', [
            'competenciaId' => $competenciaId > 0 ? $competenciaId : null,
            'competencias' => $competencias,
            'categorias' => $categorias,
            'vista' => $vista,
        ]);
    }

    private function sorteoPublico(int $rondaId): ?array
    {
        $sorteo = DB::table('catalogo.sorteos')
            ->where('ronda_id', $rondaId)
            ->where('estado', '!=', 'anulado')
            ->orderByDesc('id')
            ->first();

        if (! $sorteo) {
            return null;
        }

        $detalles = DB::table('catalogo.sorteo_detalles as sd')
            ->join('vinculaciones.inscripciones as i', 'i.id', '=', 'sd.inscripcion_id')
            ->leftJoin('catalogo.equipos as e', 'e.id', '=', 'i.equipo_id')
            ->where('sd.sorteo_id', $sorteo->id)
            ->where('i.estado', 'confirmado')
            ->where('i.estado_comprobante', 'aprobado')
            ->select([
                'sd.id',
                'sd.inscripcion_id',
                'sd.orden',
                'sd.grupo',
                'sd.lado',
                'sd.estado',
                'i.equipo_id',
                'i.nombre_prototipo',
                'e.nombre as equipo_nombre',
                'e.institucion',
            ])
            ->orderBy('sd.orden')
            ->get()
            ->map(fn ($detalle) => [
                'id' => (int) $detalle->id,
                'inscripcion_id' => (int) $detalle->inscripcion_id,
                'equipo_id' => (int) $detalle->equipo_id,
                'equipo_nombre' => (string) ($detalle->equipo_nombre ?? ''),
                'institucion' => (string) ($detalle->institucion ?? ''),
                'nombre_prototipo' => $detalle->nombre_prototipo,
                'orden' => (int) $detalle->orden,
                'grupo' => $detalle->grupo !== null ? (int) $detalle->grupo : null,
                'lado' => $detalle->lado,
                'estado' => (string) $detalle->estado,
            ])
            ->values()
            ->all();

        return [
            'id' => (int) $sorteo->id,
            'ronda_id' => (int) $sorteo->ronda_id,
            'tipo_sorteo' => (string) $sorteo->tipo_sorteo,
            'estado' => (string) $sorteo->estado,
            'detalles' => $detalles,
        ];
    }
}
