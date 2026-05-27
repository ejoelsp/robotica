<?php

namespace App\Services;

use App\Models\Notificacion;

class NotificacionEmailRenderer
{
    public function render(Notificacion $notificacion): string
    {
        $texto = str_replace(['<br />', '<br/>', '<br>'], "\n", (string) $notificacion->contenido);
        $contenido = nl2br(e($texto));
        $asunto = e((string) $notificacion->asunto);

        return <<<HTML
            <div style="font-family: Arial, sans-serif; color: #0f172a; line-height: 1.5;">
                <h2 style="margin: 0 0 12px;">{$asunto}</h2>
                <p style="margin: 0 0 16px;">{$contenido}</p>
                <p style="margin: 24px 0 0; color: #475569; font-size: 13px;">
                    Club de Robótica ESPOCH
                </p>
            </div>
        HTML;
    }
}
