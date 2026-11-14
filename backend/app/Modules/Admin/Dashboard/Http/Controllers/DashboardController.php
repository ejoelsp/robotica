<?php

namespace App\Modules\Admin\Dashboard\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Services\DashboardAdminService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(private readonly DashboardAdminService $service) {}

    public function index(Request $request): Response
    {
        $data = $request->validate([
            'competencia_id' => ['nullable', 'integer', 'min:1'],
            'rondas_page' => ['nullable', 'integer', 'min:1', 'max:100000'],
        ], [
            'competencia_id.integer' => 'Selecciona una competencia válida.',
            'competencia_id.min' => 'Selecciona una competencia válida.',
            'rondas_page.integer' => 'La página de rondas debe ser un número entero.',
            'rondas_page.min' => 'La página de rondas debe ser mayor o igual que 1.',
            'rondas_page.max' => 'La página de rondas solicitada no es válida.',
        ]);
        $competencias = $this->service->competencias();
        $competencia = isset($data['competencia_id'])
            ? $competencias->firstWhere('id', (int) $data['competencia_id'])
            : $competencias->first();
        abort_if(isset($data['competencia_id']) && ! $competencia, 404, 'La competencia seleccionada no existe.');

        return Inertia::render('Admin/Dashboard', [
            'competencias' => $competencias,
            'competencia' => $competencia,
            'dashboard' => $this->service->obtenerPanel($competencia, (int) $request->user()->id, (int) ($data['rondas_page'] ?? 1)),
        ]);
    }
}
