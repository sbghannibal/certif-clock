<?php
/** Beheer van locaties (Gent, Berchem, Aarschot, ...). */

declare(strict_types=1);

if (!defined('CERTIF_CLOCK')) {
    http_response_code(403);
    exit('Directe toegang is niet toegestaan.');
}

/** Maakt een URL-vriendelijke, generieke slug van een locatienaam. */
function location_slug(string $name): string
{
    $slug = strtolower(trim($name));
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';

    return trim($slug, '-');
}

/** @return array<int, array{id:int, name:string, slug:string, default_language:string, created_at:string}> */
function list_locations(): array
{
    $rows = db()->query('SELECT id, name, default_language, created_at FROM locations ORDER BY name')->fetchAll() ?: [];
    foreach ($rows as &$row) {
        $row['id'] = (int) $row['id'];
        $row['default_language'] = normalize_language($row['default_language'] ?? null);
        $row['slug'] = location_slug($row['name']);
    }

    return $rows;
}

function find_location_by_id(int $id): ?array
{
    $statement = db()->prepare('SELECT id, name, default_language, created_at FROM locations WHERE id = ?');
    $statement->execute([$id]);
    $row = $statement->fetch();
    if (!$row) {
        return null;
    }
    $row['id'] = (int) $row['id'];
    $row['default_language'] = normalize_language($row['default_language'] ?? null);
    $row['slug'] = location_slug($row['name']);

    return $row;
}

function find_location_by_slug(string $slug): ?array
{
    $slug = location_slug($slug);
    foreach (list_locations() as $location) {
        if ($location['slug'] === $slug) {
            return $location;
        }
    }

    return null;
}

/**
 * Zoekt een locatie op basis van een GET-parameter: numeriek als id, anders als
 * slug/naam. Zonder parameter wordt de eerste locatie (alfabetisch) gebruikt.
 */
function resolve_location(?string $param): ?array
{
    if ($param === null || trim($param) === '') {
        $all = list_locations();

        return $all[0] ?? null;
    }
    if (ctype_digit($param)) {
        $byId = find_location_by_id((int) $param);
        if ($byId !== null) {
            return $byId;
        }
    }

    return find_location_by_slug($param);
}

/**
 * Zoals resolve_location(), maar onthoudt de laatst bekeken locatie in de
 * sessie zodat het dashboard bij een volgend bezoek dezelfde locatie toont.
 */
function resolve_active_location(?string $param): ?array
{
    if ($param === null || trim($param) === '') {
        $remembered = $_SESSION['active_location'] ?? null;
        if (is_string($remembered) && $remembered !== '') {
            $location = resolve_location($remembered);
            if ($location !== null) {
                return $location;
            }
        }

        return resolve_location(null);
    }

    $location = resolve_location($param);
    if ($location !== null) {
        $_SESSION['active_location'] = $location['slug'];
    }

    return $location;
}

function create_location(string $name, string $defaultLanguage = DEFAULT_LANGUAGE): int
{
    $name = trim($name);
    if ($name === '' || mb_strlen($name) > 120) {
        throw new InvalidArgumentException('Locatienaam moet 1 tot 120 tekens lang zijn.');
    }

    $exists = db()->prepare('SELECT id FROM locations WHERE name = ?');
    $exists->execute([$name]);
    if ($exists->fetch()) {
        throw new InvalidArgumentException('Deze locatie bestaat al.');
    }
    $defaultLanguage = normalize_language($defaultLanguage);

    $statement = db()->prepare('INSERT INTO locations (name, default_language) VALUES (?, ?)');
    $statement->execute([$name, $defaultLanguage]);

    return (int) db()->lastInsertId();
}

function rename_location(int $id, string $name, ?string $defaultLanguage = null): void
{
    $name = trim($name);
    if ($name === '' || mb_strlen($name) > 120) {
        throw new InvalidArgumentException('Locatienaam moet 1 tot 120 tekens lang zijn.');
    }

    $statement = db()->prepare('SELECT id FROM locations WHERE id = ?');
    $statement->execute([$id]);
    if (!$statement->fetch()) {
        throw new InvalidArgumentException('Locatie niet gevonden.');
    }

    $exists = db()->prepare('SELECT id FROM locations WHERE name = ? AND id <> ?');
    $exists->execute([$name, $id]);
    if ($exists->fetch()) {
        throw new InvalidArgumentException('Deze locatienaam is al in gebruik.');
    }

    if ($defaultLanguage === null) {
        $update = db()->prepare('UPDATE locations SET name = ? WHERE id = ?');
        $update->execute([$name, $id]);

        return;
    }

    $update = db()->prepare('UPDATE locations SET name = ?, default_language = ? WHERE id = ?');
    $update->execute([$name, normalize_language($defaultLanguage), $id]);
}

function delete_location(int $id): void
{
    $statement = db()->prepare('SELECT id FROM locations WHERE id = ?');
    $statement->execute([$id]);
    if (!$statement->fetch()) {
        throw new InvalidArgumentException('Locatie niet gevonden.');
    }

    $inUse = db()->prepare('SELECT COUNT(*) FROM certifications WHERE location_id = ?');
    $inUse->execute([$id]);
    if ((int) $inUse->fetchColumn() > 0) {
        throw new InvalidArgumentException(
            'Deze locatie kan niet verwijderd worden: er staan nog certificaties op haar naam.'
        );
    }

    $delete = db()->prepare('DELETE FROM locations WHERE id = ?');
    $delete->execute([$id]);
}
