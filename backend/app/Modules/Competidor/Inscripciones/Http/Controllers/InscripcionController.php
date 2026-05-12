<?php

namespace App\Modules\Competidor\Inscripciones\Http\Controllers;

use Inertia\Inertia;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use App\Models\Competencia;
use App\Models\Categoria;
use App\Modules\Competidor\Inscripciones\Requests\StoreInscripcionRequest;
use App\Modules\Competidor\Inscripciones\Services\InscripcionService;
use Illuminate\Support\Facades\Storage;
use App\Models\Inscripcion;
use App\Modules\Competidor\Inscripciones\Requests\StoreComprobanteRequest;

class InscripcionController extends Controller
{
    public function __construct(
        protected InscripcionService $service
    ) {}

    public function index()
    {
        $competencias = Competencia::query()
            ->where('estado', true)
            ->orderBy('fecha_inicio')
            ->get([
                'id',
                'nombre',
                'fecha_inicio',
                'fecha_fin',
            ]);

        $categorias = Categoria::query()
            ->where('estado', true)
            ->whereHas('competencia', function ($q) {
                $q->where('estado', true);
            })
            ->with('competencia:id,nombre,estado')
            ->orderBy('competencia_id')
            ->orderBy('nombre')
            ->get([
                'id',
                'competencia_id',
                'nombre',
                'costo_inscripcion',
                'reglamento',
                'imagen',
            ])
            ->map(function ($categoria) {
                return [
                    'id' => $categoria->id,
                    'nombre' => $categoria->nombre,
                    'costo_inscripcion' => (float) ($categoria->costo_inscripcion ?? 0),
                    'descripcion_corta' => 'Sin subcategorías',
                    'descripcion' => 'Consulta el reglamento y completa tu inscripción para participar en esta categoría.',
                    'reglamento_url' => $categoria->reglamento
                        ? asset('storage/' . $categoria->reglamento)
                        : null,
                    'imagen_url' => $categoria->imagen
                        ? asset('storage/' . $categoria->imagen)
                        : null,
                    'competencia_id' => $categoria->competencia_id,
                    'competencia_nombre' => $categoria->competencia?->nombre,
                ];
            })
            ->values();

        $inscripcionesActivas = Inscripcion::query()
            ->with([
                'categoria:id,nombre,costo_inscripcion',
                'competencia:id,nombre,fecha_inicio',
                'equipo:id,nombre',
                'integrantes:id,inscripcion_id,nombre_completo,user_id,es_capitan',
            ])
            ->where('user_id', auth()->id())
            ->orderByDesc('id')
            ->get()
            ->map(function ($inscripcion) {
                $estado = strtolower((string) $inscripcion->estado);
                $estadoComprobante = strtolower((string) ($inscripcion->estado_comprobante ?? 'no_subido'));
                $integrantes = $inscripcion->integrantes
                    ?->sortByDesc(fn ($integrante) => (bool) $integrante->es_capitan)
                    ->values()
                    ->map(fn ($integrante) => [
                        'nombre' => (string) $integrante->nombre_completo,
                        'es_capitan' => (bool) $integrante->es_capitan,
                    ])
                    ->all() ?? [];

                return [
                    'id' => $inscripcion->id,
                    'categoria' => $inscripcion->categoria?->nombre ?? 'Categoría',

                    'costo_inscripcion' => (float) ($inscripcion->categoria?->costo_inscripcion ?? 0),
                    'estado' => match ($estado) {
                        'confirmada', 'confirmado' => 'confirmado',
                        'revision', 'en_revision', 'en revisión' => 'revision',
                        'pendiente_pago', 'pendiente de pago', 'pendiente' => 'pendiente_pago',
                        default => 'pendiente_pago',
                    },

                    'estadoLabel' => match ($estado) {
                        'confirmada', 'confirmado' => 'Confirmado',
                        'revision', 'en_revision', 'en revisión' => 'En Revisión',
                        'pendiente_pago', 'pendiente de pago', 'pendiente' => 'Pendiente de Pago',
                        default => 'Pendiente de Pago',
                    },

                    'estado_comprobante' => match ($estadoComprobante) {
                        'aprobado' => 'aprobado',
                        'rechazado' => 'rechazado',
                        'revision' => 'revision',
                        default => 'no_subido',
                    },

                    'estadoComprobanteLabel' => match ($estadoComprobante) {
                        'aprobado' => 'Aprobado',
                        'rechazado' => 'Rechazado',
                        'revision' => 'En revisión',
                        default => 'No subido',
                    },

                    'motivo_rechazo' => $inscripcion->motivo_rechazo,
                    'observacion_rechazo' => $inscripcion->observacion_rechazo,

                    'equipo' => $inscripcion->equipo?->nombre ?? 'Sin equipo',
                    'fechaInscripcion' => optional($inscripcion->created_at)?->format('d M Y'),
                    'competencia' => $inscripcion->competencia?->nombre ?? 'Sin competencia',
                    'fechaCompetencia' => optional($inscripcion->competencia?->fecha_inicio)?->format('d M Y'),
                    'integrantes' => $inscripcion->integrantes?->count() ?? 0,
                    'integrantes_nombres' => $integrantes,
                    'prototipo' => $inscripcion->nombre_prototipo,

                    'mostrarPago' => in_array($estado, [
                        'pendiente',
                        'pendiente_pago',
                        'pendiente de pago',
                    ]) || $estadoComprobante === 'rechazado',
                ];
            })
            ->values();

        return Inertia::render('Competidor/MisInscripciones', [
            'competencias' => $competencias,
            'categoriasDisponibles' => $categorias,
            'inscripcionesActivas' => $inscripcionesActivas,
        ]);
    }

    public function store(StoreInscripcionRequest $request)
    {
        $this->service->registrar(
            Auth::user(),
            $request->validated()
        );

        return redirect()
            ->back()
            ->with('success', 'Inscripción registrada correctamente.');
    }

    public function storeComprobante(StoreComprobanteRequest $request)
    {
        $inscripcionIds = collect($request->input('inscripcion_ids', []))
            ->when($request->filled('inscripcion_id'), fn ($ids) => $ids->push((int) $request->inscripcion_id))
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $inscripciones = Inscripcion::query()
            ->whereIn('id', $inscripcionIds)
            ->where('user_id', auth()->id())
            ->get();

        abort_unless($inscripciones->count() === $inscripcionIds->count(), 404);

        $rutaArchivo = $request->file('comprobante')->store('comprobantes', 'public');
        $fechaSubida = now();

        foreach ($inscripciones as $inscripcion) {
            if ($inscripcion->comprobante_pago && Storage::disk('public')->exists($inscripcion->comprobante_pago)) {
                Storage::disk('public')->delete($inscripcion->comprobante_pago);
            }

            $inscripcion->update([
                'comprobante_pago' => $rutaArchivo,
                'fecha_subida_comprobante' => $fechaSubida,
                'estado' => 'revision',
                'estado_comprobante' => 'revision',
                'motivo_rechazo' => null,
                'observacion_rechazo' => null,
                'fecha_revision_comprobante' => null,
                'revisado_por' => null,
            ]);
        }

        return redirect()
            ->back()
            ->with('success', 'Comprobante subido correctamente.');
    }
}
