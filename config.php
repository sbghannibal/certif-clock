<?php
/**
 * Centrale configuratie. Waarden komen uit omgevingsvariabelen of uit een .env
 * bestand in de basismap (zie .env.example).
 */

declare(strict_types=1);

if (!defined('CERTIF_CLOCK')) {
    http_response_code(403);
    exit('Directe toegang is niet toegestaan.');
}

require_once __DIR__ . '/app/env.php';

env_load(__DIR__ . '/.env');

return [
    'db' => [
        'host' => env('DB_HOST', '127.0.0.1'),
        'port' => (int) env('DB_PORT', '3306'),
        'name' => env('DB_NAME', 'certif_clock'),
        'user' => env('DB_USER', 'certif_clock'),
        'password' => env('DB_PASSWORD', ''),
        'charset' => env('DB_CHARSET', 'utf8mb4'),
    ],
    'owner' => [
        'username' => env('OWNER_USERNAME', 'owner'),
        'password' => env('OWNER_PASSWORD', ''),
    ],
    'session' => [
        'name' => env('SESSION_NAME', 'certif_clock_session'),
        'secure_cookies' => env('SECURE_COOKIES', 'false') === 'true',
        'lifetime' => (int) env('SESSION_LIFETIME', (string) (8 * 3600)),
    ],
    'app' => [
        'board_count' => 3,
        'default_duration_minutes' => 120,
        'max_duration_minutes' => 24 * 60,
        'rate_limit_max' => (int) env('RATE_LIMIT_MAX', '300'),
        'rate_limit_window' => (int) env('RATE_LIMIT_WINDOW', '60'),
    ],
];
