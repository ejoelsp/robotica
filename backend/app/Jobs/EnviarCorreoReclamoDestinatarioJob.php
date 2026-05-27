<?php

namespace App\Jobs;

use App\Models\Incidencia;
use App\Services\BrevoMailService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class EnviarCorreoReclamoDestinatarioJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    /**
     * @var array<int, int>
     */
    public array $backoff = [60, 300, 900];

    public function __construct(
        private readonly int $reclamoId,
        private readonly string $email,
        private readonly string $nombre
    ) {
        $this->onQueue('emails');
    }

    public function handle(BrevoMailService $brevoMailService): void
    {
        $reclamo = Incidencia::query()
            ->with('categoria:id,competencia_id')
            ->find($this->reclamoId);

        if (! $reclamo || $reclamo->tipo !== 'reclamo') {
            return;
        }

        $documento = [
            'codigo' => $reclamo->codigo,
            'fecha' => optional($reclamo->fecha_envio ?? $reclamo->created_at)?->format('d/m/Y H:i'),
            'evento' => [
                'id' => $reclamo->categoria?->competencia_id,
                'nombre' => $reclamo->equipo_snapshot['evento'] ?? 'Evento',
                'logo_url' => $reclamo->equipo_snapshot['logo_url'] ?? null,
            ],
            'categoria' => [
                'nombre' => $reclamo->equipo_snapshot['categoria'] ?? 'Categoría',
            ],
            'equipo' => $reclamo->equipo_snapshot ?? [],
            'integrantes' => $reclamo->integrantes_snapshot ?? [],
            'jueces' => $reclamo->jueces_snapshot ?? [],
            'prototipo_nombre' => $reclamo->prototipo_nombre,
            'institucion' => $reclamo->institucion,
            'descripcion' => $reclamo->descripcion,
        ];

        $html = view('reclamos.email', [
            'reclamo' => $documento,
            'codigo' => $reclamo->codigo,
            'url' => route('competidor.reclamos.formato', $reclamo),
        ])->render();

        $brevoMailService->sendEmail(
            $this->email,
            $this->nombre !== '' ? $this->nombre : 'Comité organizador',
            "Nuevo reclamo {$reclamo->codigo}",
            $html
        );
    }

    public function failed(Throwable $exception): void
    {
        Log::error('No se pudo enviar el correo de reclamo a un destinatario.', [
            'reclamo_id' => $this->reclamoId,
            'email' => $this->email,
            'message' => $exception->getMessage(),
        ]);
    }
}
