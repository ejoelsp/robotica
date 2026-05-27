<?php

namespace App\Services;

use App\Models\BloqueoRegistroCategoria;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class RegistroCategoriaLockService
{
    private const EXPIRATION_SECONDS = 60;

    public function tomar(User $juez, int $categoriaId, ?int $asignacionJuezId, ?string $sessionId): array
    {
        if (! $this->tablaDisponible()) {
            return $this->sinBloqueo();
        }

        $this->expirarVencidos($categoriaId);

        try {
            return DB::transaction(function () use ($juez, $categoriaId, $asignacionJuezId, $sessionId) {
                $activo = BloqueoRegistroCategoria::query()
                    ->with('juez:id,name,last_name,email')
                    ->where('categoria_id', $categoriaId)
                    ->where('estado', 'activo')
                    ->lockForUpdate()
                    ->first();

                if (! $activo) {
                    $activo = BloqueoRegistroCategoria::query()->create([
                        'categoria_id' => $categoriaId,
                        'juez_user_id' => $juez->id,
                        'asignacion_juez_id' => $asignacionJuezId,
                        'session_id' => $sessionId,
                        'estado' => 'activo',
                        'bloqueado_desde' => now(),
                        'ultimo_ping_at' => now(),
                    ])->load('juez:id,name,last_name,email');

                    return $this->serializar($activo, $juez);
                }

                if ((int) $activo->juez_user_id !== (int) $juez->id) {
                    $this->lanzarBloqueoDeOtroJuez($activo);
                }

                $activo->forceFill([
                    'asignacion_juez_id' => $asignacionJuezId ?? $activo->asignacion_juez_id,
                    'session_id' => $sessionId,
                    'ultimo_ping_at' => now(),
                ])->save();

                return $this->serializar($activo->fresh('juez:id,name,last_name,email'), $juez);
            });
        } catch (QueryException $exception) {
            $this->expirarVencidos($categoriaId);
            $activo = BloqueoRegistroCategoria::query()
                ->with('juez:id,name,last_name,email')
                ->where('categoria_id', $categoriaId)
                ->where('estado', 'activo')
                ->first();

            if ($activo && (int) $activo->juez_user_id === (int) $juez->id) {
                return $this->serializar($activo, $juez);
            }

            if ($activo) {
                $this->lanzarBloqueoDeOtroJuez($activo);
            }

            throw $exception;
        }
    }

    public function renovar(User $juez, int $categoriaId, ?int $asignacionJuezId, ?string $sessionId): array
    {
        return $this->tomar($juez, $categoriaId, $asignacionJuezId, $sessionId);
    }

    public function asegurarDisponibleParaJuez(User $juez, int $categoriaId, ?int $asignacionJuezId, ?string $sessionId): array
    {
        return $this->tomar($juez, $categoriaId, $asignacionJuezId, $sessionId);
    }

    public function liberarCategoria(User $juez, int $categoriaId, ?string $sessionId = null, string $motivo = 'manual'): void
    {
        if (! $this->tablaDisponible()) {
            return;
        }

        $query = BloqueoRegistroCategoria::query()
            ->where('categoria_id', $categoriaId)
            ->where('juez_user_id', $juez->id)
            ->where('estado', 'activo');

        if ($sessionId !== null) {
            $query->where(function ($inner) use ($sessionId) {
                $inner->whereNull('session_id')->orWhere('session_id', $sessionId);
            });
        }

        $query->update([
            'estado' => 'liberado',
            'liberado_at' => now(),
            'motivo_liberacion' => $motivo,
            'updated_at' => now(),
        ]);
    }

    public function liberarActivosDelJuez(User $juez, string $motivo = 'logout'): void
    {
        if (! $this->tablaDisponible()) {
            return;
        }

        BloqueoRegistroCategoria::query()
            ->where('juez_user_id', $juez->id)
            ->where('estado', 'activo')
            ->update([
                'estado' => 'liberado',
                'liberado_at' => now(),
                'motivo_liberacion' => $motivo,
                'updated_at' => now(),
            ]);
    }

    private function expirarVencidos(int $categoriaId): void
    {
        if (! $this->tablaDisponible()) {
            return;
        }

        BloqueoRegistroCategoria::query()
            ->where('categoria_id', $categoriaId)
            ->where('estado', 'activo')
            ->where(function ($query) {
                $query
                    ->whereNull('ultimo_ping_at')
                    ->orWhere('ultimo_ping_at', '<', now()->subSeconds(self::EXPIRATION_SECONDS));
            })
            ->update([
                'estado' => 'expirado',
                'liberado_at' => now(),
                'motivo_liberacion' => 'timeout',
                'updated_at' => now(),
            ]);
    }

    private function lanzarBloqueoDeOtroJuez(BloqueoRegistroCategoria $bloqueo): void
    {
        $nombre = $this->nombreJuez($bloqueo->juez);

        throw ValidationException::withMessages([
            'categoria_id' => "Esta categoria esta siendo registrada por {$nombre}.",
        ]);
    }

    private function serializar(BloqueoRegistroCategoria $bloqueo, User $juez): array
    {
        return [
            'activo' => true,
            'bloqueado' => (int) $bloqueo->juez_user_id !== (int) $juez->id,
            'categoria_id' => (int) $bloqueo->categoria_id,
            'juez_user_id' => (int) $bloqueo->juez_user_id,
            'juez_nombre' => $this->nombreJuez($bloqueo->juez),
            'session_id' => $bloqueo->session_id,
            'estado' => (string) $bloqueo->estado,
            'ultimo_ping_at' => optional($bloqueo->ultimo_ping_at)?->toIso8601String(),
            'expires_in_seconds' => self::EXPIRATION_SECONDS,
        ];
    }

    private function nombreJuez(?User $juez): string
    {
        $nombre = trim((string) ($juez?->name ?? '') . ' ' . (string) ($juez?->last_name ?? ''));

        return $nombre !== '' ? $nombre : 'otro juez';
    }

    private function tablaDisponible(): bool
    {
        try {
            return Schema::hasTable('vinculaciones.bloqueos_registro_categoria');
        } catch (QueryException) {
            return false;
        }
    }

    private function sinBloqueo(): array
    {
        return [
            'activo' => false,
            'bloqueado' => false,
        ];
    }
}
