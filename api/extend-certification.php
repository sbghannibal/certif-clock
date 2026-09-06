<?php
/** AJAX-endpoint: voegt extra tijd toe aan een lopende certificatie. */

declare(strict_types=1);

define('CERTIF_CLOCK', true);
require_once __DIR__ . '/../app/bootstrap.php';

app_boot();

$user = require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'Methode niet toegestaan.'], 405);
}

require_csrf();

$certificationId = (int) ($_POST['certification_id'] ?? 0);
$extensionSeconds = (int) ($_POST['extension_seconds'] ?? 0);

try {
    $endsAt = extend_certification($certificationId, $extensionSeconds, (int) $user['id']);
    json_response(['endsAt' => $endsAt]);
} catch (InvalidArgumentException $exception) {
    json_response(['error' => $exception->getMessage()], 400);
} catch (PDOException $exception) {
    json_response(['error' => 'Actie mislukt.'], 500);
}
