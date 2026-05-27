<?php

namespace App\Modules\Competidor\Resultados\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Services\ClasificacionConsolidacionService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ResultadoController extends Controller
{
    public function __construct(
        private readonly ClasificacionConsolidacionService $service
    ) {
    }

    public function index(Request $request): Response
    {
        return Inertia::render('Competidor/Resultados', [
            'resultadosCompetidor' => $this->service->obtenerResultadosCompetidor($request->user()),
        ]);
    }
}
