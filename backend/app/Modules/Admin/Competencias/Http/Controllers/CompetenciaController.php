<?php

namespace App\Modules\Admin\Competencias\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Competencia;
use App\Modules\Admin\Competencias\Http\Requests\StoreCompetenciaRequest;
use App\Modules\Admin\Competencias\Http\Requests\UpdateCompetenciaRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class CompetenciaController extends Controller
{
    public function index()
    {
        $competencias = Competencia::query()
            ->with(['comiteOrganizadores' => function ($query) {
                $query->orderBy('orden')->orderBy('apellidos')->orderBy('nombres');
            }])
            ->orderByDesc('id')
            ->get([
                'id',
                'nombre',
                'descripcion',
                'fecha_inicio',
                'fecha_fin',
                'enlace_evento',
                'tipo_competencia',
                'imagen_url',
                'estado',
                'created_at',
                'updated_at',
            ])
            ->map(fn (Competencia $competencia) => [
                'id' => $competencia->id,
                'nombre' => $competencia->nombre,
                'descripcion' => $competencia->descripcion,
                'fecha_inicio' => $competencia->fecha_inicio,
                'fecha_fin' => $competencia->fecha_fin,
                'enlace_evento' => $competencia->enlace_evento,
                'tipo_competencia' => $competencia->tipo_competencia,
                'imagen_url' => $competencia->imagen_url ? Storage::url($competencia->imagen_url) : null,
                'estado' => (bool) $competencia->estado,
                'comite_organizadores' => $competencia->comiteOrganizadores
                    ->map(fn ($integrante) => [
                        'id' => (int) $integrante->id,
                        'competencia_id' => (int) $integrante->competencia_id,
                        'nombres' => $integrante->nombres,
                        'apellidos' => $integrante->apellidos,
                        'correo' => $integrante->correo,
                        'rol_comite' => $integrante->rol_comite,
                        'foto' => $integrante->foto,
                        'foto_url' => $integrante->foto ? Storage::url($integrante->foto) : null,
                        'orden' => (int) $integrante->orden,
                        'estado' => (bool) $integrante->estado,
                    ])
                    ->values(),
                'created_at' => $competencia->created_at,
                'updated_at' => $competencia->updated_at,
            ]);

        return Inertia::render('Admin/Competencias', [
            'competencias' => $competencias,
        ]);
    }

    public function store(StoreCompetenciaRequest $request)
    {
        $data = $request->validated();

        if ($request->hasFile('imagen')) {
            $data['imagen_url'] = $request->file('imagen')->store('competencias', 'public');
        }

        unset($data['imagen']);

        Competencia::create($data);

        return redirect()
            ->route('admin.competencias.index')
            ->with('success', 'Competencia creada correctamente.')
            ->setStatusCode(303);
    }

    public function update(UpdateCompetenciaRequest $request, int $id)
    {
        $competencia = Competencia::findOrFail($id);
        $data = $request->validated();

        if ($request->hasFile('imagen')) {
            if ($competencia->imagen_url && Storage::disk('public')->exists($competencia->imagen_url)) {
                Storage::disk('public')->delete($competencia->imagen_url);
            }

            $data['imagen_url'] = $request->file('imagen')->store('competencias', 'public');
        }

        unset($data['imagen']);

        $competencia->update($data);

        return redirect()
            ->route('admin.competencias.index')
            ->with('success', 'Competencia actualizada correctamente.')
            ->setStatusCode(303);
    }

    public function destroy(int $id)
    {
        $competencia = Competencia::findOrFail($id);
        $categoriasCount = $competencia->categorias()->count();

        if ($categoriasCount > 0) {
            return redirect()
                ->route('admin.competencias.index')
                ->with('error', "No se puede eliminar la competencia porque tiene {$categoriasCount} categorías asociadas.")
                ->setStatusCode(303);
        }

        if ($competencia->imagen_url && Storage::disk('public')->exists($competencia->imagen_url)) {
            Storage::disk('public')->delete($competencia->imagen_url);
        }

        $competencia->delete();

        return redirect()
            ->route('admin.competencias.index')
            ->with('success', 'Competencia eliminada correctamente.')
            ->setStatusCode(303);
    }

    public function toggle(int $id): RedirectResponse
    {
        $competencia = Competencia::findOrFail($id);

        try {
            DB::transaction(function () use ($competencia) {
                $activos = DB::table('catalogo.competencias')->where('estado', true)->count();
                $esUnicaActiva = $competencia->estado && $activos === 1;

                if ($esUnicaActiva) {
                    throw new \RuntimeException('Debe existir al menos un evento principal.');
                }

                $nuevoEstado = ! $competencia->estado;

                if ($nuevoEstado) {
                    DB::table('catalogo.competencias')->update(['estado' => false]);
                }

                $competencia->update(['estado' => $nuevoEstado]);
            });

            return redirect()
                ->route('admin.competencias.index')
                ->with('success', 'Evento principal actualizado.')
                ->setStatusCode(303);
        } catch (\RuntimeException $e) {
            return redirect()
                ->route('admin.competencias.index')
                ->with('error', $e->getMessage())
                ->setStatusCode(303);
        }
    }
}
