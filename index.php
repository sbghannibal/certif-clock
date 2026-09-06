<?php
/** Publieke startpagina met een overzicht van de drie borden. */

declare(strict_types=1);

define('CERTIF_CLOCK', true);
require_once __DIR__ . '/app/bootstrap.php';

app_boot();

$selectedLocation = resolve_location(isset($_GET['location']) ? (string) $_GET['location'] : null);
if ($selectedLocation !== null) {
    init_i18n($selectedLocation, true);
}

render('home', [
    'locations' => list_locations(),
    'selectedLocation' => $selectedLocation,
    'boards' => $selectedLocation !== null ? all_board_states($selectedLocation) : [],
    'user' => current_user(),
]);
