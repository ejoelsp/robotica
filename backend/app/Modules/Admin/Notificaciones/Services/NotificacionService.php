<?php

namespace App\Modules\Admin\Notificaciones\Services;

use App\Jobs\EnviarNotificacionEmailJob;
use App\Models\Categoria;
use App\Models\Inscripcion;
use App\Models\Notificacion;
use App\Models\User;
use Illuminate\Support\Str;

class NotificacionService
{
    public function notificarInscripcionAprobada(Inscripcion $inscripcion, ?User $actor = null): ?Notificacion
    {
        $inscripcion->loadMissing([
            'usuarioRegistro:id,name,last_name,email',
            'competencia:id,nombre',
            'categoria:id,nombre',
            'equipo:id,nombre',
        ]);

        $usuario = $inscripcion->usuarioRegistro;

        if (! $usuario || ! $usuario->email) {
            return null;
        }

        $competencia = (string) ($inscripcion->competencia?->nombre ?? 'la competencia');
        $categoria = (string) ($inscripcion->categoria?->nombre ?? 'la categoría');
        $equipo = (string) ($inscripcion->equipo?->nombre ?? 'tu equipo');
        $prototipo = (string) ($inscripcion->nombre_prototipo ?: 'Sin prototipo');

        $contenido = implode("\n", [
            'Tu inscripción ha sido aprobada correctamente.',
            '',
            "Equipo: {$equipo}",
            "Prototipo: {$prototipo}",
            "Categoría: {$categoria}",
            "Competencia: {$competencia}",
        ]);

        return $this->crearAppEmailUnica(
            usuario: $usuario,
            tipo: 'inscripcion_aprobada',
            asunto: 'Inscripción aprobada',
            contenido: $contenido,
            referenciaTipo: 'inscripcion_aprobada',
            referenciaId: (int) $inscripcion->id,
            competenciaId: (int) $inscripcion->competencia_id,
            categoriaId: (int) $inscripcion->categoria_id,
            actor: $actor,
            datos: [
                'inscripcion_id' => (int) $inscripcion->id,
                'equipo' => $equipo,
                'prototipo' => $prototipo,
                'categoria' => $categoria,
                'competencia' => $competencia,
            ]
        );
    }

    public function notificarInscripcionRechazada(Inscripcion $inscripcion, ?User $actor = null): ?Notificacion
    {
        $inscripcion->loadMissing([
            'usuarioRegistro:id,name,last_name,email',
            'competencia:id,nombre',
            'categoria:id,nombre',
            'equipo:id,nombre',
        ]);

        $usuario = $inscripcion->usuarioRegistro;

        if (! $usuario || ! $usuario->email) {
            return null;
        }

        $competencia = (string) ($inscripcion->competencia?->nombre ?? 'la competencia');
        $categoria = (string) ($inscripcion->categoria?->nombre ?? 'la categoría');
        $equipo = (string) ($inscripcion->equipo?->nombre ?? 'tu equipo');
        $competenciaTexto = $inscripcion->competencia
            ? 'del ' . Str::title($competencia)
            : 'de la competencia';
        $motivo = trim((string) ($inscripcion->motivo_rechazo ?? 'No especificado'));
        $observacion = trim((string) ($inscripcion->observacion_rechazo ?? ''));

        $contenido = implode("\n", [
            "El comprobante de pago enviado para el equipo {$equipo}, en la categoría {$categoria} {$competenciaTexto}, no pudo ser aprobado.",
            '',
            "Motivo: {$motivo}.",
            '',
            'Por favor, vuelve a subir un comprobante claro y legible para continuar con el proceso de inscripción.',
        ]);

        if ($observacion !== '') {
            $contenido .= "\n\nObservación: {$observacion}.";
        }

        return $this->crearAppEmailUnica(
            usuario: $usuario,
            tipo: 'inscripcion_rechazada',
            asunto: 'Inscripción rechazada',
            contenido: $contenido,
            referenciaTipo: 'inscripcion_rechazada',
            referenciaId: (int) $inscripcion->id,
            competenciaId: (int) $inscripcion->competencia_id,
            categoriaId: (int) $inscripcion->categoria_id,
            actor: $actor,
            datos: [
                'inscripcion_id' => (int) $inscripcion->id,
                'equipo' => $equipo,
                'categoria' => $categoria,
                'competencia' => $competencia,
                'motivo' => $motivo,
                'observacion' => $observacion,
            ],
            evitarDuplicado: false
        );
    }

    public function notificarResultadosCategoriaFinalizada(Categoria $categoria, ?User $actor = null): int
    {
        $categoria->loadMissing('competencia:id,nombre');

        $inscripciones = Inscripcion::query()
            ->with('usuarioRegistro:id,name,last_name,email')
            ->aprobadas()
            ->where('categoria_id', $categoria->id)
            ->whereNotNull('user_id')
            ->get()
            ->unique('user_id');

        $enviadas = 0;
        $competencia = (string) ($categoria->competencia?->nombre ?? 'la competencia');
        $categoriaNombre = (string) $categoria->nombre;

        foreach ($inscripciones as $inscripcion) {
            $usuario = $inscripcion->usuarioRegistro;

            if (! $usuario || ! $usuario->email) {
                continue;
            }

            $notificacion = $this->crearAppEmailUnica(
                usuario: $usuario,
                tipo: 'resultados_publicados',
                asunto: 'Resultados disponibles',
                contenido: "Los resultados de la categoría {$categoriaNombre} en {$competencia} ya han sido consolidados.",
                referenciaTipo: 'categoria_resultados_finalizados',
                referenciaId: (int) $categoria->id,
                competenciaId: (int) $categoria->competencia_id,
                categoriaId: (int) $categoria->id,
                actor: $actor,
                datos: [
                    'categoria' => $categoriaNombre,
                    'competencia' => $competencia,
                    'resultados_finalizados_at' => optional($categoria->resultados_finalizados_at)?->toIso8601String(),
                ]
            );

            if ($notificacion) {
                $enviadas++;
            }
        }

        return $enviadas;
    }

    private function crearAppEmailUnica(
        User $usuario,
        string $tipo,
        string $asunto,
        string $contenido,
        string $referenciaTipo,
        int $referenciaId,
        ?int $competenciaId = null,
        ?int $categoriaId = null,
        ?User $actor = null,
        array $datos = [],
        bool $evitarDuplicado = true
    ): ?Notificacion {
        if ($evitarDuplicado) {
            $existe = Notificacion::query()
                ->where('user_id', $usuario->id)
                ->where('referencia_tipo', $referenciaTipo)
                ->where('referencia_id', $referenciaId)
                ->exists();

            if ($existe) {
                return null;
            }
        }

        $notificacion = Notificacion::query()->create([
            'user_id' => $usuario->id,
            'competencia_id' => $competenciaId,
            'categoria_id' => $categoriaId,
            'canal' => 'app_email',
            'tipo' => $tipo,
            'asunto' => $asunto,
            'contenido' => $contenido,
            'estado' => 'pendiente',
            'leido' => false,
            'reintentos' => 0,
            'email_destino' => $usuario->email,
            'referencia_tipo' => $referenciaTipo,
            'referencia_id' => $referenciaId,
            'creado_por' => $actor?->id,
            'datos' => $datos,
        ]);

        EnviarNotificacionEmailJob::dispatch((int) $notificacion->id)->afterCommit();

        return $notificacion;
    }
}
