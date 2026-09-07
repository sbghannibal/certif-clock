<?php
/** Accountpagina: eigen wachtwoord en taalvoorkeur beheren. */

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

            case 'save_account_default_duration':
                $rawDuration = trim((string) ($_POST['default_duration_minutes'] ?? ''));
                save_account_default_duration_minutes(
                    (int) $user['id'],
                    $rawDuration === '' ? null : (int) $rawDuration
                );
                flash('success', t('flash.account_default_duration_saved'));
                break;

            default:
                flash('error', t('flash.unknown_action'));
        }
    } catch (InvalidArgumentException $exception) {
        flash('error', $exception->getMessage());
    } catch (PDOException $exception) {
        flash('error', t('flash.action_failed_user_certifications'));
    }

    redirect('/account.php');
}

render('account', [
    'user' => $user,
    'accountDefaultDuration' => account_default_duration_minutes((int) $user['id']),
    'globalDefaultDuration' => global_default_duration_minutes(),
    'maxDurationMinutes' => (int) config('app')['max_duration_minutes'],
]);
