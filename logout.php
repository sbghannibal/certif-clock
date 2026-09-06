<?php
/** Afmelden (POST met CSRF-token). */

declare(strict_types=1);

define('CERTIF_CLOCK', true);
require_once __DIR__ . '/app/bootstrap.php';

app_boot();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    logout();
}

redirect('/login.php');
