<?php
/** Domeinlogica voor borden en certificaties. */

declare(strict_types=1);

if (!defined('CERTIF_CLOCK')) {
    http_response_code(403);
    exit('Directe toegang is niet toegestaan.');
}

const PERID_PATTERN = '/^[0-9]{4,10}$/';

/** Maximale totale tijdsverlenging per certificatie (in seconden). */
const MAX_EXTENSION_SECONDS = 120 * 60;

/** Certificaties die langer dan dit gestopt zijn, sluiten automatisch. */
const AUTO_CLOSE_AFTER_SECONDS = 10 * 60;

function board_count(): int
{
    return (int) config('app')['board_count'];
}

function is_valid_board($board): bool
{
    return is_numeric($board) && (int) $board == $board && (int) $board >= 1 && (int) $board <= board_count();
}

/** Bouwt de directe URL naar een bord, inclusief locatie. */
function board_url(string $locationSlug, int $board): string
{
    return '/board.php?location=' . rawurlencode($locationSlug) . '&board=' . $board;
}

function board_state(array $location, int $board): array
{
    $state = [
        'board' => $board,
        'location' => $location,
        'url' => board_url($location['slug'], $board),
        'running' => false,
        'certification' => null,
    ];

    $statement = db()->prepare(
        'SELECT id, perid, board, duration_seconds, started_at, ends_at, paused_at
           FROM certifications
          WHERE board = ? AND location_id = ? AND stopped_at IS NULL
          ORDER BY id DESC LIMIT 1'
    );
    $statement->execute([$board, $location['id']]);
    $row = $statement->fetch();
    if (!$row) {
        return $state;
    }

    $endsAt = strtotime($row['ends_at']);
    $paused = $row['paused_at'] !== null;
    $pausedAt = $paused ? strtotime($row['paused_at']) : null;
    $now = $paused ? $pausedAt : time();
    $state['running'] = true;
    $state['certification'] = [
        'id' => (int) $row['id'],
        'perid' => $row['perid'],
        'location' => $location['name'],
        'durationSeconds' => (int) $row['duration_seconds'],
        'startedAt' => $row['started_at'],
        'endsAt' => gmdate('c', $endsAt),
        'remainingSeconds' => max(0, $endsAt - $now),
        'finished' => !$paused && $endsAt <= $now,
        'paused' => $paused,
        'pausedAt' => $paused ? gmdate('c', $pausedAt) : null,
    ];

    return $state;
}

function all_board_states(array $location): array
{
    $states = [];
    for ($board = 1; $board <= board_count(); $board++) {
        $states[] = board_state($location, $board);
    }

    return $states;
}

/**
 * Start een certificatie. Gooit een InvalidArgumentException bij ongeldige invoer.
 */
function start_certification(array $input, int $userId): int
{
    $app = config('app');
    $perid = trim((string) ($input['perid'] ?? ''));
    $locationId = (int) ($input['location_id'] ?? 0);
    $board = $input['board'] ?? null;
    $durationRaw = $input['duration_minutes'] ?? '';
    $duration = ($durationRaw === '' || $durationRaw === null)
        ? (int) $app['default_duration_minutes']
        : $durationRaw;

    if (!preg_match(PERID_PATTERN, $perid)) {
        throw new InvalidArgumentException('Ongeldig PERID (4 tot 10 cijfers).');
    }
    if (!is_valid_board($board)) {
        throw new InvalidArgumentException('Bord moet tussen 1 en ' . board_count() . ' liggen.');
    }
    $location = find_location_by_id($locationId);
    if ($location === null) {
        throw new InvalidArgumentException('Ongeldige locatie.');
    }
    if (!is_numeric($duration) || (float) $duration <= 0 || (float) $duration > $app['max_duration_minutes']) {
        throw new InvalidArgumentException('Ongeldige duur (1 tot ' . $app['max_duration_minutes'] . ' minuten).');
    }

    $board = (int) $board;
    $durationSeconds = (int) round((float) $duration * 60);
    $now = time();

    // Bezetting nakijken en invoegen in één transactie, zodat twee gelijktijdige
    // aanvragen niet allebei hetzelfde bord (op dezelfde locatie) kunnen starten.
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $lock = $pdo->prepare(
            'SELECT id FROM certifications
              WHERE board = ? AND location_id = ? AND stopped_at IS NULL
              ORDER BY id DESC LIMIT 1 FOR UPDATE'
        );
        $lock->execute([$board, $locationId]);
        if ($lock->fetch()) {
            throw new InvalidArgumentException('Dit bord is al bezet.');
        }

        $statement = $pdo->prepare(
            'INSERT INTO certifications
                (perid, board, location_id, duration_seconds, started_at, ends_at, started_by)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $statement->execute([
            $perid,
            $board,
            $locationId,
            $durationSeconds,
            date('Y-m-d H:i:s', $now),
            date('Y-m-d H:i:s', $now + $durationSeconds),
            $userId,
        ]);
        $id = (int) $pdo->lastInsertId();
        $pdo->commit();
    } catch (Throwable $exception) {
    $pdo->rollBack();
    throw $exception;
    }

    return $id;
}

