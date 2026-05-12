<?php

return [
    'api_key' => env('BREVO_API_KEY'),
    'sender_email' => env('BREVO_SENDER_EMAIL'),
    'sender_name' => env('BREVO_SENDER_NAME', 'Club de Robótica ESPOCH'),
    'base_url' => 'https://api.brevo.com/v3',
];