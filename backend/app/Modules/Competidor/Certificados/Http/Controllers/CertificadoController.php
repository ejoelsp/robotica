<?php

namespace App\Modules\Competidor\Certificados\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Services\CertificadoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class CertificadoController extends Controller
{
    public function __construct(
        private readonly CertificadoService $service
    ) {
    }

    public function index(Request $request): Response
    {
        return Inertia::render('Competidor/Certificados', [
            'certificadosCompetidor' => $this->service->obtenerCertificadosCompetidor($request->user()->id),
        ]);
    }

    public function download(Request $request, int $integrante)
    {
        $certificado = $this->service->generarParaIntegrante($integrante, $request->user()->id);

        abort_unless(Storage::disk('public')->exists($certificado->archivo_pdf), 404);

        $nombre = 'certificado-' . $certificado->id . '.pdf';

        return Storage::disk('public')->download($certificado->archivo_pdf, $nombre, [
            'Content-Type' => 'application/pdf',
        ]);
    }
}
