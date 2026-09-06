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
                flash('success', t('flash.certification_started'));
                break;

            case 'stop':
                stop_certification((int) ($_POST['certification_id'] ?? 0));
                flash('success', t('flash.certification_stopped'));
                break;

            case 'create_user':
                require_owner();
                $created = create_user(
                    (string) ($_POST['username'] ?? ''),
                    (string) ($_POST['password'] ?? ''),
                    (string) ($_POST['role'] ?? 'expert'),
                    (string) ($_POST['language'] ?? DEFAULT_LANGUAGE)
                );
                if ($created['password'] !== null) {
                    flash('generated_password', $created['password']);
                    flash('success', t('flash.user_created_generated'));
                } else {
                    flash('success', t('flash.user_created'));
                }
                break;

            case 'delete_user':
                require_owner();
                delete_user((int) ($_POST['user_id'] ?? 0), (int) $user['id']);
                flash('success', t('flash.user_deleted'));
                break;

            case 'change_password':
                change_password(
                    (int) $user['id'],
                    (string) ($_POST['current_password'] ?? ''),
                    (string) ($_POST['new_password'] ?? ''),
                    (string) ($_POST['confirm_password'] ?? '')
                );
                flash('success', t('flash.password_changed'));
                break;

            case 'change_language':
                change_language((int) $user['id'], (string) ($_POST['language'] ?? DEFAULT_LANGUAGE));
                init_i18n();
                flash('success', t('flash.language_saved'));
                break;

            case 'create_location':
                require_owner();
                create_location((string) ($_POST['name'] ?? ''), (string) ($_POST['default_language'] ?? DEFAULT_LANGUAGE));
                flash('success', t('flash.location_added'));
                break;

            case 'rename_location':
                require_owner();
                rename_location(
                    (int) ($_POST['location_id'] ?? 0),
                    (string) ($_POST['name'] ?? ''),
                    (string) ($_POST['default_language'] ?? DEFAULT_LANGUAGE)
                );
                flash('success', t('flash.location_updated'));
                break;

            case 'delete_location':
                require_owner();
                delete_location((int) ($_POST['location_id'] ?? 0));
                flash('success', t('flash.location_deleted'));
                break;

            default:
                flash('error', t('flash.unknown_action'));
        }
    } catch (InvalidArgumentException $exception) {
        flash('error', $exception->getMessage());
    } catch (PDOException $exception) {
        flash('error', t('flash.action_failed_user_certifications'));
    }

    $redirectLocation = (string) ($_POST['location'] ?? '');
    if ($action === 'start') {
        // Na het starten meteen de locatie tonen waarvoor gestart werd, ook als
        // die verschilt van de eerder bekeken/gefilterde locatie.
        $startedLocation = find_location_by_id((int) ($_POST['location_id'] ?? 0));
        if ($startedLocation !== null) {
            $redirectLocation = $startedLocation['slug'];
        }
    }
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
