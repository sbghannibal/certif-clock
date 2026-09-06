<?php
/** Gebruikersbeheer (alleen voor de owner). */

declare(strict_types=1);

if (!defined('CERTIF_CLOCK')) {
    http_response_code(403);
    exit('Directe toegang is niet toegestaan.');
}

function list_users(): array
{
    return db()->query('SELECT id, username, role, created_at FROM users ORDER BY id')->fetchAll() ?: [];
}

function create_user(string $username, string $password, string $role): int
{
    $username = trim($username);
    if (mb_strlen($username) < 3 || mb_strlen($username) > 100) {
        throw new InvalidArgumentException('Gebruikersnaam moet 3 tot 100 tekens lang zijn.');
    }
    if (strlen($password) < 8) {
        throw new InvalidArgumentException('Wachtwoord is te kort (min. 8 tekens).');
    }
    $role = $role === 'owner' ? 'owner' : 'expert';

    $exists = db()->prepare('SELECT id FROM users WHERE username = ?');
    $exists->execute([$username]);
    if ($exists->fetch()) {
        throw new InvalidArgumentException('Gebruikersnaam bestaat al.');
    }

    $statement = db()->prepare('INSERT INTO users (username, password_hash, role) VALUES (?, ?, ?)');
    $statement->execute([$username, password_hash($password, PASSWORD_DEFAULT), $role]);

    return (int) db()->lastInsertId();
}

function delete_user(int $id, int $currentUserId): void
{
    if ($id === $currentUserId) {
        throw new InvalidArgumentException('Je kan je eigen account niet verwijderen.');
    }
    $statement = db()->prepare('SELECT id FROM users WHERE id = ?');
    $statement->execute([$id]);
    if (!$statement->fetch()) {
        throw new InvalidArgumentException('Gebruiker niet gevonden.');
    }
    $delete = db()->prepare('DELETE FROM users WHERE id = ?');
    $delete->execute([$id]);
}
