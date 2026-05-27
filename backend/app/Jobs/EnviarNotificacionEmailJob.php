<?php

namespace App\Jobs;

use App\Models\Notificacion;
use App\Services\BrevoMailService;
use App\Services\NotificacionEmailRenderer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class EnviarNotificacionEmailJob implements ShouldQueue
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
        private readonly int $notificacionId
    ) {
        $this->onQueue('emails');
    }

    public function handle(BrevoMailService $brevoMailService, NotificacionEmailRenderer $renderer): void
    {
        $notificacion = Notificacion::query()
            ->with('usuario:id,name,last_name,email')
            ->find($this->notificacionId);

        if (! $notificacion || $notificacion->estado === 'enviado') {
            return;
        }

        $email = trim((string) ($notificacion->email_destino ?: $notificacion->usuario?->email));

        if ($email === '') {
            $notificacion->update([
                'estado' => 'error',
                'error_envio' => 'La notificación no tiene correo de destino.',
            ]);

            return;
        }

        try {
            $response = $brevoMailService->sendEmail(
                $email,
                $this->nombreDestinatario($notificacion),
                (string) $notificacion->asunto,
                $renderer->render($notificacion)
            );

            $notificacion->update([
                'estado' => 'enviado',
                'enviado_en' => now(),
                'provider_message_id' => $response['messageId'] ?? null,
                'error_envio' => null,
            ]);
        } catch (Throwable $exception) {
            $notificacion->update([
                'estado' => 'error',
                'reintentos' => DB::raw('reintentos + 1'),
                'error_envio' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    public function failed(Throwable $exception): void
    {
        Log::warning('No se pudo enviar correo de notificación.', [
            'notificacion_id' => $this->notificacionId,
            'message' => $exception->getMessage(),
        ]);
    }

    private function nombreDestinatario(Notificacion $notificacion): string
    {
        $nombre = trim((string) $notificacion->usuario?->name . ' ' . (string) $notificacion->usuario?->last_name);

        return $nombre !== '' ? $nombre : 'Participante';
    }
}
