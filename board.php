<?php
/** Publieke klokweergave van één bord (directe link voor de kandidaat). */

declare(strict_types=1);

define('CERTIF_CLOCK', true);
require_once __DIR__ . '/app/bootstrap.php';

app_boot();

$location = resolve_location(isset($_GET['location']) ? (string) $_GET['location'] : null);
if ($location === null) {
    http_response_code(404);
    render('error', ['message' => 'Onbekende locatie.']);
    exit;
}

$board = $_GET['board'] ?? 1;
if (!is_valid_board($board)) {
    http_response_code(404);
    render('error', ['message' => 'Onbekend bord.']);
    exit;
}
$board = (int) $board;
$state = board_state($location, $board);

if (($_GET['format'] ?? '') === 'json') {
    json_response($state);
}

render('board', [
    'state' => $state,
    'user' => current_user(),
]);
