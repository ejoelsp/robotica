<?php

namespace App\Modules\Admin\Certificados\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Inscripcion;
use App\Models\PlantillaCertificado;
use App\Services\CertificadoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CertificadoController extends Controller
{
    private const CERTIFICADOS_TABS = ['plantillas', 'manual'];

    public function __construct(
        private readonly CertificadoService $service
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
            ->select('id', 'nombre', 'fecha_inicio', 'estado')
            ->orderByDesc('estado')
            ->orderByDesc('fecha_inicio')
            ->orderBy('nombre')
            ->get()
            ->map(fn ($competencia) => [
                'id' => (int) $competencia->id,
                'nombre' => (string) $competencia->nombre,
                'anio' => $competencia->fecha_inicio ? (int) date('Y', strtotime($competencia->fecha_inicio)) : null,
                'estado' => (bool) $competencia->estado,
            ]);

        $plantillas = PlantillaCertificado::query()
            ->with('competencia:id,nombre,fecha_inicio')
            ->where('competencia_id', $competenciaId)
            ->orderBy('tipo_certificado')
            ->orderByDesc('activo')
            ->orderByDesc('id')
            ->get()
            ->map(fn (PlantillaCertificado $plantilla) => [
                'id' => (int) $plantilla->id,
                'competencia_id' => (int) $plantilla->competencia_id,
                'anio' => $plantilla->anio,
                'tipo_certificado' => (string) $plantilla->tipo_certificado,
                'tipo_label' => $this->service->tiposCertificados()[$plantilla->tipo_certificado] ?? $plantilla->tipo_certificado,
                'archivo_url' => Storage::disk('public')->url($plantilla->archivo_plantilla),
                'configuracion_textos' => $plantilla->configuracion_textos ?: $this->service->configuracionDefault(),
                'activo' => (bool) $plantilla->activo,
                'created_at' => optional($plantilla->created_at)?->format('d/m/Y H:i'),
                'delete_url' => route('admin.certificados.destroy', $plantilla),
            ]);

        $emergenciaInscripciones = Inscripcion::query()
            ->with([
                'categoria:id,nombre',
                'equipo:id,nombre,institucion',
                'integrantes:id,inscripcion_id,nombre_completo,user_id,es_capitan',
            ])
            ->where('competencia_id', $competenciaId)
            ->aprobadas()
            ->orderByDesc('id')
            ->get();

        $emergenciaCategorias = $emergenciaInscripciones
            ->groupBy('categoria_id')
            ->map(function ($inscripciones, $categoriaId) {
                $primera = $inscripciones->first();

                return [
                    'id' => (int) $categoriaId,
                    'nombre' => (string) ($primera?->categoria?->nombre ?? 'Categoría'),
                    'equipos_count' => $inscripciones->count(),
                    'integrantes_count' => $inscripciones->sum(
                        fn (Inscripcion $inscripcion) => $inscripcion->integrantes->count()
                    ),
                ];
            })
            ->sortBy('nombre')
            ->values();

        $emergenciaParticipantes = $emergenciaInscripciones
            ->flatMap(function (Inscripcion $inscripcion) {
                return $inscripcion->integrantes
                    ->sortByDesc(fn ($integrante) => (bool) $integrante->es_capitan)
                    ->map(function ($integrante) use ($inscripcion) {
                        return [
                            'integrante_id' => (int) $integrante->id,
                            'categoria_id' => (int) $inscripcion->categoria_id,
                            'categoria' => (string) ($inscripcion->categoria?->nombre ?? 'Categoría'),
                            'participante' => (string) $integrante->nombre_completo,
                            'equipo' => (string) ($inscripcion->equipo?->nombre ?? 'Sin equipo'),
                            'institucion' => (string) ($inscripcion->equipo?->institucion ?? ''),
                            'prototipo' => (string) ($inscripcion->nombre_prototipo ?? 'Sin prototipo'),
                            'es_capitan' => (bool) $integrante->es_capitan,
                        ];
                    });
            })
            ->sortBy([
                ['categoria', 'asc'],
                ['equipo', 'asc'],
                ['participante', 'asc'],
            ])
            ->values();

        $activeTab = $request->query('tab', 'plantillas');
        if (! in_array($activeTab, self::CERTIFICADOS_TABS, true)) {
            $activeTab = 'plantillas';
        }

        return Inertia::render('Admin/Certificados', [
            'competenciaId' => $competenciaId,
            'activeTab' => $activeTab,
            'competencias' => $competencias,
            'tiposCertificados' => $this->service->tiposCertificados(),
            'configuracionDefault' => $this->service->configuracionDefault(),
            'plantillas' => $plantillas,
            'emergenciaCategorias' => $emergenciaCategorias,
            'emergenciaParticipantes' => $emergenciaParticipantes,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'competencia_id' => ['required', 'integer', 'min:1'],
            'anio' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'tipo_certificado' => ['required', Rule::in(PlantillaCertificado::TIPOS)],
            'archivo_plantilla' => ['required', 'file', 'mimes:jpg,jpeg,png', 'max:8192'],
            'configuracion_textos' => ['nullable', 'json'],
            'activo' => ['nullable', 'boolean'],
        ]);

        $competenciaExiste = DB::table('catalogo.competencias')
            ->where('id', (int) $validated['competencia_id'])
            ->exists();

        if (! $competenciaExiste) {
            return back()->withErrors([
                'competencia_id' => 'La competencia seleccionada no existe.',
            ]);
        }

        $configuracion = $validated['configuracion_textos']
            ? json_decode($validated['configuracion_textos'], true)
            : $this->service->configuracionDefault();

        if (! is_array($configuracion)) {
            return back()->withErrors([
                'configuracion_textos' => 'La configuracion debe ser un JSON valido.',
            ]);
        }

        $activo = (bool) ($validated['activo'] ?? true);

        if ($activo) {
            PlantillaCertificado::query()
                ->where('competencia_id', (int) $validated['competencia_id'])
                ->where('tipo_certificado', (string) $validated['tipo_certificado'])
                ->where(function ($query) use ($validated) {
                    if (isset($validated['anio'])) {
                        $query->where('anio', (int) $validated['anio']);
                    } else {
                        $query->whereNull('anio');
                    }
                })
                ->update(['activo' => false]);
        }

        PlantillaCertificado::query()->create([
            'competencia_id' => (int) $validated['competencia_id'],
            'anio' => isset($validated['anio']) ? (int) $validated['anio'] : null,
            'tipo_certificado' => (string) $validated['tipo_certificado'],
            'archivo_plantilla' => $request->file('archivo_plantilla')->store('certificados/plantillas', 'public'),
            'configuracion_textos' => $configuracion,
            'activo' => $activo,
            'creado_por' => $request->user()->id,
        ]);

        return redirect()
            ->route('admin.certificados.index', ['competencia_id' => $validated['competencia_id']])
            ->with('success', 'Plantilla de certificado guardada correctamente.');
    }

    public function update(Request $request, PlantillaCertificado $plantilla)
    {
        $validated = $request->validate([
            'competencia_id' => ['required', 'integer', 'min:1'],
            'anio' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'tipo_certificado' => ['required', Rule::in(PlantillaCertificado::TIPOS)],
            'archivo_plantilla' => ['nullable', 'file', 'mimes:jpg,jpeg,png', 'max:8192'],
            'configuracion_textos' => ['nullable', 'json'],
            'activo' => ['nullable', 'boolean'],
        ]);

        $competenciaExiste = DB::table('catalogo.competencias')
            ->where('id', (int) $validated['competencia_id'])
            ->exists();

        if (! $competenciaExiste) {
            return back()->withErrors([
                'competencia_id' => 'La competencia seleccionada no existe.',
            ]);
        }

        $configuracion = $validated['configuracion_textos']
            ? json_decode($validated['configuracion_textos'], true)
            : ($plantilla->configuracion_textos ?: $this->service->configuracionDefault());

        if (! is_array($configuracion)) {
            return back()->withErrors([
                'configuracion_textos' => 'La configuración debe ser válida.',
            ]);
        }

        $activo = (bool) ($validated['activo'] ?? false);

        if ($activo) {
            PlantillaCertificado::query()
                ->where('competencia_id', (int) $validated['competencia_id'])
                ->where('tipo_certificado', (string) $validated['tipo_certificado'])
                ->where(function ($query) use ($validated) {
                    if (isset($validated['anio'])) {
                        $query->where('anio', (int) $validated['anio']);
                    } else {
                        $query->whereNull('anio');
                    }
                })
                ->whereKeyNot($plantilla->id)
                ->update(['activo' => false]);
        }

        $data = [
            'competencia_id' => (int) $validated['competencia_id'],
            'anio' => isset($validated['anio']) ? (int) $validated['anio'] : null,
            'tipo_certificado' => (string) $validated['tipo_certificado'],
            'configuracion_textos' => $configuracion,
            'activo' => $activo,
        ];

        if ($request->hasFile('archivo_plantilla')) {
            $data['archivo_plantilla'] = $request->file('archivo_plantilla')->store('certificados/plantillas', 'public');
        }

        $plantilla->update($data);

        return redirect()
            ->route('admin.certificados.index', ['competencia_id' => $validated['competencia_id']])
            ->with('success', 'Plantilla de certificado actualizada correctamente.');
    }

    public function destroy(PlantillaCertificado $plantilla)
    {
        DB::table('resultados.certificados_generados')
            ->where('plantilla_certificado_id', $plantilla->id)
            ->delete();

        if ($plantilla->archivo_plantilla && Storage::disk('public')->exists($plantilla->archivo_plantilla)) {
            Storage::disk('public')->delete($plantilla->archivo_plantilla);
        }

        $competenciaId = $plantilla->competencia_id;
        $plantilla->delete();

        return redirect()
            ->route('admin.certificados.index', ['competencia_id' => $competenciaId])
            ->with('success', 'Plantilla de certificado eliminada correctamente.');
    }

    public function preview(PlantillaCertificado $plantilla)
    {
        $plantilla->load('competencia:id,nombre');
        $pdf = $this->service->construirPdfDesdePlantilla($plantilla);

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="preview-certificado.pdf"',
        ]);
    }

    public function downloadManual(Request $request)
    {
        $validated = $request->validate([
            'competencia_id' => ['required', 'integer', 'min:1'],
            'integrante_id' => ['required', 'integer', 'min:1'],
            'tipo_certificado' => ['required', Rule::in(PlantillaCertificado::TIPOS)],
        ]);

        $perteneceACompetencia = Inscripcion::query()
            ->where('competencia_id', (int) $validated['competencia_id'])
            ->whereHas('integrantes', fn ($query) => $query->where('id', (int) $validated['integrante_id']))
            ->aprobadas()
            ->exists();

        if (! $perteneceACompetencia) {
            return redirect()
                ->route('admin.certificados.index', [
                    'competencia_id' => (int) $validated['competencia_id'],
                    'tab' => 'manual',
                ])
                ->withErrors([
                    'integrante_id' => 'El integrante seleccionado no pertenece a una inscripción aprobada de esta competencia.',
                ]);
        }

        $certificado = $this->service->generarParaIntegranteComoAdminPorTipo(
            (int) $validated['integrante_id'],
            (string) $validated['tipo_certificado']
        );

        abort_unless(Storage::disk('public')->exists($certificado->archivo_pdf), 404);

        $nombre = 'certificado-' . $certificado->id . '.pdf';

        return Storage::disk('public')->download($certificado->archivo_pdf, $nombre, [
            'Content-Type' => 'application/pdf',
        ]);
    }
}
