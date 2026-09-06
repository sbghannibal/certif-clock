<?php
/** Domeinlogica voor borden en certificaties. */

declare(strict_types=1);

if (!defined('CERTIF_CLOCK')) {
    http_response_code(403);
    exit('Directe toegang is niet toegestaan.');
}

const PERID_PATTERN = '/^[0-9]{4,10}$/';

function board_count(): int
{
    return (int) config('app')['board_count'];
}

function is_valid_board($board): bool
{
    return is_numeric($board) && (int) $board == $board && (int) $board >= 1 && (int) $board <= board_count();
}

function board_state(int $board): array
{
    $state = [
        'board' => $board,
        'url' => '/board.php?board=' . $board,
        'running' => false,
        'certification' => null,
    ];

    $statement = db()->prepare(
        'SELECT id, perid, board, location, duration_seconds, started_at, ends_at
           FROM certifications
          WHERE board = ? AND stopped_at IS NULL
          ORDER BY id DESC LIMIT 1'
    );
    $statement->execute([$board]);
    $row = $statement->fetch();
    if (!$row) {
        return $state;
    }

    $endsAt = strtotime($row['ends_at']);
    $state['running'] = true;
    $state['certification'] = [
        'id' => (int) $row['id'],
        'perid' => $row['perid'],
        'location' => $row['location'],
        'durationSeconds' => (int) $row['duration_seconds'],
        'startedAt' => $row['started_at'],
        'endsAt' => gmdate('c', $endsAt),
        'remainingSeconds' => max(0, $endsAt - time()),
        'finished' => $endsAt <= time(),
    ];

    return $state;
}

function all_board_states(): array
{
    $states = [];
    for ($board = 1; $board <= board_count(); $board++) {
        $states[] = board_state($board);
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
    $location = trim((string) ($input['location'] ?? ''));
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
    if ($location === '') {
        throw new InvalidArgumentException('Locatie is verplicht.');
    }
    if (mb_strlen($location) > 120) {
        throw new InvalidArgumentException('Locatie is te lang (max. 120 tekens).');
    }
    if (!is_numeric($duration) || (float) $duration <= 0 || (float) $duration > $app['max_duration_minutes']) {
        throw new InvalidArgumentException('Ongeldige duur (1 tot ' . $app['max_duration_minutes'] . ' minuten).');
    }

    $board = (int) $board;
    $durationSeconds = (int) round((float) $duration * 60);
    $now = time();

    // Bezetting nakijken en invoegen in één transactie, zodat twee gelijktijdige
    // aanvragen niet allebei hetzelfde bord kunnen starten.
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $lock = $pdo->prepare(
            'SELECT id FROM certifications
              WHERE board = ? AND stopped_at IS NULL
              ORDER BY id DESC LIMIT 1 FOR UPDATE'
        );
        $lock->execute([$board]);
        if ($lock->fetch()) {
            throw new InvalidArgumentException('Dit bord is al bezet.');
        }

        $statement = $pdo->prepare(
            'INSERT INTO certifications
                (perid, board, location, duration_seconds, started_at, ends_at, started_by)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $statement->execute([
            $perid,
            $board,
            $location,
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

function certification_history(int $limit = 100): array
{
    $limit = max(1, min(500, $limit));
    $rows = db()->query(
        'SELECT c.id, c.perid, c.board, c.location, c.duration_seconds, c.started_at,
                c.ends_at, c.stopped_at, u.username AS started_by
           FROM certifications c
           JOIN users u ON u.id = c.started_by
          ORDER BY c.id DESC
          LIMIT ' . $limit
    )->fetchAll();

    return $rows ?: [];
}