function stop_certification(int $id): void
{
    $statement = db()->prepare('SELECT id, stopped_at FROM certifications WHERE id = ?');
    $statement->execute([$id]);
    $row = $statement->fetch();
    if (!$row) {
        throw new InvalidArgumentException('Certificatie niet gevonden.');
    }
    if ($row['stopped_at'] !== null) {
        throw new InvalidArgumentException('Certificatie is al gestopt.');
    }
    $update = db()->prepare('UPDATE certifications SET stopped_at = ? WHERE id = ?');
    $update->execute([date('Y-m-d H:i:s'), $id]);
}

/** Som van reeds gelogde tijdsverlengingen (in seconden) voor een certificatie. */
function certification_extension_total(int $certificationId): int
{
    $statement = db()->prepare(
        'SELECT COALESCE(SUM(extension_seconds), 0) FROM certification_extensions WHERE certification_id = ?'
    );
    $statement->execute([$certificationId]);

    return (int) $statement->fetchColumn();
}

/**
 * Voegt extra tijd toe aan een lopende certificatie en logt de verlenging.
 * Gooit een InvalidArgumentException wanneer de certificatie niet bestaat/al
 * gestopt is, of wanneer de totale verlenging boven MAX_EXTENSION_SECONDS komt.
 */
function extend_certification(int $certificationId, int $extensionSeconds, int $userId): string
{
    if ($extensionSeconds <= 0) {
        throw new InvalidArgumentException('Ongeldige extra tijd.');
    }

    $statement = db()->prepare('SELECT id, ends_at, stopped_at FROM certifications WHERE id = ?');
    $statement->execute([$certificationId]);
    $row = $statement->fetch();
    if (!$row) {
        throw new InvalidArgumentException('Certificatie niet gevonden.');
    }
    if ($row['stopped_at'] !== null) {
        throw new InvalidArgumentException('Certificatie is al gestopt.');
    }

    $alreadyExtended = certification_extension_total($certificationId);
    if ($alreadyExtended + $extensionSeconds > MAX_EXTENSION_SECONDS) {
        throw new InvalidArgumentException(
            'Maximaal ' . (int) (MAX_EXTENSION_SECONDS / 60) . ' minuten extra tijd per certificatie.'
        );
    }

    $newEndsAt = strtotime($row['ends_at']) + $extensionSeconds;
    $now = date('Y-m-d H:i:s');

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $pdo->prepare('UPDATE certifications SET ends_at = ? WHERE id = ?')
            ->execute([date('Y-m-d H:i:s', $newEndsAt), $certificationId]);
        $pdo->prepare(
            'INSERT INTO certification_extensions (certification_id, extension_seconds, extended_at, extended_by)
             VALUES (?, ?, ?, ?)'
        )->execute([$certificationId, $extensionSeconds, $now, $userId]);
        $pdo->commit();
    } catch (Throwable $exception) {
        $pdo->rollBack();
        throw $exception;
    }

    return gmdate('c', $newEndsAt);
}

