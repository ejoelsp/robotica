<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Exception;

class BrevoMailService
{
    public function sendEmail(string $toEmail, string $toName, string $subject, string $htmlContent): array
    {
        $response = Http::withHeaders([
            'api-key' => config('brevo.api_key'),
            'accept' => 'application/json',
            'content-type' => 'application/json',
        ])->post(config('brevo.base_url') . '/smtp/email', [
            'sender' => [
                'name' => config('brevo.sender_name'),
                'email' => config('brevo.sender_email'),
            ],
            'to' => [
                [
                    'email' => $toEmail,
                    'name' => $toName,
                ]
            ],
            'subject' => $subject,
            'htmlContent' => $htmlContent,
        ]);

        if ($response->failed()) {
            throw new Exception('Error Brevo: ' . $response->body());
        }

        return $response->json();
    }
}