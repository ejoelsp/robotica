<?php

namespace App\Modules\Admin\AnalisisHistorico\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Services\AnalisisHistoricoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AnalisisHistoricoController extends Controller
{
    public function __construct(private readonly AnalisisHistoricoService $service)
    {
    }

    public function index(Request $request): Response
    {
        $temporadas = $this->service->temporadas();
        $temporadaId = (int) (
            $request->integer('temporada_id')
            ?: ($temporadas->firstWhere('tiene_cierre', true)['id'] ?? null)
            ?: ($temporadas->first()['id'] ?? 0)
        );

        $competenciaId = $request->integer('competencia_id') ?: null;
        $competencias = $temporadaId > 0 ? $this->service->competencias($temporadaId) : collect();
        $competenciaSeleccionada = $competenciaId
            ? $competencias->firstWhere('id', $competenciaId)
            : null;

        if ($competenciaId && ! $competenciaSeleccionada) {
            $competenciaId = null;
        }

        $temporadaCerrable = $temporadaId > 0 && $this->service->temporadaCerrable($temporadaId);
        $competenciaCerrable = $competenciaId ? $this->service->competenciaCerrable($competenciaId) : false;
        $cierreTemporada = $temporadaCerrable
            ? $this->service->serializar($this->service->obtenerCierreTemporada($temporadaId, $request->user()))
            : null;
        $cierreCompetencia = $competenciaCerrable
            ? $this->service->serializar($this->service->obtenerCierreCompetencia($competenciaId, $request->user()))
            : null;

        return Inertia::render('Admin/AnalisisHistorico', [
            'temporadaId' => $temporadaId ?: null,
            'competenciaId' => $competenciaId,
            'temporadas' => $temporadas,
            'competencias' => $competencias,
            'cierreTemporada' => $cierreTemporada,
            'cierreCompetencia' => $cierreCompetencia,
            'preliminarTemporada' => $cierreTemporada ? null : ($temporadaId > 0 ? $this->service->obtenerPreliminarTemporada($temporadaId) : null),
            'preliminarCompetencia' => $cierreCompetencia ? null : ($competenciaId ? $this->service->obtenerPreliminarCompetencia($competenciaId) : null),
        ]);
    }

    public function generar(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'tipo_cierre' => ['required', 'in:temporada,competencia'],
            'temporada_id' => ['required', 'integer', 'min:1'],
            'competencia_id' => ['nullable', 'integer', 'min:1'],
        ], [
            'tipo_cierre.required' => 'Selecciona el tipo de cierre.',
            'temporada_id.required' => 'Selecciona una temporada.',
        ]);

        $tipo = (string) $validated['tipo_cierre'];
        $cierre = $this->service->generarCierre(
            $tipo,
            (int) $validated['temporada_id'],
            $tipo === 'competencia' ? (int) ($validated['competencia_id'] ?? 0) : null,
            $request->user()
        );

        return response()->json([
            'message' => $tipo === 'temporada'
                ? 'Cierre de temporada generado correctamente.'
                : 'Cierre de competencia generado correctamente.',
            'cierre' => $this->service->serializar($cierre),
            'temporadas' => $this->service->temporadas(),
            'competencias' => $this->service->competencias((int) $validated['temporada_id']),
        ]);
    }
}
