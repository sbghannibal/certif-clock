<?php
/** Publieke startpagina met een overzicht van de drie borden. */

declare(strict_types=1);

define('CERTIF_CLOCK', true);
require_once __DIR__ . '/app/bootstrap.php';

app_boot();

render('home', [
    'boards' => all_board_states(),
    'user' => current_user(),
]);
