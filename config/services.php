<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    // All CAMP emails go out through the SMTP2GO HTTP API (App\Services\EmailService).
    // The sender address must be on a domain verified in SMTP2GO.
    'smtp2go' => [
        'api_key' => env('SMTP2GO_API_KEY'),
        'sender_email' => env('SMTP2GO_SENDER_EMAIL'),
        'sender_name' => env('SMTP2GO_SENDER_NAME', 'Sha-Shib CAMPUS'),
        'endpoint' => env('SMTP2GO_ENDPOINT', 'https://api.smtp2go.com/v3/email/send'),
        'timeout' => (int) env('SMTP2GO_TIMEOUT', 20),
    ],

];
