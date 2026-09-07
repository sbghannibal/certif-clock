<?php
/**
 * Generieke sleutel/waarde-instellingen (bv. de globale standaardduur die de
 * owner instelt) en de per-account standaardduur die een gebruiker zelf kan
 * instellen op zijn account.
 */

declare(strict_types=1);

if (!defined('CERTIF_CLOCK')) {
    http_response_code(403);
    exit('Directe toegang is niet toegestaan.');
}

const SETTING_DEFAULT_DURATION_MINUTES = 'default_duration_minutes';

function get_setting(string $key): ?string
{
    $statement = db()->prepare('SELECT setting_value FROM settings WHERE setting_key = ?');
    $statement->execute([$key]);
    $value = $statement->fetchColumn();

    return $value === false ? null : (string) $value;
}

function set_setting(string $key, string $value): void
{
    $statement = db()->prepare(
        'INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
    );
    $statement->execute([$key, $value]);
}

/** Valideert een duur (in minuten) tegen de toegelaten grenzen uit de configuratie. */
function assert_valid_duration_minutes(int $minutes): void
{
    $maxDuration = (int) config('app')['max_duration_minutes'];
    if ($minutes < 1 || $minutes > $maxDuration) {
        throw new InvalidArgumentException('Ongeldige standaardduur (1 tot ' . $maxDuration . ' minuten).');
    }
}

/** Globale standaardduur (in minuten), ingesteld door de owner. Valt terug op de configuratie. */
function global_default_duration_minutes(): int
{
    $value = get_setting(SETTING_DEFAULT_DURATION_MINUTES);
    if ($value === null || $value === '') {
        return (int) config('app')['default_duration_minutes'];
    }

    return (int) $value;
}

function save_global_default_duration_minutes(int $minutes): void
{
    assert_valid_duration_minutes($minutes);
    set_setting(SETTING_DEFAULT_DURATION_MINUTES, (string) $minutes);
}

/** Optionele, per-account standaardduur (in minuten). `null` als er geen override is. */
function account_default_duration_minutes(int $userId): ?int
{
    $statement = db()->prepare('SELECT default_duration_minutes FROM users WHERE id = ?');
    $statement->execute([$userId]);
    $value = $statement->fetchColumn();

    return ($value === false || $value === null) ? null : (int) $value;
}

/** Slaat de per-account standaardduur op. Geef `null` mee om de override te wissen. */
function save_account_default_duration_minutes(int $userId, ?int $minutes): void
{
    if ($minutes !== null) {
        assert_valid_duration_minutes($minutes);
    }
    $statement = db()->prepare('UPDATE users SET default_duration_minutes = ? WHERE id = ?');
    $statement->execute([$minutes, $userId]);
}

/**
 * Bepaalt de voorgestelde duur (in minuten) volgens de prioriteit:
 * 1. account-specifieke standaard (als ingevuld)
 * 2. globale owner-standaard
 * 3. fallback 120 minuten (via configuratie)
 */
function resolve_default_duration_minutes(?int $userId): int
{
    if ($userId !== null) {
        $accountDefault = account_default_duration_minutes($userId);
        if ($accountDefault !== null) {
            return $accountDefault;
        }
    }

    return global_default_duration_minutes();
}
