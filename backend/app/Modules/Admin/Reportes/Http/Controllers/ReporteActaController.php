<?php

namespace App\Modules\Admin\Reportes\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\ActaReporte;
use App\Services\ReporteActaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ReporteActaController extends Controller
{
    public function __construct(private readonly ReporteActaService $service)
    {
    }

    public function index(Request $request): Response
    {
        $competenciaId = (int) (
            $request->integer('competencia_id')
            ?: DB::table('catalogo.competencias')->where('estado', true)->orderByDesc('id')->value('id')
            ?: DB::table('catalogo.competencias')->orderByDesc('id')->value('id')
        );

        return Inertia::render('Admin/Reportes', [
            'competenciaId' => $competenciaId,
            'competencias' => $this->competencias(),
            'categorias' => $this->categorias($competenciaId),
            'tiposReporte' => collect($this->service->tiposReporte())
                ->map(fn (string $label, string $value) => [
                    'value' => $value,
                    'label' => $label,
                ])
                ->values(),
            'reportes' => $this->reportes($competenciaId),
        ]);
    }

    public function listado(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'competencia_id' => ['required', 'integer', 'min:1'],
        ]);

        return response()->json([
            'reportes' => $this->reportes((int) $validated['competencia_id']),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'competencia_id' => ['required', 'integer', 'min:1'],
            'categoria_id' => ['required', 'integer', 'min:1'],
            'ronda_id' => ['nullable', 'integer', 'min:1'],
            'tipo_reporte' => ['required', Rule::in(array_keys($this->service->tiposReporte()))],
            'observaciones' => ['nullable', 'string', 'max:1000'],
        ], [
            'competencia_id.required' => 'Selecciona una competencia.',
            'categoria_id.required' => 'Selecciona una categoría.',
            'tipo_reporte.required' => 'Selecciona el tipo de reporte.',
        ]);

        $acta = $this->service->generar($validated, $request->user());

        return response()->json([
            'message' => 'Reporte generado correctamente.',
            'reporte' => $this->service->serializar($acta->load([
                'competencia:id,nombre',
                'categoria:id,nombre',
                'ronda:id,nombre',
                'generadoPor:id,name,last_name,email',
                'archivoFirmadoSubidoPor:id,name,last_name,email',
            ])),
        ], 201);
    }

    public function subirFirmado(Request $request, ActaReporte $acta): JsonResponse
    {
        $validated = $request->validate([
            'archivo_firmado' => ['required', 'file', 'mimes:pdf', 'max:10240'],
        ], [
            'archivo_firmado.required' => 'Selecciona el PDF firmado.',
            'archivo_firmado.mimes' => 'El archivo firmado debe ser un PDF.',
            'archivo_firmado.max' => 'El PDF firmado no debe superar los 10 MB.',
        ]);

        if ($acta->archivo_firmado_path) {
            Storage::disk('public')->delete($acta->archivo_firmado_path);
        }

        $path = $validated['archivo_firmado']->store(
            sprintf('reportes/actas-firmadas/%s/%s', $acta->competencia_id, $acta->categoria_id),
            'public'
        );

        $acta->forceFill([
            'estado' => 'firmado',
            'archivo_firmado_path' => $path,
            'archivo_firmado_subido_por' => $request->user()->id,
            'archivo_firmado_subido_at' => now(),
        ])->save();

        return response()->json([
            'message' => 'Acta firmada cargada correctamente.',
            'reporte' => $this->service->serializar($acta->fresh([
                'competencia:id,nombre',
                'categoria:id,nombre',
                'ronda:id,nombre',
                'generadoPor:id,name,last_name,email',
                'archivoFirmadoSubidoPor:id,name,last_name,email',
            ])),
        ]);
    }

    public function downloadGenerado(ActaReporte $acta)
    {
        abort_unless(Storage::disk('public')->exists($acta->archivo_generado_path), 404);

        return Storage::disk('public')->download(
            $acta->archivo_generado_path,
            $this->nombreDescarga($acta, 'generado')
        );
    }

    public function downloadFirmado(ActaReporte $acta)
    {
        abort_unless($acta->archivo_firmado_path && Storage::disk('public')->exists($acta->archivo_firmado_path), 404);

        return Storage::disk('public')->download(
            $acta->archivo_firmado_path,
            $this->nombreDescarga($acta, 'firmado')
        );
    }

    private function competencias()
    {
        return DB::table('catalogo.competencias')
            ->select('id', 'nombre', 'estado')
            ->orderByDesc('estado')
            ->orderBy('nombre')
            ->get()
            ->map(fn ($item) => [
                'id' => (int) $item->id,
                'nombre' => (string) $item->nombre,
                'estado' => (bool) $item->estado,
            ]);
    }

    private function categorias(int $competenciaId)
    {
        return DB::table('catalogo.categorias as c')
            ->leftJoin('catalogo.rondas as r', 'r.categoria_id', '=', 'c.id')
            ->where('c.competencia_id', $competenciaId)
            ->select('c.id', 'c.nombre', 'c.estado', 'r.id as ronda_id', 'r.nombre as ronda_nombre', 'r.orden as ronda_orden', 'r.es_final')
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
                    'rondas' => collect($rows)
                        ->filter(fn ($row) => $row->ronda_id)
                        ->map(fn ($row) => [
                            'id' => (int) $row->ronda_id,
                            'nombre' => (string) $row->ronda_nombre,
                            'orden' => (int) ($row->ronda_orden ?? 1),
                            'es_final' => (bool) $row->es_final,
                        ])
                        ->values()
                        ->all(),
                ];
            })
            ->values();
    }

    private function reportes(int $competenciaId)
    {
        return ActaReporte::query()
            ->with([
                'competencia:id,nombre',
                'categoria:id,nombre',
                'ronda:id,nombre',
                'generadoPor:id,name,last_name,email',
                'archivoFirmadoSubidoPor:id,name,last_name,email',
            ])
            ->where('competencia_id', $competenciaId)
            ->orderByDesc('generado_at')
            ->orderByDesc('id')
            ->limit(80)
            ->get()
            ->map(fn (ActaReporte $acta) => $this->service->serializar($acta))
            ->values();
    }

    private function nombreDescarga(ActaReporte $acta, string $estado): string
    {
        $tipo = $this->service->tiposReporte()[$acta->tipo_reporte] ?? $acta->tipo_reporte;

        return str($tipo . '-' . ($acta->categoria?->nombre ?? 'categoria') . '-' . $estado)
            ->ascii()
            ->slug('-')
            ->append('.pdf')
            ->toString();
    }
}
