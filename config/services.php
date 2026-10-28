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

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    // Asistente virtual SKAI (Google Gemini). La key NUNCA debe ir en el
    // código fuente: se lee solo de la variable de entorno GEMINI_API_KEY.
    'gemini' => [
        'key' => env('GEMINI_API_KEY'),
        'model' => env('GEMINI_MODEL', 'gemini-3.6-flash'),
        // Modelo de respaldo cuando el principal está saturado (503/429),
        // tarda demasiado o fue descontinuado (404). El alias "-latest"
        // siempre apunta a la versión vigente, así no se rompe cuando
        // Google retira un modelo. Déjalo vacío para no usar respaldo.
        'fallback_model' => env('GEMINI_FALLBACK_MODEL', 'gemini-flash-lite-latest'),
        'max_tokens' => (int) env('GEMINI_MAX_TOKENS', 900),
        // Los modelos 3.x "piensan" antes de responder (~400 tokens,
        // 5-15 s). Para un chat corto "minimal" responde en 1-3 s.
        // Valores: minimal, low, medium, high. Vacío = el del modelo.
        'thinking_level' => env('GEMINI_THINKING_LEVEL', 'minimal'),
        'timeout' => (int) env('GEMINI_TIMEOUT', 25),
    ],

];