/** Pauzeert een lopende (niet-gestopte, niet reeds gepauzeerde) certificatie. */
function pause_certification(int $id): void
{
    $statement = db()->prepare('SELECT id, stopped_at, paused_at FROM certifications WHERE id = ?');
    $statement->execute([$id]);
    $row = $statement->fetch();
    if (!$row) {
        throw new InvalidArgumentException('Certificatie niet gevonden.');
    }
    if ($row['stopped_at'] !== null) {
        throw new InvalidArgumentException('Certificatie is al gestopt.');
    }
    if ($row['paused_at'] !== null) {
        throw new InvalidArgumentException('Certificatie is al gepauzeerd.');
    }
    db()->prepare('UPDATE certifications SET paused_at = ? WHERE id = ?')
        ->execute([date('Y-m-d H:i:s'), $id]);
}

/**
 * Hervat een gepauzeerde certificatie: het pauzeer-interval wordt bij ends_at
 * opgeteld, zodat de resterende tijd ongewijzigd blijft.
 */
function resume_certification(int $id): void
{
    $statement = db()->prepare('SELECT id, ends_at, stopped_at, paused_at FROM certifications WHERE id = ?');
    $statement->execute([$id]);
    $row = $statement->fetch();
    if (!$row) {
        throw new InvalidArgumentException('Certificatie niet gevonden.');
    }
    if ($row['stopped_at'] !== null) {
        throw new InvalidArgumentException('Certificatie is al gestopt.');
    }
    if ($row['paused_at'] === null) {
        throw new InvalidArgumentException('Certificatie is niet gepauzeerd.');
    }

    $pauseSeconds = time() - strtotime($row['paused_at']);
    $newEndsAt = strtotime($row['ends_at']) + max(0, $pauseSeconds);

    db()->prepare('UPDATE certifications SET ends_at = ?, paused_at = NULL WHERE id = ?')
        ->execute([date('Y-m-d H:i:s', $newEndsAt), $id]);
}

/**
 * Sluit certificaties automatisch die al langer dan AUTO_CLOSE_AFTER_SECONDS
 * gestopt zijn. Geeft het aantal net gesloten certificaties terug.
 */
function auto_close_expired_certifications(): int
{
    $statement = db()->prepare(
        'UPDATE certifications
            SET auto_closed = 1
          WHERE stopped_at IS NOT NULL
            AND auto_closed = 0
            AND stopped_at < (NOW() - INTERVAL ' . AUTO_CLOSE_AFTER_SECONDS . ' SECOND)'
    );
    $statement->execute();

    return $statement->rowCount();
}

/**
 * Geeft de certificatiehistoriek terug, optioneel gefilterd op locatie.
 * Enkel "relevante" certificaties worden getoond: nog lopend, in de laatste
 * 4 uur gestart, of maximaal 6 uur geleden gestopt. Zo blijft de lijst
 * overzichtelijk en zie je geen oude ruis meer.
 */
function certification_history(int $limit = 100, ?int $locationId = null): array
{
    $limit = max(1, min(500, $limit));

    $conditions = [
        '(c.stopped_at IS NULL
           OR c.started_at >= (NOW() - INTERVAL 4 HOUR)
           OR c.stopped_at >= (NOW() - INTERVAL 6 HOUR))',
    ];
    $params = [];
    if ($locationId !== null) {
        $conditions[] = 'c.location_id = ?';
        $params[] = $locationId;
    }

    $statement = db()->prepare(
        'SELECT c.id, c.perid, c.board, l.name AS location, c.duration_seconds, c.started_at,
                c.ends_at, c.stopped_at, c.paused_at, c.auto_closed, u.username AS started_by,
                (SELECT COALESCE(SUM(ce.extension_seconds), 0)
                   FROM certification_extensions ce WHERE ce.certification_id = c.id) AS extended_seconds,
                (SELECT eu.username
                   FROM certification_extensions ce2
                   JOIN users eu ON eu.id = ce2.extended_by
                  WHERE ce2.certification_id = c.id
                  ORDER BY ce2.id DESC LIMIT 1) AS extended_by
           FROM certifications c
           JOIN users u ON u.id = c.started_by
           JOIN locations l ON l.id = c.location_id
          WHERE ' . implode(' AND ', $conditions) . '
          ORDER BY c.id DESC
          LIMIT ' . $limit
    );
    $statement->execute($params);

    return $statement->fetchAll() ?: [];
}
