<?php
/**
 * Bootstrapping van de applicatie: configuratie, sessie, database en de
 * automatische "admin install" van het owner-account bij de allereerste start.
 */

declare(strict_types=1);

if (!defined('CERTIF_CLOCK')) {
    http_response_code(403);
    exit('Directe toegang is niet toegestaan.');
}

require_once __DIR__ . '/env.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/i18n.php';
require_once __DIR__ . '/rate_limit.php';
require_once __DIR__ . '/locations.php';
require_once __DIR__ . '/certifications.php';
require_once __DIR__ . '/users.php';

/**
 * Maakt bij de eerste start automatisch het owner-account aan op basis van
 * OWNER_USERNAME en OWNER_PASSWORD. Daarna gebeurt dit nooit opnieuw.
 */
function ensure_owner_bootstrap(): void
{
    $count = (int) db()->query('SELECT COUNT(*) FROM users')->fetchColumn();
    if ($count > 0) {
        return;
    }

    $owner = config('owner');
    $password = (string) $owner['password'];
    if ($password === '') {
        throw new RuntimeException(
            'Eerste installatie: zet OWNER_USERNAME en OWNER_PASSWORD in je .env bestand '
            . '(of als omgevingsvariabelen) zodat het owner-account aangemaakt kan worden.'
        );
    }
    if (strlen($password) < 8) {
        throw new RuntimeException('OWNER_PASSWORD moet minstens 8 tekens lang zijn.');
    }

    $statement = db()->prepare(
        "INSERT INTO users (username, password_hash, role) VALUES (?, ?, 'owner')"
    );
    try {
        $statement->execute([(string) $owner['username'], password_hash($password, PASSWORD_DEFAULT)]);
    } catch (PDOException $exception) {
        // Een parallelle eerste request kan de owner al aangemaakt hebben.
        if ($exception->getCode() !== '23000') {
            throw $exception;
        }
    }
}

function app_boot(): void
{
    session_start_secure();
    rate_limit_check();

    try {
        if (!db_schema_installed()) {
            throw new RuntimeException(
                'De databasetabellen ontbreken. Importeer eerst database/schema.sql in je MySQL-database.'
            );
        }
        ensure_owner_bootstrap();
        init_i18n();
    } catch (RuntimeException $exception) {
        app_fail($exception->getMessage());
    }
}

function app_fail(string $message): void
{
    http_response_code(503);
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
    render('setup', ['message' => $message]);
    exit;
}
