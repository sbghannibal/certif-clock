<?php
/** Publieke installer, zonder de normale applicatiebootstrap uit te voeren. */

declare(strict_types=1);

define('CERTIF_CLOCK', true);
require_once __DIR__ . '/app/helpers.php';
require_once __DIR__ . '/app/db.php';
require_once __DIR__ . '/app/auth.php';
require_once __DIR__ . '/app/rate_limit.php';
require_once __DIR__ . '/app/install.php';

session_start_secure();
rate_limit_check();
header('Cache-Control: no-store');
header('Content-Type: text/html; charset=utf-8');

$error = null;
$success = false;
$available = false;
$username = is_string($_POST['username'] ?? null) ? trim($_POST['username']) : '';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
try {
    if ($method === 'POST') {
        rate_limit_check('install', 10, 60);
        // Valideer voor de verbinding en iedere mogelijke schemawijziging.
        installer_validate($_POST);
        installer_install(db(), $_POST);
        $success = true;
    } elseif ($method !== 'GET') {
        header('Allow: GET, POST');
        http_response_code(405);
    }
    $available = installer_available(db());
    if (!$available && !$success && $method !== 'GET') {
        http_response_code(403);
    }
} catch (InvalidArgumentException $exception) {
    http_response_code(422);
    $error = $exception->getMessage();
    try {
        $available = installer_available(db());
    } catch (Throwable $exception) {
        $error = 'De database kan niet veilig worden gecontroleerd. Controleer de DB-configuratie.';
    }
} catch (Throwable $exception) {
    http_response_code(503);
    $error = 'Installatie niet mogelijk: de database is niet leeg, de installatie is gesloten of er is een databasefout. '
        . 'Controleer de DB-configuratie en laat zo nodig de beheerder de installatie nakijken.';
}

render('install', compact('error', 'success', 'available', 'username'));
