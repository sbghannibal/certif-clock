<?php
/** Algemene helpers: configuratie, escaping en redirects. */

declare(strict_types=1);

if (!defined('CERTIF_CLOCK')) {
    http_response_code(403);
    exit('Directe toegang is niet toegestaan.');
}

function config(?string $key = null)
{
    static $config = null;
    if ($config === null) {
        $config = require dirname(__DIR__) . '/config.php';
    }
    if ($key === null) {
        return $config;
    }

    return $config[$key] ?? null;
}

/** Escapet gebruikersinhoud voor weergave in HTML. */
function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function redirect(string $path): void
{
    header('Location: ' . $path);
    exit;
}

function json_response(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function flash(string $type, ?string $message = null): ?string
{
    if ($message !== null) {
        $_SESSION['flash'][$type] = $message;

        return null;
    }
    $value = $_SESSION['flash'][$type] ?? null;
    unset($_SESSION['flash'][$type]);

    return $value;
}

function base_url(): string
{
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

    return $scheme . '://' . $host;
}

function format_datetime(?string $value): string
{
    if ($value === null || $value === '') {
        return '—';
    }
    $time = strtotime($value);

    return $time === false ? '—' : date('d/m/Y H:i', $time);
}

function render(string $view, array $data = []): void
{
    extract($data, EXTR_SKIP);
    require dirname(__DIR__) . '/views/' . $view . '.php';
}
