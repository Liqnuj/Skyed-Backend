<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Aquí se define qué orígenes (dominios) pueden llamar esta API desde
    | el navegador. Ajusta FRONTEND_URL en tu .env con la URL real donde
    | corre el frontend en React (por defecto Vite usa localhost:5173,
    | Create React App usa localhost:3000).
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_filter([
        env('FRONTEND_URL', 'http://localhost:5173'),
        'http://localhost:3000',
        'http://127.0.0.1:5173',
        'http://127.0.0.1:3000',
    ]),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    // true porque Sanctum puede necesitar enviar la cookie de sesión;
    // si el frontend solo usa el Bearer token (Authorization header),
    // esto puede quedar en false sin problema.
    'supports_credentials' => true,

];
