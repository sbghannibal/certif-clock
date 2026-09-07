<?php
/** Beheerpagina voor de owner: locaties en gebruikers. */

declare(strict_types=1);

define('CERTIF_CLOCK', true);
require_once __DIR__ . '/app/bootstrap.php';

app_boot();

$user = require_login();
if (!is_owner()) {
    redirect('/admin.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = (string) ($_POST['action'] ?? '');

    try {
        switch ($action) {
            case 'create_user':
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
                delete_user((int) ($_POST['user_id'] ?? 0), (int) $user['id']);
                flash('success', t('flash.user_deleted'));
                break;

            case 'reset_user_password':
                $newPassword = reset_user_password((int) ($_POST['user_id'] ?? 0));
                flash('generated_password', $newPassword);
                flash('success', t('flash.password_reset_generated'));
                break;

            case 'create_location':
                create_location((string) ($_POST['name'] ?? ''), (string) ($_POST['default_language'] ?? DEFAULT_LANGUAGE));
                flash('success', t('flash.location_added'));
                break;

            case 'rename_location':
                rename_location(
                    (int) ($_POST['location_id'] ?? 0),
                    (string) ($_POST['name'] ?? ''),
                    (string) ($_POST['default_language'] ?? DEFAULT_LANGUAGE)
                );
                flash('success', t('flash.location_updated'));
                break;

            case 'delete_location':
                delete_location((int) ($_POST['location_id'] ?? 0));
                flash('success', t('flash.location_deleted'));
                break;

            case 'save_default_duration':
                save_global_default_duration_minutes((int) ($_POST['default_duration_minutes'] ?? 0));
                flash('success', t('flash.default_duration_saved'));
                break;

            case 'save_board_rules':
                $rules = [];
                foreach (supported_languages() as $language) {
                    $rules[$language] = (string) ($_POST['board_rules_' . $language] ?? '');
                }
                save_board_rules((int) ($_POST['location_id'] ?? 0), $rules);
                flash('success', t('flash.board_rules_saved'));
                break;

            default:
                flash('error', t('flash.unknown_action'));
        }
    } catch (InvalidArgumentException $exception) {
        flash('error', $exception->getMessage());
    } catch (PDOException $exception) {
        flash('error', t('flash.action_failed_user_certifications'));
    }

    redirect('/owner.php');
}

render('owner', [
    'user' => $user,
    'locations' => list_locations(),
    'users' => list_users(),
    'generatedPassword' => flash('generated_password'),
    'globalDefaultDuration' => global_default_duration_minutes(),
    'maxDurationMinutes' => (int) config('app')['max_duration_minutes'],
]);
