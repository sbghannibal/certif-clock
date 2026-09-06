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

/** Genereert een sterk, leesbaar wachtwoord (geen verwarrende tekens zoals 0/O/1/l). */
function generate_password(int $length = 14): string
{
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#$%&*';
    $max = strlen($alphabet) - 1;
    $password = '';
    for ($i = 0; $i < $length; $i++) {
        $password .= $alphabet[random_int(0, $max)];
    }

    return $password;
}

/**
 * Maakt een gebruiker aan. Geef geen wachtwoord (of een lege string) mee om er
 * automatisch een sterk wachtwoord te laten genereren.
 *
 * @return array{id:int, password:?string} `password` is enkel gezet als het automatisch
 *                                          gegenereerd werd (om eenmalig te tonen).
 */
function create_user(string $username, ?string $password, string $role): array
{
    $username = trim($username);
    if (mb_strlen($username) < 3 || mb_strlen($username) > 100) {
        throw new InvalidArgumentException('Gebruikersnaam moet 3 tot 100 tekens lang zijn.');
    }

    $generated = false;
    if ($password === null || $password === '') {
        $password = generate_password();
        $generated = true;
    } elseif (strlen($password) < 8) {
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

    return [
        'id' => (int) db()->lastInsertId(),
        'password' => $generated ? $password : null,
    ];
}

/** Laat een aangemelde gebruiker (owner of expert) zijn eigen wachtwoord wijzigen. */
function change_password(int $userId, string $currentPassword, string $newPassword, string $confirmPassword): void
{
    if ($newPassword !== $confirmPassword) {
        throw new InvalidArgumentException('De bevestiging komt niet overeen met het nieuwe wachtwoord.');
    }
    if (strlen($newPassword) < 8) {
        throw new InvalidArgumentException('Nieuw wachtwoord is te kort (min. 8 tekens).');
    }

    $statement = db()->prepare('SELECT password_hash FROM users WHERE id = ?');
    $statement->execute([$userId]);
    $row = $statement->fetch();
    if (!$row || !password_verify($currentPassword, $row['password_hash'])) {
        throw new InvalidArgumentException('Huidig wachtwoord is onjuist.');
    }

    $update = db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
    $update->execute([password_hash($newPassword, PASSWORD_DEFAULT), $userId]);
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
