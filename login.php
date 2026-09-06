<?php
/** Aanmelden. */

declare(strict_types=1);

define('CERTIF_CLOCK', true);
require_once __DIR__ . '/app/bootstrap.php';

app_boot();

$next = (string) ($_GET['next'] ?? '/admin.php');
// Enkel interne paden toelaten (geen open redirect).
if ($next === '' || $next[0] !== '/' || str_starts_with($next, '//')) {
    $next = '/admin.php';
}

if (is_logged_in()) {
    redirect($next);
}

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    rate_limit_check('login', 10, 300);
    require_csrf();
    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    if (attempt_login($username, $password)) {
        redirect($next);
    }
    $error = 'Ongeldige gebruikersnaam of wachtwoord.';
}

render('login', [
    'error' => $error,
    'next' => $next,
    'user' => null,
]);
