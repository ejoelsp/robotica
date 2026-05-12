<?php

namespace App\Modules\Admin\Competencias\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\ComiteOrganizador;
use App\Models\Competencia;
use App\Modules\Admin\Competencias\Http\Requests\StoreComiteOrganizadorRequest;
use App\Modules\Admin\Competencias\Http\Requests\UpdateComiteOrganizadorRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;

class ComiteOrganizadorController extends Controller
{
    public function index(Competencia $competencia)
    {
        return response()->json([
            'competencia' => [
                'id' => (int) $competencia->id,
                'nombre' => (string) $competencia->nombre,
            ],
            'integrantes' => $competencia->comiteOrganizadores()
                ->orderBy('orden')
                ->orderBy('apellidos')
                ->orderBy('nombres')
                ->get()
                ->map(fn (ComiteOrganizador $integrante) => $this->serializeIntegrante($integrante))
                ->values(),
        ]);
    }

    public function store(StoreComiteOrganizadorRequest $request, Competencia $competencia): RedirectResponse
    {
        $data = $request->validated();
        $data['competencia_id'] = $competencia->id;

        if ($request->hasFile('foto')) {
            $data['foto'] = $request->file('foto')->store('comite-organizador', 'public');
        }

        ComiteOrganizador::create($data);

        return back(303)->with('success', 'Integrante del comite registrado correctamente.');
    }

    public function update(
        UpdateComiteOrganizadorRequest $request,
        Competencia $competencia,
        ComiteOrganizador $integrante
    ): RedirectResponse {
        $this->ensureBelongsToCompetencia($competencia, $integrante);

        $data = $request->validated();

        if ($request->hasFile('foto')) {
            if ($integrante->foto && Storage::disk('public')->exists($integrante->foto)) {
                Storage::disk('public')->delete($integrante->foto);
            }

            $data['foto'] = $request->file('foto')->store('comite-organizador', 'public');
        } else {
            unset($data['foto']);
        }

        $integrante->update($data);

        return back(303)->with('success', 'Integrante del comite actualizado correctamente.');
    }

    public function destroy(Competencia $competencia, ComiteOrganizador $integrante): RedirectResponse
    {
        $this->ensureBelongsToCompetencia($competencia, $integrante);

        $integrante->update(['estado' => false]);

        return back(303)->with('success', 'Integrante del comite desactivado correctamente.');
    }

    public function toggle(Competencia $competencia, ComiteOrganizador $integrante): RedirectResponse
    {
        $this->ensureBelongsToCompetencia($competencia, $integrante);

        $integrante->update(['estado' => ! $integrante->estado]);

        return back(303)->with('success', 'Estado del integrante actualizado.');
    }

    private function ensureBelongsToCompetencia(Competencia $competencia, ComiteOrganizador $integrante): void
    {
        abort_unless((int) $integrante->competencia_id === (int) $competencia->id, 404);
    }

    private function serializeIntegrante(ComiteOrganizador $integrante): array
    {
        return [
            'id' => (int) $integrante->id,
            'competencia_id' => (int) $integrante->competencia_id,
            'nombres' => (string) $integrante->nombres,
            'apellidos' => (string) $integrante->apellidos,
            'correo' => $integrante->correo,
            'rol_comite' => (string) $integrante->rol_comite,
            'foto' => $integrante->foto,
            'foto_url' => $integrante->foto ? Storage::url($integrante->foto) : null,
            'orden' => (int) $integrante->orden,
            'estado' => (bool) $integrante->estado,
        ];
    }
}
