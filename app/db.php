<?php
/** Databaselaag (MySQL via PDO). */

declare(strict_types=1);

if (!defined('CERTIF_CLOCK')) {
    http_response_code(403);
    exit('Directe toegang is niet toegestaan.');
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $config = config('db');
    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=%s',
        $config['host'],
        $config['port'],
        $config['name'],
        $config['charset']
    );

    try {
        $pdo = new PDO($dsn, $config['user'], $config['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    } catch (PDOException $exception) {
        throw new RuntimeException(
            'Kan geen verbinding maken met de MySQL-database. Controleer DB_HOST, DB_NAME, '
            . 'DB_USER en DB_PASSWORD in je .env bestand.',
            0,
            $exception
        );
    }

    return $pdo;
}

/** Controleert of het schema geïmporteerd is (database/schema.sql). */
function db_schema_installed(): bool
{
    try {
        db()->query('SELECT 1 FROM users LIMIT 1');
    } catch (PDOException $exception) {
        return false;
    }

    return true;
}
