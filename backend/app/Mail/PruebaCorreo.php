<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PruebaCorreo extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct()
    {
        //
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Prueba de correo - Club de Robótica ESPOCH',
        );
    }

    public function content(): Content
    {
        return new Content(
            htmlString: '
                <h2>Prueba de correo</h2>
                <p>Este es un correo de prueba enviado desde Laravel con Brevo.</p>
                <p>Si recibes este mensaje, la configuración SMTP está funcionando correctamente.</p>
            ',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}