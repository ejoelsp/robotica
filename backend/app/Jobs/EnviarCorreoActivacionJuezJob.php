<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\BrevoMailService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class EnviarCorreoActivacionJuezJob implements ShouldBeEncrypted, ShouldQueue
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
        private readonly int $juezId,
        private readonly string $activationUrl
    ) {
        $this->onQueue('emails');
    }

    public function handle(BrevoMailService $brevoMailService): void
    {
        $juez = User::query()->find($this->juezId);

        if (! $juez || ! $juez->email) {
            return;
        }

        if ($juez->email_verified_at !== null && ! $juez->must_change_password) {
            return;
        }

        $html = "
            <h2>Activación de cuenta</h2>
            <p>Hola {$juez->name} {$juez->last_name},</p>
            <p>Se ha creado tu cuenta como juez en el sistema del Club de Robótica ESPOCH.</p>
            <p>Para activar tu cuenta y definir tu contraseña, haz clic en el siguiente enlace:</p>
            <p><a href='{$this->activationUrl}' target='_blank'>Activar cuenta</a></p>
            <p>Este enlace caduca en 24 horas.</p>
        ";

        $brevoMailService->sendEmail(
            (string) $juez->email,
            trim((string) $juez->name . ' ' . (string) $juez->last_name),
            'Activa tu cuenta - Club de Robótica ESPOCH',
            $html
        );
    }

    public function failed(Throwable $exception): void
    {
        Log::error('No se pudo enviar el correo de activación de juez.', [
            'juez_id' => $this->juezId,
            'message' => $exception->getMessage(),
        ]);
    }
}
