<?php

return [
    'paths' => ['api/*'],
    'allowed_methods' => ['*'],
    // api-contract §4: solo el origen del frontend configurado por entorno.
    'allowed_origins' => [env('CORS_ORIGIN', 'http://localhost:5173')],
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 0,
    // La autenticación va en la cabecera Authorization, no en cookies.
    'supports_credentials' => false,
];
