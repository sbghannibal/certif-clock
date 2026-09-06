<?php
/** Centrale i18n-hulpen voor NL/EN/FR/DE. */

declare(strict_types=1);

if (!defined('CERTIF_CLOCK')) {
    http_response_code(403);
    exit('Directe toegang is niet toegestaan.');
}

const DEFAULT_LANGUAGE = 'en';
const SUPPORTED_LANGUAGES = ['nl', 'en', 'fr', 'de'];

function supported_languages(): array
{
    return SUPPORTED_LANGUAGES;
}

function language_options(): array
{
    return [
        'nl' => 'Nederlands',
        'en' => 'English',
        'fr' => 'Français',
        'de' => 'Deutsch',
    ];
}

function is_supported_language(?string $language): bool
{
    return is_string($language) && in_array(strtolower($language), SUPPORTED_LANGUAGES, true);
}

function normalize_language(?string $language): string
{
    $language = is_string($language) ? strtolower(trim($language)) : '';

    return is_supported_language($language) ? $language : DEFAULT_LANGUAGE;
}

function load_language_file(string $language): array
{
    $path = dirname(__DIR__) . '/lang/' . normalize_language($language) . '.php';
    if (!is_file($path)) {
        $path = dirname(__DIR__) . '/lang/' . DEFAULT_LANGUAGE . '.php';
    }

    $translations = require $path;

    return is_array($translations) ? $translations : [];
}

function set_translation_language(string $language): void
{
    $language = normalize_language($language);
    $GLOBALS['app_language'] = $language;
    $GLOBALS['translations'] = load_language_file($language);
    $GLOBALS['fallback_translations'] = load_language_file(DEFAULT_LANGUAGE);
}

function current_language(): string
{
    return $GLOBALS['app_language'] ?? DEFAULT_LANGUAGE;
}

function t(string $key): string
{
    $translations = $GLOBALS['translations'] ?? [];
    $fallback = $GLOBALS['fallback_translations'] ?? load_language_file(DEFAULT_LANGUAGE);

    return (string) ($translations[$key] ?? $fallback[$key] ?? $key);
}

function admin_language(?int $userId): ?string
{
    if ($userId === null) {
        return null;
    }
    $statement = db()->prepare('SELECT language FROM users WHERE id = ? LIMIT 1');
    $statement->execute([$userId]);
    $language = $statement->fetchColumn();

    return is_supported_language($language) ? strtolower((string) $language) : null;
}

function save_admin_language(int $userId, string $language): void
{
    $language = normalize_language($language);
    $statement = db()->prepare('UPDATE users SET language = ? WHERE id = ?');
    $statement->execute([$language, $userId]);
    if (isset($_SESSION['user']) && (int) ($_SESSION['user']['id'] ?? 0) === $userId) {
        $_SESSION['user']['language'] = $language;
    }
    $_SESSION['lang'] = $language;
}

function language_from_request(): ?string
{
    $language = $_GET['lang'] ?? null;

    return is_supported_language($language) ? strtolower((string) $language) : null;
}

function init_i18n(?array $location = null, bool $locationFirst = false): string
{
    $queryLanguage = language_from_request();
    if ($queryLanguage !== null) {
        $_SESSION['lang'] = $queryLanguage;
        $user = current_user();
        if ($user !== null) {
            save_admin_language((int) $user['id'], $queryLanguage);
        }
        set_translation_language($queryLanguage);

        return $queryLanguage;
    }

    if ($locationFirst) {
        $locationLanguage = normalize_language($location['default_language'] ?? null);
        set_translation_language($locationLanguage);

        return $locationLanguage;
    }

    $user = current_user();
    $userLanguage = $user !== null ? admin_language((int) $user['id']) : null;
    if ($userLanguage !== null) {
        $_SESSION['lang'] = $userLanguage;
        set_translation_language($userLanguage);

        return $userLanguage;
    }

    if (isset($_SESSION['lang']) && is_supported_language($_SESSION['lang'])) {
        $sessionLanguage = strtolower((string) $_SESSION['lang']);
        set_translation_language($sessionLanguage);

        return $sessionLanguage;
    }

    set_translation_language(DEFAULT_LANGUAGE);

    return DEFAULT_LANGUAGE;
}

function language_url(string $language): string
{
    $path = strtok($_SERVER['REQUEST_URI'] ?? '/index.php', '?') ?: '/index.php';
    $params = $_GET;
    $params['lang'] = normalize_language($language);
    $query = http_build_query($params);

    return $path . ($query !== '' ? '?' . $query : '');
}

set_translation_language(DEFAULT_LANGUAGE);
