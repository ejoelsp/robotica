<?php

namespace App\Modules\Competidor\Inscripciones\Services;

use App\Models\Equipo;
use App\Models\Categoria;
use App\Models\Inscripcion;
use App\Models\Competencia;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InscripcionService
{
    public function registrar(User $usuarioAutenticado, array $data): Inscripcion
    {
        return DB::transaction(function () use ($usuarioAutenticado, $data) {
            $competencia = Competencia::query()->findOrFail($data['competencia_id']);
            $categoria = Categoria::query()->findOrFail($data['categoria_id']);

            if ((int) $categoria->competencia_id !== (int) $competencia->id) {
                throw ValidationException::withMessages([
                    'categoria_id' => 'La categoría no pertenece a la competencia seleccionada.',
                ]);
            }

            $nombreCapitan = trim((string) $data['nombre_capitan']);

            if ($nombreCapitan === '') {
                throw ValidationException::withMessages([
                    'nombre_capitan' => 'El nombre del capitán es obligatorio.',
                ]);
            }

            /*
            Crear o reutilizar equipo.
            Ya no depende de capitan_user_id.
            */
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

            /*
            Evitar duplicidad:
            mismo equipo y mismo prototipo en la misma categoría
            */
            $existeInscripcion = Inscripcion::query()
                ->where('categoria_id', $categoria->id)
                ->where('equipo_id', $equipo->id)
                ->where('nombre_prototipo', $data['nombre_prototipo'])
                ->exists();

            if ($existeInscripcion) {
                throw ValidationException::withMessages([
                    'nombre_prototipo' => 'Ya existe una inscripción para este mismo equipo con este mismo prototipo en la categoría seleccionada.',
                ]);
            }

            $inscripcion = Inscripcion::query()->create([
                'competencia_id' => $competencia->id,
                'categoria_id' => $categoria->id,
                'equipo_id' => $equipo->id,
                'user_id' => $usuarioAutenticado->id,
                'nombre_prototipo' => $data['nombre_prototipo'],
                'telefono_contacto' => $data['telefono_contacto'],
                'codigo' => $this->generarCodigo(
                    $competencia->id,
                    $categoria->id,
                    $equipo->id
                ),
                'estado' => 'pendiente_pago',
            ]);

            /*
            Procesar integrantes
            */
            $integrantes = collect($data['integrantes'])
                ->map(fn ($nombre) => trim((string) $nombre))
                ->filter()
                ->unique()
                ->values();

            $nombreCapitanNormalizado = mb_strtolower($nombreCapitan);
            $capitanRegistrado = false;

            foreach ($integrantes as $nombreIntegrante) {
                $esCapitan = mb_strtolower($nombreIntegrante) === $nombreCapitanNormalizado;

                if ($esCapitan) {
                    $capitanRegistrado = true;
                }

                $inscripcion->integrantes()->create([
                    'nombre_completo' => $nombreIntegrante,
                    'user_id' => null,
                    'es_capitan' => $esCapitan,
                ]);
            }

            /*
            Si el capitán no estaba en la lista de integrantes,
            lo agregamos automáticamente.
            */
            if (!$capitanRegistrado) {
                $inscripcion->integrantes()->create([
                    'nombre_completo' => $nombreCapitan,
                    'user_id' => null,
                    'es_capitan' => true,
                ]);
            }

            return $inscripcion->load([
                'competencia',
                'categoria',
                'equipo',
                'usuarioRegistro',
                'integrantes',
            ]);
        });
    }

    protected function generarCodigo(
        int $competenciaId,
        int $categoriaId,
        int $equipoId
    ): string {
        return 'INS-' .
            $competenciaId . '-' .
            $categoriaId . '-' .
            $equipoId . '-' .
            Str::upper(Str::random(6));
    }
}
