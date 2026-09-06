<?php
/** Sessie-authenticatie, rolcontroles en CSRF-bescherming. */

declare(strict_types=1);

if (!defined('CERTIF_CLOCK')) {
    http_response_code(403);
    exit('Directe toegang is niet toegestaan.');
}

function session_start_secure(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    $session = config('session');
    session_name($session['name']);
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => (bool) $session['secure_cookies'],
    ]);
    session_start();

    $now = time();
    if (isset($_SESSION['last_activity']) && $now - (int) $_SESSION['last_activity'] > $session['lifetime']) {
        $_SESSION = [];
        session_regenerate_id(true);
    }
    $_SESSION['last_activity'] = $now;
}

function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function is_logged_in(): bool
{
    return current_user() !== null;
}

function is_owner(): bool
{
    $user = current_user();

    return $user !== null && $user['role'] === 'owner';
}

/** Harde guard: zonder login nooit toegang tot een beveiligde pagina. */
function require_login(): array
{
    $user = current_user();
    if ($user === null) {
        redirect('/login.php?next=' . rawurlencode($_SERVER['REQUEST_URI'] ?? '/admin.php'));
    }

    return $user;
}

function require_owner(): array
{
    $user = require_login();
    if ($user['role'] !== 'owner') {
        http_response_code(403);
        exit('Alleen een owner mag dit doen.');
    }

    return $user;
}

function attempt_login(string $username, string $password): bool
{
    $statement = db()->prepare('SELECT id, username, password_hash, role FROM users WHERE username = ?');
    $statement->execute([$username]);
    $row = $statement->fetch();

    if (!$row || !password_verify($password, $row['password_hash'])) {
        return false;
    }

    session_regenerate_id(true);
    $_SESSION['user'] = [
        'id' => (int) $row['id'],
        'username' => $row['username'],
        'role' => $row['role'],
    ];

    return true;
}

function logout(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function csrf_verify(?string $token): bool
{
    $expected = $_SESSION['csrf_token'] ?? '';

    return $expected !== '' && is_string($token) && hash_equals($expected, $token);
}

/** Blokkeert POST-acties zonder geldig CSRF-token. */
function require_csrf(): void
{
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        http_response_code(403);
        exit('Ongeldig of ontbrekend CSRF-token.');
    }
}
