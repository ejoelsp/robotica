<?php

namespace App\Modules\Admin\AsignacionJueces\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\AsignacionJuezCategoria;
use App\Models\Categoria;
use App\Models\Ronda;
use App\Modules\Admin\AsignacionJueces\Requests\StoreAsignacionJuezRequest;
use App\Modules\Admin\AsignacionJueces\Requests\UpdateAsignacionJuezRequest;
use App\Modules\Admin\AsignacionJueces\Requests\UpdateConfigJuecesCompetenciaRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class AsignacionJuezController extends Controller
{
    public function index(): Response
    {
        $competencia = DB::table('catalogo.competencias')
            ->where('estado', true)
            ->orderByDesc('id')
            ->select('id', 'nombre', 'estado')
            ->first()
            ?? DB::table('catalogo.competencias')
                ->orderByDesc('id')
                ->select('id', 'nombre', 'estado')
                ->first();

        $competenciaId = $competencia?->id;

        $configJueces = $competenciaId
            ? DB::table('catalogo.config_jueces_competencia')
                ->where('competencia_id', $competenciaId)
                ->first()
            : null;

        if ($competenciaId) {
            Categoria::query()
                ->with(['configCalificacion.mecanismo'])
                ->where('competencia_id', $competenciaId)
                ->where('estado', true)
                ->get()
                ->each(fn (Categoria $categoria) => $this->ensureRondaSistemaEnfrentamiento($categoria));
        }

        $categorias = Categoria::query()
            ->with(['configCalificacion.mecanismo'])
            ->withCount([
                'inscripciones as inscripciones_count' => fn ($query) => $query->aprobadas(),
                'rondas',
            ])
            ->where('competencia_id', $competenciaId)
            ->where('estado', true)
            ->orderBy('nombre')
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->id,
                    'competencia_id' => $item->competencia_id,
                    'nombre' => $item->nombre,
                    'estado' => (bool) $item->estado,
                    'inscripciones_count' => (int) $item->inscripciones_count,
                    'rondas_count' => (int) $item->rondas_count,
                    'config_calificacion' => $item->configCalificacion ? [
                        'id' => $item->configCalificacion->id,
                        'mecanismo_calificacion_id' => $item->configCalificacion->mecanismo_calificacion_id,
                        'mecanismo_codigo' => $item->configCalificacion->mecanismo?->codigo,
                        'mecanismo_nombre' => $item->configCalificacion->mecanismo?->nombre,
                        'unidad_resultado' => $item->configCalificacion->unidad_resultado,
                        'orden_ranking' => $item->configCalificacion->orden_ranking,
                        'requiere_aprobacion_admin' => (bool) $item->configCalificacion->requiere_aprobacion_admin,
                        'visible_publico_en_vivo' => (bool) $item->configCalificacion->visible_publico_en_vivo,
                        'permite_edicion_juez' => (bool) $item->configCalificacion->permite_edicion_juez,
                        'campos_json' => $item->configCalificacion->campos_json ?? [],
                        'reglas_json' => $item->configCalificacion->reglas_json ?? [],
                        'modalidad_competencia' => $this->modalidadCompetencia($item),
                    ] : null,
                ];
            })
            ->values();

        $jueces = DB::table('seguridad.users as u')
            ->join('seguridad.roles as r', 'r.id', '=', 'u.role_id')
            ->where('r.nombre', 'juez')
            ->select(
                'u.id',
                'u.name',
                'u.last_name',
                'u.email',
                'u.telefono',
                'u.email_verified_at',
                'u.must_change_password',
                'u.photo_path',
                ...(Schema::hasColumn('seguridad.users', 'estado') ? ['u.estado'] : [])
            )
            ->orderBy('u.name')
            ->orderBy('u.last_name')
            ->get()
            ->map(function ($item) {
                $estado = property_exists($item, 'estado')
                    ? (bool) $item->estado
                    : ($item->email_verified_at !== null && ! (bool) $item->must_change_password);

                return [
                    'id' => $item->id,
                    'nombre' => trim(($item->name ?? '') . ' ' . ($item->last_name ?? '')),
                    'email' => $item->email,
                    'telefono' => $item->telefono,
                    'photo_path' => $item->photo_path,
                    'photo_url' => $item->photo_path ? Storage::url($item->photo_path) : null,
                    'estado' => $estado,
                    'estado_texto' => $estado ? 'Activo' : 'Inactivo',
                ];
            })
            ->values();

        $asignaciones = DB::table('vinculaciones.asignaciones_juez_categoria as ajc')
            ->join('catalogo.categorias as c', 'c.id', '=', 'ajc.categoria_id')
            ->join('seguridad.users as u', 'u.id', '=', 'ajc.juez_user_id')
            ->where('c.competencia_id', $competenciaId)
            ->select(
                'ajc.id',
                'ajc.categoria_id',
                'ajc.juez_user_id',
                'ajc.rol',
                'c.nombre as categoria_nombre',
                'u.name',
                'u.last_name',
                'u.email',
                'u.telefono',
                'ajc.created_at'
            )
            ->orderByDesc('ajc.id')
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->id,
                    'categoria_id' => $item->categoria_id,
                    'juez_user_id' => $item->juez_user_id,
                    'categoria_nombre' => $item->categoria_nombre,
                    'juez_nombre' => trim(($item->name ?? '') . ' ' . ($item->last_name ?? '')),
                    'juez_email' => $item->email,
                    'juez_telefono' => $item->telefono,
                    'rol' => $item->rol,
                    'created_at' => $item->created_at,
                ];
            })
            ->values();

        return Inertia::render('Admin/AsignacionJueces', [
            'competenciaId' => $competenciaId,
            'competencia' => $competencia ? [
                'id' => (int) $competencia->id,
                'nombre' => (string) $competencia->nombre,
                'estado' => (bool) $competencia->estado,
            ] : null,
            'configJueces' => [
                'competencia_id' => $competenciaId ? (int) $competenciaId : null,
                'jueces_principales_requeridos' => (int) ($configJueces?->jueces_principales_requeridos ?? 1),
                'jueces_apoyo_requeridos' => (int) ($configJueces?->jueces_apoyo_requeridos ?? 2),
            ],
            'categorias' => $categorias,
            'jueces' => $jueces,
            'asignaciones' => $asignaciones,
        ]);
    }

    private function modalidadCompetencia(Categoria $categoria): string
    {
        $config = $categoria->configCalificacion;
        $registro = is_array($config?->reglas_json ?? null)
            ? (array) ($config->reglas_json['registro'] ?? [])
            : [];
        $campos = is_array($config?->campos_json ?? null) ? $config->campos_json : [];
        $mecanismo = (string) ($config?->mecanismo?->codigo ?? '');

        return (string) (
            $registro['modalidad_competencia']
            ?? $campos['modalidad_competencia']
            ?? (in_array($mecanismo, ['combate', 'combate_llaves', 'soccer_goles'], true)
                ? 'enfrentamiento_directo'
                : 'participacion_individual')
        );
    }

    private function ensureRondaSistemaEnfrentamiento(Categoria $categoria): void
    {
        if ($this->modalidadCompetencia($categoria) !== 'enfrentamiento_directo') {
            return;
        }

        if ($categoria->rondas()->exists()) {
            return;
        }

        Ronda::create([
            'categoria_id' => $categoria->id,
            'nombre' => 'Ronda 1',
            'tipo' => 'libre',
            'orden' => 1,
            'cantidad_intentos' => 1,
            'intentos_consecutivos' => false,
            'clasifican_cantidad' => null,
            'criterio_clasificacion' => 'ganador_enfrentamiento',
            'es_final' => false,
            'estado' => 'activa',
        ]);
    }

    public function updateConfig(UpdateConfigJuecesCompetenciaRequest $request): RedirectResponse
    {
        $data = $request->validated();

        DB::table('catalogo.config_jueces_competencia')->updateOrInsert(
            ['competencia_id' => $data['competencia_id']],
            [
                'jueces_principales_requeridos' => $data['jueces_principales_requeridos'],
                'jueces_apoyo_requeridos' => $data['jueces_apoyo_requeridos'],
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        return redirect()
            ->route('admin.asignacion_jueces.index', [], 303)
            ->with('success', 'Configuración de jueces actualizada correctamente.');
    }

    public function store(StoreAsignacionJuezRequest $request): RedirectResponse
    {
        $data = $request->validated();

        DB::transaction(function () use ($data) {
            foreach ($data['juez_principal_ids'] as $juezPrincipalId) {
                AsignacionJuezCategoria::create([
                    'categoria_id' => $data['categoria_id'],
                    'juez_user_id' => $juezPrincipalId,
                    'rol' => 'principal',
                ]);
            }

            foreach ($data['jueces_apoyo_ids'] ?? [] as $juezApoyoId) {
                AsignacionJuezCategoria::create([
                    'categoria_id' => $data['categoria_id'],
                    'juez_user_id' => $juezApoyoId,
                    'rol' => 'apoyo',
                ]);
            }
        });

        return redirect()
            ->route('admin.asignacion_jueces.index', [], 303)
            ->with('success', 'Asignación creada correctamente.');
    }

    public function update(UpdateAsignacionJuezRequest $request, ?int $id = null): RedirectResponse
    {
        $data = $request->validated();

        DB::transaction(function () use ($data) {
            DB::table('vinculaciones.asignaciones_juez_categoria')
                ->where('categoria_id', $data['categoria_id'])
                ->delete();

            foreach ($data['juez_principal_ids'] as $juezPrincipalId) {
                AsignacionJuezCategoria::create([
                    'categoria_id' => $data['categoria_id'],
                    'juez_user_id' => $juezPrincipalId,
                    'rol' => 'principal',
                ]);
            }

            foreach ($data['jueces_apoyo_ids'] ?? [] as $juezApoyoId) {
                AsignacionJuezCategoria::create([
                    'categoria_id' => $data['categoria_id'],
                    'juez_user_id' => $juezApoyoId,
                    'rol' => 'apoyo',
                ]);
            }
        });

        return redirect()
            ->route('admin.asignacion_jueces.index', [], 303)
            ->with('success', 'Asignación actualizada correctamente.');
    }

}
