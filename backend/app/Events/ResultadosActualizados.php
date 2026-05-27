<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ResultadosActualizados implements ShouldBroadcastNow
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly int $competenciaId,
        public readonly int $categoriaId,
        public readonly int $rondaId,
        public readonly string $estadoPublicacion,
        public readonly bool $rondaCompleta = false
    ) {
    }

    public function broadcastOn(): Channel
    {
        return new Channel("resultados.competencia.{$this->competenciaId}");
    }

    public function broadcastAs(): string
    {
        return 'ResultadosActualizados';
    }

    public function broadcastWith(): array
    {
        return [
            'competencia_id' => $this->competenciaId,
            'categoria_id' => $this->categoriaId,
            'ronda_id' => $this->rondaId,
            'estado_publicacion' => $this->estadoPublicacion,
            'ronda_completa' => $this->rondaCompleta,
            'actualizado_at' => now()->toIso8601String(),
        ];
    }
}
