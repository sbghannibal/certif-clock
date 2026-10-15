<?php
/** Eenmalige installatie; gebruikt uitsluitend het canonieke schema. */

declare(strict_types=1);

if (!defined('CERTIF_CLOCK')) {
    http_response_code(403);
    exit('Directe toegang is niet toegestaan.');
}

/** @return array{ddl:array, seeds:array, tables:array} */
function installer_schema(): array
{
    $sql = file_get_contents(dirname(__DIR__) . '/database/schema.sql');
    if ($sql === false) {
        throw new RuntimeException('Schema niet beschikbaar.');
    }
    $sql = preg_replace('/^\s*--[^\r\n]*/m', '', $sql);
    $schema = ['ddl' => [], 'seeds' => [], 'tables' => []];
    foreach (explode(';', $sql) as $statement) {
        $statement = trim($statement);
        if ($statement === '') {
            continue;
        }
        if (preg_match('/^CREATE TABLE IF NOT EXISTS ([a-z_]+)\s*\(/i', $statement, $match)) {
            $schema['ddl'][] = $statement;
            $schema['tables'][] = $match[1];
        } elseif (preg_match('/^INSERT IGNORE INTO ([a-z_]+)\s*\(/i', $statement, $match)
            && in_array($match[1], $schema['tables'], true)) {
            $schema['seeds'][] = $statement;
        } else {
            throw new RuntimeException('Onbekende schemainstructie.');
        }
    }
    if ($schema['ddl'] === [] || !in_array('settings', $schema['tables'], true)) {
        throw new RuntimeException('Onvolledig schema.');
    }

    return $schema;
}

/**
 * Alleen lege, bekende InnoDB-tabellen zijn herbruikbaar. Ook seeddata en het
 * duurzame installation_completed-signaal in settings sluiten installatie af.
 * Inspectiefouten worden bewust niet als "leeg" behandeld.
 */
function installer_available(PDO $pdo): bool
{
    $known = installer_schema()['tables'];
    $tables = $pdo->query(
        'SELECT TABLE_NAME, TABLE_TYPE, ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()'
    )->fetchAll();
    foreach ($tables as $table) {
        if (!in_array($table['TABLE_NAME'], $known, true)
            || $table['TABLE_TYPE'] !== 'BASE TABLE'
            || strcasecmp((string) $table['ENGINE'], 'InnoDB') !== 0) {
            return false;
        }
        $name = '`' . str_replace('`', '``', $table['TABLE_NAME']) . '`';
        if ($pdo->query('SELECT 1 FROM ' . $name . ' LIMIT 1')->fetchColumn() !== false) {
            return false;
        }
    }

    return true;
}

function installer_validate(array $input): array
{
    foreach (['username', 'password', 'password_confirm', 'csrf_token'] as $key) {
        if (!isset($input[$key]) || !is_string($input[$key])) {
            throw new InvalidArgumentException('Vul alle velden in met een geldig CSRF-token.');
        }
    }
    if (!csrf_verify($input['csrf_token'])) {
        throw new InvalidArgumentException('Ongeldig of ontbrekend CSRF-token. Herlaad de pagina.');
    }
    $username = trim($input['username']);
    if (!mb_check_encoding($username, 'UTF-8') || mb_strlen($username) < 3 || mb_strlen($username) > 100) {
        throw new InvalidArgumentException('Gebruikersnaam moet 3 tot 100 tekens lang zijn.');
    }
    if (strlen($input['password']) < 8) {
        throw new InvalidArgumentException('Wachtwoord is te kort (min. 8 tekens).');
    }
    if ($input['password'] !== $input['password_confirm']) {
        throw new InvalidArgumentException('De wachtwoordbevestiging komt niet overeen.');
    }

    return [$username, $input['password']];
}

function installer_install(PDO $pdo, array $input): void
{
    [$username, $password] = installer_validate($input);
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $database = $pdo->query('SELECT DATABASE()')->fetchColumn();
    if (!is_string($database) || $database === '' || $pdo->inTransaction()) {
        throw new RuntimeException('Geen zelfstandige databaseverbinding.');
    }
    // GET_LOCK blijft behouden bij de implicit commits van MySQL-DDL.
    $lock = 'certif-install:' . substr(hash('sha256', $database), 0, 48);
    $statement = $pdo->prepare('SELECT GET_LOCK(?, 10)');
    $statement->execute([$lock]);
    if ((int) $statement->fetchColumn() !== 1) {
        throw new RuntimeException('Installatie is bezet.');
    }
    try {
        if (!installer_available($pdo)) {
            throw new RuntimeException('Installatie is gesloten.');
        }
        $schema = installer_schema();
        foreach ($schema['ddl'] as $ddl) {
            $pdo->exec($ddl);
        }
        // DDL is niet terug te draaien. Bij fouten blijven uitsluitend lege
        // tabellen achter; seeds, owner en afsluiting worden samen vastgelegd.
        if (!installer_available($pdo)) {
            throw new RuntimeException('Installatie is gesloten.');
        }
        $pdo->beginTransaction();
        foreach ($schema['seeds'] as $seed) {
            $pdo->exec($seed);
        }
        $statement = $pdo->prepare(
            "INSERT INTO users (username, password_hash, role, language) VALUES (?, ?, 'owner', 'nl')"
        );
        $statement->execute([$username, $hash]);
        $pdo->exec("INSERT INTO settings (setting_key, setting_value) VALUES ('installation_completed', '1')");
        $pdo->commit();
    } finally {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $statement = $pdo->prepare('SELECT RELEASE_LOCK(?)');
        $statement->execute([$lock]);
    }
}
