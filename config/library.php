<?php

/*
 * Parámetros propios de Manager Library. Todo se lee del entorno aquí, y no desde el
 * código, para que `php artisan config:cache` no los pierda.
 */
return [
    // Secreto de firma del JWT (api-contract §4). Sin valor por defecto: sin él la API no emite tokens.
    'jwt_secret' => env('JWT_SECRET'),

    // Administrador inicial (spec §1, data-model §9). Lo crea `library:bootstrap-admin`.
    'admin_username' => env('ADMIN_USERNAME'),
    'admin_password' => env('ADMIN_PASSWORD'),

    // Carpeta de los binarios de portada.
    'covers_dir' => env('COVERS_DIR'),

    // Solo para demostraciones: permite que el sembrador de `tool` registre préstamos con
    // fecha pasada mediante la cabecera X-Simulated-Time. En producción debe ser false.
    'allow_simulated_time' => filter_var(env('ALLOW_SIMULATED_TIME', false), FILTER_VALIDATE_BOOLEAN),
];
