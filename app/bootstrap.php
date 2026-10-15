<?php
/**
 * Bootstrapping van de applicatie: configuratie, sessie en database.
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
require_once __DIR__ . '/settings.php';
require_once __DIR__ . '/certifications.php';
require_once __DIR__ . '/users.php';

function app_boot(): void
{
    session_start_secure();
    rate_limit_check();

    try {
        if (!db_schema_installed() || !db()->query('SELECT id FROM users LIMIT 1')->fetchColumn()) {
            throw new RuntimeException(
                'De installatie ontbreekt of er zijn geen gebruikers. Bezoek /install.php voor de installatieprocedure. '
                . 'Bij bestaande gegevens moet de beheerder de toegang herstellen.'
            );
        }
        init_i18n();
    } catch (PDOException $exception) {
        app_fail('De database kon niet worden gelezen. Controleer de configuratie en installatie.');
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
