<?php

namespace App\Modules\Admin\Notificaciones\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Jobs\EnviarNotificacionEmailJob;
use App\Models\Notificacion;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class NotificacionController extends Controller
{
    public function adminIndex(Request $request): Response
    {
        $recibidas = $this->notificacionesRecibidas((int) $request->user()->id);
        $enviadas = $this->notificacionesEnviadas();
        $competenciaActualId = $this->competenciaActualId();
        $categorias = $this->categoriasParaNotificaciones($competenciaActualId);

        return Inertia::render('Admin/Notificaciones', [
            'notificacionesEnviadas' => $enviadas,
            'notificacionesRecibidas' => $recibidas,
            'categorias' => $categorias,
            'competenciaActualId' => $competenciaActualId,
            'stats' => [
                'enviadas' => $enviadas->count(),
                'recibidas' => $recibidas->count(),
                'noLeidas' => $recibidas->where('leido', false)->count(),
                'destinatarios' => $this->destinatariosAprobadosPorCategorias($competenciaActualId, collect())->count(),
            ],
        ]);
    }

    public function competidorIndex(Request $request): Response
    {
        $recibidas = $this->notificacionesRecibidas((int) $request->user()->id);

        return Inertia::render('Competidor/Notificaciones', [
            'notificacionesRecibidas' => $recibidas,
            'stats' => [
                'recibidas' => $recibidas->count(),
                'noLeidas' => $recibidas->where('leido', false)->count(),
            ],
        ]);
    }

    public function contadorAdmin(Request $request)
    {
        $noLeidas = Notificacion::query()
            ->where('user_id', $request->user()->id)
            ->where('leido', false)
            ->count();

        return response()->json([
            'no_leidas' => $noLeidas,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'tipo' => ['required', 'string', Rule::in(['notificacion_admin'])],
            'destinatarios' => ['required', 'string', Rule::in(['competidor'])],
            'canal' => ['required', 'string', Rule::in(['app', 'email', 'app_email'])],
            'competencia_id' => ['required', 'integer', 'min:1'],
            'categoria_ids' => ['required', 'array', 'min:1'],
            'categoria_ids.*' => ['string'],
            'asunto' => ['required', 'string', 'max:200'],
            'contenido' => ['required', 'string', 'max:5000'],
        ], [
            'categoria_ids.required' => 'Selecciona al menos una categoría.',
            'categoria_ids.min' => 'Selecciona al menos una categoría.',
        ]);

        $categoriaIds = collect($validated['categoria_ids'])
            ->filter(fn ($value) => $value !== 'all')
            ->map(fn ($value) => (int) $value)
            ->filter(fn (int $value) => $value > 0)
            ->unique()
            ->values();

        $destinatarios = $this->destinatariosAprobadosPorCategorias((int) $validated['competencia_id'], $categoriaIds);

        if ($destinatarios->isEmpty()) {
            return back()->with('error', 'No se encontraron competidores con comprobante aprobado para las categorias seleccionadas.');
        }

        $creadas = 0;

        foreach ($destinatarios as $destinatario) {
            $notificacion = Notificacion::query()->create([
                'user_id' => $destinatario->id,
                'canal' => $validated['canal'],
                'tipo' => $validated['tipo'],
                'asunto' => $validated['asunto'],
                'contenido' => $validated['contenido'],
                'competencia_id' => (int) $validated['competencia_id'],
                'categoria_id' => $destinatario->categoria_id,
                'estado' => in_array($validated['canal'], ['email', 'app_email'], true) ? 'pendiente' : 'enviado',
                'leido' => false,
                'reintentos' => 0,
                'email_destino' => in_array($validated['canal'], ['email', 'app_email'], true) ? $destinatario->email : null,
                'creado_por' => $request->user()->id,
            ]);

            if (in_array($validated['canal'], ['email', 'app_email'], true)) {
                EnviarNotificacionEmailJob::dispatch((int) $notificacion->id)->afterCommit();
            }

            $creadas++;
        }

        $mensaje = in_array($validated['canal'], ['email', 'app_email'], true)
            ? "Notificación registrada y correo encolado para {$creadas} destinatario(s)."
            : "Notificación enviada a {$creadas} destinatario(s).";

        return back()->with('success', $mensaje);
    }

    public function markAsRead(Request $request, Notificacion $notificacion)
    {
        if ((int) $notificacion->user_id !== (int) $request->user()->id) {
            abort(403);
        }

        if (! $notificacion->leido) {
            $notificacion->update([
                'leido' => true,
                'leido_en' => now(),
            ]);
        }

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'ok' => true,
                'notificacion_id' => (int) $notificacion->id,
            ]);
        }

        return back();
    }

    private function notificacionesRecibidas(int $userId): Collection
    {
        return Notificacion::query()
            ->with(['competencia:id,nombre', 'categoria:id,nombre', 'creador:id,name,last_name,email'])
            ->where('user_id', $userId)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(100)
            ->get()
            ->map(fn (Notificacion $notificacion) => $this->serializar($notificacion));
    }

    private function notificacionesEnviadas(): Collection
    {
        return Notificacion::query()
            ->with(['usuario:id,name,last_name,email', 'competencia:id,nombre', 'categoria:id,nombre'])
            ->where('tipo', '!=', 'reclamo_competidor')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(150)
            ->get()
            ->map(fn (Notificacion $notificacion) => $this->serializar($notificacion));
    }

    private function competenciaActualId(): int
    {
        return (int) (
            DB::table('catalogo.competencias')->where('estado', true)->orderByDesc('id')->value('id')
            ?: DB::table('catalogo.competencias')->orderByDesc('id')->value('id')
            ?: 0
        );
    }

    private function categoriasParaNotificaciones(int $competenciaId): Collection
    {
        return DB::table('catalogo.categorias')
            ->where('competencia_id', $competenciaId)
            ->orderBy('nombre')
            ->get(['id', 'nombre'])
            ->map(fn ($categoria) => [
                'id' => (int) $categoria->id,
                'nombre' => (string) $categoria->nombre,
            ]);
    }

    private function destinatariosAprobadosPorCategorias(int $competenciaId, Collection $categoriaIds): Collection
    {
        $competidorRoleId = DB::table('seguridad.roles')
            ->where('nombre', 'competidor')
            ->value('id');

        return User::query()
            ->select([
                'seguridad.users.id',
                'seguridad.users.name',
                'seguridad.users.last_name',
                'seguridad.users.email',
                DB::raw('MIN(i.categoria_id) as categoria_id'),
            ])
            ->join('vinculaciones.inscripciones as i', 'i.user_id', '=', 'seguridad.users.id')
            ->where('seguridad.users.estado', true)
            ->where('seguridad.users.role_id', $competidorRoleId)
            ->whereNotNull('seguridad.users.email')
            ->where('i.competencia_id', $competenciaId)
            ->where('i.estado', 'confirmado')
            ->where('i.estado_comprobante', 'aprobado')
            ->when($categoriaIds->isNotEmpty(), fn ($query) => $query->whereIn('i.categoria_id', $categoriaIds->all()))
            ->groupBy('seguridad.users.id', 'seguridad.users.name', 'seguridad.users.last_name', 'seguridad.users.email')
            ->orderBy('seguridad.users.name')
            ->get();
    }

    private function serializar(Notificacion $notificacion): array
    {
        return [
            'id' => (int) $notificacion->id,
            'tipo' => (string) $notificacion->tipo,
            'canal' => (string) $notificacion->canal,
            'asunto' => (string) $notificacion->asunto,
            'contenido' => (string) $notificacion->contenido,
            'estado' => (string) $notificacion->estado,
            'leido' => (bool) $notificacion->leido,
            'created_at' => optional($notificacion->created_at)?->format('Y-m-d H:i'),
            'enviado_en' => optional($notificacion->enviado_en)?->format('Y-m-d H:i'),
            'email_destino' => $notificacion->email_destino,
            'destinatario' => $notificacion->usuario ? [
                'id' => (int) $notificacion->usuario->id,
                'nombre' => trim((string) $notificacion->usuario->name . ' ' . (string) $notificacion->usuario->last_name),
                'email' => (string) $notificacion->usuario->email,
            ] : null,
            'creador' => $notificacion->creador ? [
                'id' => (int) $notificacion->creador->id,
                'nombre' => trim((string) $notificacion->creador->name . ' ' . (string) $notificacion->creador->last_name),
                'email' => (string) $notificacion->creador->email,
            ] : null,
            'competencia' => $notificacion->competencia?->nombre,
            'categoria' => $notificacion->categoria?->nombre,
            'datos' => $notificacion->datos ?? [],
        ];
    }
}
