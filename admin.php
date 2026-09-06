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
                $created = create_user(
                    (string) ($_POST['username'] ?? ''),
                    (string) ($_POST['password'] ?? ''),
                    (string) ($_POST['role'] ?? 'expert')
                );
                if ($created['password'] !== null) {
                    flash('generated_password', $created['password']);
                    flash('success', 'Gebruiker aangemaakt. Het automatisch gegenereerde wachtwoord staat hieronder.');
                } else {
                    flash('success', 'Gebruiker aangemaakt.');
                }
                break;

            case 'delete_user':
                require_owner();
                delete_user((int) ($_POST['user_id'] ?? 0), (int) $user['id']);
                flash('success', 'Gebruiker verwijderd.');
                break;

            case 'change_password':
                change_password(
                    (int) $user['id'],
                    (string) ($_POST['current_password'] ?? ''),
                    (string) ($_POST['new_password'] ?? ''),
                    (string) ($_POST['confirm_password'] ?? '')
                );
                flash('success', 'Wachtwoord gewijzigd.');
                break;

            case 'create_location':
                require_owner();
                create_location((string) ($_POST['name'] ?? ''));
                flash('success', 'Locatie toegevoegd.');
                break;

            case 'rename_location':
                require_owner();
                rename_location((int) ($_POST['location_id'] ?? 0), (string) ($_POST['name'] ?? ''));
                flash('success', 'Locatie aangepast.');
                break;

            case 'delete_location':
                require_owner();
                delete_location((int) ($_POST['location_id'] ?? 0));
                flash('success', 'Locatie verwijderd.');
                break;

            default:
                flash('error', 'Onbekende actie.');
        }
    } catch (InvalidArgumentException $exception) {
        flash('error', $exception->getMessage());
    } catch (PDOException $exception) {
        flash('error', 'Actie mislukt: deze gebruiker heeft nog certificaties op zijn naam staan.');
    }

    $redirectLocation = (string) ($_POST['location'] ?? '');
    $suffix = $redirectLocation !== '' ? '?location=' . rawurlencode($redirectLocation) : '';
    redirect('/admin.php' . $suffix);
}

$locations = list_locations();
$selectedLocation = resolve_location(isset($_GET['location']) ? (string) $_GET['location'] : null);

render('admin', [
    'user' => $user,
    'locations' => $locations,
    'selectedLocation' => $selectedLocation,
    'boards' => $selectedLocation !== null ? all_board_states($selectedLocation) : [],
    'history' => certification_history(50, $selectedLocation['id'] ?? null),
    'users' => is_owner() ? list_users() : [],
    'defaultDuration' => config('app')['default_duration_minutes'],
    'generatedPassword' => flash('generated_password'),
]);
