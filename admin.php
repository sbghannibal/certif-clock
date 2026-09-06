<?php
/**
 * Admin-dashboard: klokken, certificaties starten/stoppen en de historiek.
 * Deze pagina is nooit toegankelijk zonder login: de guard hieronder stuurt
 * niet-aangemelde bezoekers meteen door naar de loginpagina.
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

            case 'pause':
                pause_certification((int) ($_POST['certification_id'] ?? 0));
                flash('success', t('flash.certification_paused'));
                break;

            case 'resume':
                resume_certification((int) ($_POST['certification_id'] ?? 0));
                flash('success', t('flash.certification_resumed'));
                break;

            case 'extend':
                $extensionSeconds = isset($_POST['extension_minutes']) && $_POST['extension_minutes'] !== ''
                    ? (int) round(((float) $_POST['extension_minutes']) * 60)
                    : (int) ($_POST['extension_seconds'] ?? 0);
                extend_certification(
                    (int) ($_POST['certification_id'] ?? 0),
                    $extensionSeconds,
                    (int) $user['id']
                );
                flash('success', t('flash.certification_extended'));
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

auto_close_expired_certifications();

$locations = list_locations();
$selectedLocation = resolve_active_location(isset($_GET['location']) ? (string) $_GET['location'] : null);

if (($_GET['format'] ?? '') === 'json') {
    json_response([
        'boards' => $selectedLocation !== null ? all_board_states($selectedLocation) : [],
    ]);
}

render('admin', [
    'user' => $user,
    'locations' => $locations,
    'selectedLocation' => $selectedLocation,
    'boards' => $selectedLocation !== null ? all_board_states($selectedLocation) : [],
    'history' => certification_history(50, $selectedLocation['id'] ?? null),
    'defaultDuration' => config('app')['default_duration_minutes'],
]);
