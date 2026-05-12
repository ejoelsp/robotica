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
            ->whereIn('estado_publicacion', ['visible', 'cerrado'])
            ->pluck('competencia_id')
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
                ->where('competencia_id', $competenciaId)
                ->whereIn('estado_publicacion', ['visible', 'cerrado'])
                ->pluck('categoria_id')
                ->unique()
                ->values();

            $categorias = DB::table('catalogo.categorias as c')
                ->leftJoin('catalogo.rondas as r', 'r.categoria_id', '=', 'c.id')
                ->whereIn('c.id', $categoriaIds)
                ->select('c.id', 'c.nombre', 'r.id as ronda_id', 'r.nombre as ronda_nombre', 'r.fecha_hora')
                ->orderBy('c.nombre')
                ->orderBy('r.fecha_hora')
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
                                'fecha_hora' => $row->fecha_hora,
                            ])
                            ->values()
                            ->all(),
                    ];
                })
                ->values();

            try {
                $vista = $this->service->obtenerVistaPublica(
                    $competenciaId,
                    $request->integer('categoria_id') ?: null,
                    $request->integer('ronda_id') ?: null,
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
}
