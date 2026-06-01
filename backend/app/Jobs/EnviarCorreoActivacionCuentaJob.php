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

class EnviarCorreoActivacionCuentaJob implements ShouldBeEncrypted, ShouldQueue
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
        private readonly int $userId,
        private readonly string $activationUrl
    ) {
        $this->onQueue('emails');
    }

    public function handle(BrevoMailService $brevoMailService): void
    {
        $user = User::query()->find($this->userId);

        if (! $user || ! $user->email) {
            return;
        }

        if ($user->email_verified_at !== null && ! $user->must_change_password) {
            return;
        }

        $fullName = trim((string) $user->name . ' ' . (string) $user->last_name);

        $html = "
            <h2>Activación de cuenta</h2>
            <p>Hola {$fullName},</p>
            <p>Se ha creado tu cuenta en el sistema del Club de Robótica ESPOCH.</p>
            <p>Para activar tu cuenta y definir tu contraseña, haz clic en el siguiente enlace:</p>
            <p><a href='{$this->activationUrl}' target='_blank'>Activar cuenta</a></p>
            <p>Este enlace caduca en 24 horas.</p>
        ";

        $brevoMailService->sendEmail(
            (string) $user->email,
            $fullName,
            'Activa tu cuenta - Club de Robótica ESPOCH',
            $html
        );
    }

    public function failed(Throwable $exception): void
    {
        Log::error('No se pudo enviar el correo de activación de cuenta.', [
            'user_id' => $this->userId,
            'message' => $exception->getMessage(),
        ]);
    }
}
