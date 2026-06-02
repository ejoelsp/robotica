<?php

namespace App\Modules\Competidor\Inscripciones\Http\Controllers;

use Inertia\Inertia;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use App\Models\ConfiguracionPago;
use App\Models\Competencia;
use App\Models\Categoria;
use App\Models\Equipo;
use App\Modules\Competidor\Inscripciones\Requests\StoreInscripcionRequest;
use App\Modules\Competidor\Inscripciones\Services\InscripcionService;
use Illuminate\Support\Facades\Storage;
use App\Models\Inscripcion;
use App\Modules\Competidor\Inscripciones\Requests\StoreComprobanteRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

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
                'max_integrantes',
                'reglamento',
                'imagen',
            ])
            ->map(function ($categoria) {
                return [
                    'id' => $categoria->id,
                    'nombre' => $categoria->nombre,
                    'costo_inscripcion' => (float) ($categoria->costo_inscripcion ?? 0),
                    'max_integrantes' => (int) ($categoria->max_integrantes ?? 2),
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
                'categoria:id,nombre,costo_inscripcion,max_integrantes',
                'competencia:id,nombre,fecha_inicio',
                'equipo:id,nombre,institucion',
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
                    'categoria_id' => $inscripcion->categoria_id,
                    'competencia_id' => $inscripcion->competencia_id,
                    'categoria' => $inscripcion->categoria?->nombre ?? 'Categoría',
                    'max_integrantes' => (int) ($inscripcion->categoria?->max_integrantes ?? 2),

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
                    'institucion' => $inscripcion->equipo?->institucion ?? '',
                    'fechaInscripcion' => optional($inscripcion->created_at)?->format('d M Y'),
                    'competencia' => $inscripcion->competencia?->nombre ?? 'Sin competencia',
                    'fechaCompetencia' => optional($inscripcion->competencia?->fecha_inicio)?->format('d M Y'),
                    'integrantes' => $inscripcion->integrantes?->count() ?? 0,
                    'integrantes_nombres' => $integrantes,
                    'prototipo' => $inscripcion->nombre_prototipo,
                    'telefono_contacto' => $inscripcion->telefono_contacto,

                    'mostrarPago' => in_array($estado, [
                        'pendiente',
                        'pendiente_pago',
                        'pendiente de pago',
                    ]) || $estadoComprobante === 'rechazado',

                    'puedeEliminar' => $estado !== 'confirmado'
                        && !in_array($estadoComprobante, ['aprobado', 'revision'], true),

                    'puedeEditar' => in_array($estado, [
                        'pendiente',
                        'pendiente_pago',
                        'pendiente de pago',
                    ], true) && in_array($estadoComprobante, ['no_subido', 'rechazado'], true),
                ];
            })
            ->values();

        return Inertia::render('Competidor/MisInscripciones', [
            'competencias' => $competencias,
            'categoriasDisponibles' => $categorias,
            'inscripcionesActivas' => $inscripcionesActivas,
            'configuracionPago' => $this->configuracionPagoActiva(),
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

    public function update(StoreInscripcionRequest $request, int $id)
    {
        $inscripcion = Inscripcion::query()
            ->with(['integrantes', 'equipo'])
            ->where('user_id', auth()->id())
            ->findOrFail($id);

        $estado = strtolower((string) $inscripcion->estado);
        $estadoComprobante = strtolower((string) ($inscripcion->estado_comprobante ?? 'no_subido'));

        if (
            !in_array($estado, ['pendiente', 'pendiente_pago', 'pendiente de pago'], true)
            || !in_array($estadoComprobante, ['no_subido', 'rechazado'], true)
        ) {
            throw ValidationException::withMessages([
                'inscripcion' => 'Solo puedes editar la inscripción antes de enviar el comprobante de pago.',
            ]);
        }

        $data = $request->validated();

        if (
            (int) $data['competencia_id'] !== (int) $inscripcion->competencia_id
            || (int) $data['categoria_id'] !== (int) $inscripcion->categoria_id
        ) {
            throw ValidationException::withMessages([
                'categoria_id' => 'No puedes cambiar la categoría ni la competencia de una inscripción existente.',
            ]);
        }

        DB::transaction(function () use ($inscripcion, $data, $estadoComprobante) {
            $equipo = Equipo::query()->firstOrCreate(
                [
                    'nombre' => $data['nombre_equipo'],
                    'institucion' => $data['institucion'],
                ],
                [
                    'nombre' => $data['nombre_equipo'],
                    'institucion' => $data['institucion'],
                    'capitan_user_id' => null,
                ]
            );

            $existeInscripcion = Inscripcion::query()
                ->where('categoria_id', $inscripcion->categoria_id)
                ->where('equipo_id', $equipo->id)
                ->where('nombre_prototipo', $data['nombre_prototipo'])
                ->where('id', '!=', $inscripcion->id)
                ->exists();

            if ($existeInscripcion) {
                throw ValidationException::withMessages([
                    'nombre_prototipo' => 'Ya existe una inscripción para este mismo equipo con este mismo prototipo en la categoría seleccionada.',
                ]);
            }

            $updates = [
                'equipo_id' => $equipo->id,
                'nombre_prototipo' => $data['nombre_prototipo'],
                'telefono_contacto' => $data['telefono_contacto'],
            ];

            if ($estadoComprobante === 'rechazado') {
                $comprobante = $inscripcion->comprobante_pago;
                $existeOtroUso = $comprobante
                    ? Inscripcion::query()
                        ->where('id', '!=', $inscripcion->id)
                        ->where('comprobante_pago', $comprobante)
                        ->exists()
                    : false;

                if ($comprobante && ! $existeOtroUso && Storage::disk('public')->exists($comprobante)) {
                    Storage::disk('public')->delete($inscripcion->comprobante_pago);
                }

                $updates = array_merge($updates, [
                    'estado' => 'pendiente_pago',
                    'estado_comprobante' => 'no_subido',
                    'comprobante_pago' => null,
                    'fecha_subida_comprobante' => null,
                    'motivo_rechazo' => null,
                    'observacion_rechazo' => null,
                    'fecha_revision_comprobante' => null,
                    'revisado_por' => null,
                ]);
            }

            $inscripcion->update($updates);
            $inscripcion->integrantes()->delete();

            $nombreCapitan = trim((string) $data['nombre_capitan']);
            $integrantes = collect($data['integrantes'])
                ->map(fn ($nombre) => trim((string) $nombre))
                ->filter()
                ->unique()
                ->values();

            $nombreCapitanNormalizado = mb_strtolower($nombreCapitan);
            $capitanRegistrado = false;

            foreach ($integrantes as $nombreIntegrante) {
                $esCapitan = mb_strtolower($nombreIntegrante) === $nombreCapitanNormalizado;
                $capitanRegistrado = $capitanRegistrado || $esCapitan;

                $inscripcion->integrantes()->create([
                    'nombre_completo' => $nombreIntegrante,
                    'user_id' => null,
                    'es_capitan' => $esCapitan,
                ]);
            }

            if (! $capitanRegistrado) {
                $inscripcion->integrantes()->create([
                    'nombre_completo' => $nombreCapitan,
                    'user_id' => null,
                    'es_capitan' => true,
                ]);
            }
        });

        return back()->with('success', 'Inscripción actualizada correctamente.');
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

    public function destroy(int $id)
    {
        $inscripcion = Inscripcion::query()
            ->where('user_id', auth()->id())
            ->findOrFail($id);

        $estado = strtolower((string) $inscripcion->estado);
        $estadoComprobante = strtolower((string) ($inscripcion->estado_comprobante ?? 'no_subido'));

        if ($estado === 'confirmado' || in_array($estadoComprobante, ['aprobado', 'revision'], true)) {
            return back()->with('error', 'No puedes eliminar una inscripción aprobada o en revisión.');
        }

        $comprobante = $inscripcion->comprobante_pago;

        DB::transaction(function () use ($inscripcion) {
            $inscripcion->delete();
        });

        if ($comprobante) {
            $existeOtroUso = Inscripcion::query()
                ->where('comprobante_pago', $comprobante)
                ->exists();

            if (! $existeOtroUso && Storage::disk('public')->exists($comprobante)) {
                Storage::disk('public')->delete($comprobante);
            }
        }

        return back()->with('success', 'Inscripción eliminada correctamente.');
    }

    protected function configuracionPagoActiva(): ?array
    {
        $configuracion = ConfiguracionPago::query()
            ->where('activo', true)
            ->latest('updated_at')
            ->first();

        return $configuracion ? [
            'id' => $configuracion->id,
            'informacion_pago' => $configuracion->informacion_pago,
        ] : null;
    }
}
