<?php
/**
 * Admin-dashboard. Deze pagina is nooit toegankelijk zonder login: de guard
 * hieronder stuurt niet-aangemelde bezoekers meteen door naar de loginpagina.
 */

declare(strict_types=1);

define('CERTIF_CLOCK', true);
require_once __DIR__ . '/app/bootstrap.php';

app_boot();

$user = require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = (string) ($_POST['action'] ?? '');

    try {
        switch ($action) {
            case 'start':
                start_certification($_POST, (int) $user['id']);
                flash('success', 'Certificatie gestart.');
                break;

            case 'stop':
                stop_certification((int) ($_POST['certification_id'] ?? 0));
                flash('success', 'Certificatie gestopt.');
                break;

            case 'create_user':
                require_owner();
                create_user(
                    (string) ($_POST['username'] ?? ''),
                    (string) ($_POST['password'] ?? ''),
                    (string) ($_POST['role'] ?? 'expert')
                );
                flash('success', 'Gebruiker aangemaakt.');
                break;

            case 'delete_user':
                require_owner();
                delete_user((int) ($_POST['user_id'] ?? 0), (int) $user['id']);
                flash('success', 'Gebruiker verwijderd.');
                break;

            default:
                flash('error', 'Onbekende actie.');
        }
    } catch (InvalidArgumentException $exception) {
        flash('error', $exception->getMessage());
    } catch (PDOException $exception) {
        flash('error', 'Actie mislukt: deze gebruiker heeft nog certificaties op zijn naam staan.');
    }

    redirect('/admin.php');
}

render('admin', [
    'user' => $user,
    'boards' => all_board_states(),
    'history' => certification_history(50),
    'users' => is_owner() ? list_users() : [],
    'defaultDuration' => config('app')['default_duration_minutes'],
]);
