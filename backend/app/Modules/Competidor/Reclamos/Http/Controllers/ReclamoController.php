<?php

namespace App\Modules\Competidor\Reclamos\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Jobs\EnviarCorreoReclamoJob;
use App\Models\Incidencia;
use App\Models\Inscripcion;
use App\Models\Notificacion;
use App\Models\User;
use App\Modules\Competidor\Reclamos\Requests\StoreReclamoRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ReclamoController extends Controller
{
    public function index(Request $request): Response
    {
        $inscripciones = $this->inscripcionesAprobadas($request->user()->id)
            ->get()
            ->map(fn (Inscripcion $inscripcion) => $this->mapInscripcion($inscripcion, null));

        $reclamos = Incidencia::query()
            ->where('tipo', 'reclamo')
            ->where('reportado_por', $request->user()->id)
            ->orderByDesc('id')
            ->get()
            ->map(fn (Incidencia $reclamo) => [
                'id' => $reclamo->id,
                'codigo' => $reclamo->codigo,
                'categoria' => $reclamo->equipo_snapshot['categoria'] ?? 'Categoría',
                'equipo' => $reclamo->equipo_snapshot['nombre'] ?? 'Equipo',
                'estado' => $reclamo->estado,
                'fecha_envio' => optional($reclamo->fecha_envio ?? $reclamo->created_at)?->format('d/m/Y H:i'),
                'formato_url' => route('competidor.reclamos.formato', $reclamo),
            ]);

        return Inertia::render('Competidor/Reclamos', [
            'inscripcionesAprobadas' => $inscripciones,
            'reclamos' => $reclamos,
        ]);
    }

    public function preview(Request $request)
    {
        $data = $request->validate([
            'inscripcion_id' => ['required', 'integer'],
            'descripcion' => ['required', 'string', 'min:10', 'max:3000'],
        ]);

        $inscripcion = $this->inscripcionesAprobadas($request->user()->id)
            ->where('id', (int) $data['inscripcion_id'])
            ->firstOrFail();

        return response()->view('reclamos.formato', [
            'reclamo' => $this->mapInscripcion($inscripcion, $data['descripcion']),
            'modo' => 'preview',
        ]);
    }

    public function store(StoreReclamoRequest $request)
    {
        $data = $request->validated();
        $user = $request->user();

        $inscripcion = $this->inscripcionesAprobadas($user->id)
            ->where('id', (int) $data['inscripcion_id'])
            ->firstOrFail();

        $documento = $this->mapInscripcion($inscripcion, $data['descripcion']);

        $reclamo = DB::transaction(function () use ($documento, $data, $user, $inscripcion) {
            $reclamo = Incidencia::create([
                'categoria_id' => $inscripcion->categoria_id,
                'equipo_id' => $inscripcion->equipo_id,
                'reportado_por' => $user->id,
                'tipo' => 'reclamo',
                'descripcion' => $data['descripcion'],
                'estado' => 'pendiente',
                'fecha_envio' => now(),
                'jueces_snapshot' => $documento['jueces'],
                'equipo_snapshot' => $documento['equipo'],
                'integrantes_snapshot' => $documento['integrantes'],
                'prototipo_nombre' => $documento['prototipo_nombre'],
                'institucion' => $documento['institucion'],
            ]);

            $codigo = sprintf('REC-%s-%06d', now()->format('Y'), $reclamo->id);
            $documento['codigo'] = $codigo;
            $html = view('reclamos.formato', [
                'reclamo' => $documento,
                'modo' => 'final',
            ])->render();

            $path = "reclamos/{$codigo}.html";
            Storage::disk('public')->put($path, $html);

            $reclamo->update([
                'codigo' => $codigo,
                'pdf_path' => $path,
            ]);

            return $reclamo->fresh();
        });

        EnviarCorreoReclamoJob::dispatch((int) $reclamo->id)->afterCommit();
        $this->registrarNotificacionesAdministradores($reclamo, $documento, $user);

        return redirect()
            ->route('competidor.reclamos')
            ->with('success', 'Reclamo enviado correctamente. El comité organizador y el administrador fueron notificados.');
    }

    public function formato(Request $request, Incidencia $incidencia)
    {
        abort_unless($incidencia->tipo === 'reclamo', 404);

        $esPropietario = (int) $incidencia->reportado_por === (int) $request->user()->id;
        $esAdmin = (int) $request->user()->role_id === 1;

        abort_unless($esPropietario || $esAdmin, 403);

        if (! $esPropietario && $incidencia->estado === 'pendiente') {
            $incidencia->update(['estado' => 'recibido']);
            $incidencia->refresh();
        }

        return response()->view('reclamos.formato', [
            'reclamo' => $this->mapIncidencia($incidencia),
            'modo' => 'final',
        ]);
    }

    private function inscripcionesAprobadas(int $userId)
    {
        return Inscripcion::query()
            ->aprobadas()
            ->with([
                'competencia:id,nombre,fecha_inicio,fecha_fin,imagen_url,logo_url',
                'categoria.asignacionesJuez.juez:id,name,last_name,email',
                'equipo.capitan:id,name,last_name,email',
                'equipo.inscripciones.integrantes:id,inscripcion_id,nombre_completo,user_id,es_capitan',
                'integrantes:id,inscripcion_id,nombre_completo,user_id,es_capitan',
            ])
            ->where(function ($query) use ($userId) {
                $query->where('user_id', $userId)
                    ->orWhereHas('integrantes', fn ($q) => $q->where('user_id', $userId));
            })
            ->orderByDesc('id');
    }

    private function mapInscripcion(Inscripcion $inscripcion, ?string $descripcion): array
    {
        $categoria = $inscripcion->categoria;
        $competencia = $inscripcion->competencia;
        $equipo = $inscripcion->equipo;

        $integrantes = $this->integrantesDelEquipo($inscripcion);

        $jueces = $categoria?->asignacionesJuez
            ?->map(fn ($asignacion) => [
                'nombre' => trim(($asignacion->juez?->name ?? '') . ' ' . ($asignacion->juez?->last_name ?? '')) ?: 'Juez asignado',
                'correo' => $asignacion->juez?->email,
                'rol' => ucfirst((string) $asignacion->rol),
            ])
            ->values()
            ->all() ?? [];

        return [
            'id' => $inscripcion->id,
            'codigo' => 'REC-BORRADOR',
            'fecha' => now()->format('d/m/Y H:i'),
            'evento' => [
                'id' => $competencia?->id,
                'nombre' => $competencia?->nombre ?? 'Evento',
                'logo_url' => $this->storageUrl($competencia?->logo_url ?: $competencia?->imagen_url),
            ],
            'categoria' => [
                'id' => $categoria?->id,
                'nombre' => $categoria?->nombre ?? 'Categoría',
            ],
            'equipo' => [
                'id' => $equipo?->id,
                'nombre' => $equipo?->nombre ?? 'Equipo',
                'categoria' => $categoria?->nombre ?? 'Categoría',
                'evento' => $competencia?->nombre ?? 'Evento',
                'logo_url' => $this->storageUrl($competencia?->logo_url ?: $competencia?->imagen_url),
            ],
            'integrantes' => $integrantes,
            'capitan' => collect($integrantes)->firstWhere('es_capitan', true),
            'jueces' => $jueces,
            'prototipo_nombre' => $inscripcion->nombre_prototipo,
            'institucion' => $equipo?->institucion,
            'descripcion' => $descripcion,
        ];
    }

    private function mapIncidencia(Incidencia $incidencia): array
    {
        return [
            'codigo' => $incidencia->codigo,
            'fecha' => optional($incidencia->fecha_envio ?? $incidencia->created_at)?->format('d/m/Y H:i'),
            'evento' => [
                'nombre' => $incidencia->equipo_snapshot['evento'] ?? 'Evento',
                'logo_url' => $incidencia->equipo_snapshot['logo_url'] ?? null,
            ],
            'categoria' => [
                'nombre' => $incidencia->equipo_snapshot['categoria'] ?? 'Categoría',
            ],
            'equipo' => $incidencia->equipo_snapshot ?? [],
            'integrantes' => $incidencia->integrantes_snapshot ?? [],
            'capitan' => collect($incidencia->integrantes_snapshot ?? [])->firstWhere('es_capitan', true),
            'jueces' => $incidencia->jueces_snapshot ?? [],
            'prototipo_nombre' => $incidencia->prototipo_nombre,
            'institucion' => $incidencia->institucion,
            'descripcion' => $incidencia->descripcion,
        ];
    }

    private function integrantesDelEquipo(Inscripcion $inscripcion): array
    {
        $integrantes = $inscripcion->integrantes ?? collect();

        if ($inscripcion->equipo?->relationLoaded('inscripciones')) {
            $integrantes = $integrantes->merge(
                $inscripcion->equipo->inscripciones
                    ->flatMap(fn (Inscripcion $item) => $item->integrantes ?? collect())
            );
        }

        return $integrantes
            ->map(fn ($integrante) => [
                'nombre' => trim((string) $integrante->nombre_completo),
                'es_capitan' => (bool) $integrante->es_capitan,
            ])
            ->filter(fn (array $integrante) => $integrante['nombre'] !== '')
            ->groupBy(fn (array $integrante) => mb_strtolower($integrante['nombre']))
            ->map(fn ($items) => [
                'nombre' => $items->first()['nombre'],
                'es_capitan' => $items->contains(fn (array $item) => $item['es_capitan']),
            ])
            ->sortByDesc(fn (array $integrante) => $integrante['es_capitan'])
            ->values()
            ->all();
    }

    private function registrarNotificacionesAdministradores(Incidencia $reclamo, array $documento, User $competidor): void
    {
        $admins = User::query()
            ->join('seguridad.roles', 'seguridad.roles.id', '=', 'seguridad.users.role_id')
            ->where('seguridad.roles.nombre', 'admin')
            ->where('seguridad.users.estado', true)
            ->get([
                'seguridad.users.id',
                'seguridad.users.name',
                'seguridad.users.last_name',
                'seguridad.users.email',
            ]);

        $competidorNombre = trim((string) $competidor->name . ' ' . (string) $competidor->last_name) ?: 'Competidor';
        $categoria = $documento['categoria']['nombre'] ?? 'Categoría';
        $equipo = $documento['equipo']['nombre'] ?? 'Equipo';
        $prototipo = $documento['prototipo_nombre'] ?: 'No registrado';

        foreach ($admins as $admin) {
            Notificacion::query()->create([
                'user_id' => $admin->id,
                'competencia_id' => $documento['evento']['id'] ?? null,
                'categoria_id' => $reclamo->categoria_id,
                'canal' => 'app',
                'tipo' => 'reclamo_competidor',
                'asunto' => "Nuevo reclamo {$reclamo->codigo}",
                'contenido' => "El usuario {$competidorNombre} envió un reclamo para la categoría {$categoria}, equipo {$equipo}, prototipo {$prototipo}.",
                'estado' => 'enviado',
                'leido' => false,
                'reintentos' => 0,
                'referencia_tipo' => 'reclamo',
                'referencia_id' => $reclamo->id,
                'creado_por' => $competidor->id,
                'datos' => [
                    'codigo' => $reclamo->codigo,
                    'formato_url' => route('competidor.reclamos.formato', $reclamo),
                    'equipo' => $equipo,
                    'prototipo' => $prototipo,
                ],
            ]);
        }
    }

    private function storageUrl(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        if (Str::startsWith($path, ['http://', 'https://', '/storage/'])) {
            return $path;
        }

        return Storage::url($path);
    }
}
