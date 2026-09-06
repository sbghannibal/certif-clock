<?php
/**
 * Lichte testset zonder externe testframeworks.
 *
 * Uitvoeren: php tests/run.php
 *
 * De databasetests worden overgeslagen wanneer er geen MySQL-verbinding
 * beschikbaar is (bv. DB_* variabelen niet ingesteld).
 */

declare(strict_types=1);

define('CERTIF_CLOCK', true);
require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/qr.php';

$passed = 0;
$failed = 0;
$skipped = 0;

function check(string $name, bool $condition): void
{
    global $passed, $failed;
    if ($condition) {
        $passed++;
        echo "  ok   $name\n";

        return;
    }
    $failed++;
    echo "  FAIL $name\n";
}

function skip(string $name, string $reason): void
{
    global $skipped;
    $skipped++;
    echo "  skip $name ($reason)\n";
}

echo "Validatie\n";
check('bord 1 is geldig', is_valid_board(1));
check('bord 3 is geldig', is_valid_board(3));
check('bord 4 is ongeldig', !is_valid_board(4));
check('bord 0 is ongeldig', !is_valid_board(0));
check('niet-numeriek bord is ongeldig', !is_valid_board('abc'));
check('maximaal 3 borden', board_count() === 3);

echo "Escaping\n";
check('html wordt geëscaped', e('<script>alert(1)</script>') === '&lt;script&gt;alert(1)&lt;/script&gt;');
check('quotes worden geëscaped', e('"x"') === '&quot;x&quot;');

echo "QR-code\n";
$matrix = QrCode::matrix('http://localhost:8080/board.php?board=1');
check('versie 3 matrix (29x29)', count($matrix) === 29 && count($matrix[0]) === 29);
check('zoekpatroon linksboven', $matrix[0][0] === 1 && $matrix[0][6] === 1 && $matrix[1][1] === 0);
check('donkere module aanwezig', $matrix[count($matrix) - 8][8] === 1);
if (function_exists('imagecreatetruecolor')) {
    $png = QrCode::png('http://localhost:8080/board.php?board=1', 4);
    check('png wordt gegenereerd', str_starts_with($png, "\x89PNG"));
} else {
    skip('png wordt gegenereerd', 'de GD-extensie ontbreekt');
}

echo "Database\n";
$dbAvailable = false;
try {
    $dbAvailable = db_schema_installed();
} catch (Throwable $exception) {
    $dbAvailable = false;
}

if (!$dbAvailable) {
    skip('certificatie starten en stoppen', 'geen MySQL-verbinding of schema');
    skip('bord accepteert enkel 1 tot 3', 'geen MySQL-verbinding of schema');
} else {
    $statement = db()->prepare("INSERT INTO users (username, password_hash, role) VALUES (?, ?, 'expert')");
    $username = 'test-' . bin2hex(random_bytes(4));
    $statement->execute([$username, password_hash('test-wachtwoord', PASSWORD_DEFAULT)]);
    $userId = (int) db()->lastInsertId();

    try {
        $board = board_count();
        $free = null;
        for ($i = 1; $i <= $board; $i++) {
            if (!board_state($i)['running']) {
                $free = $i;
                break;
            }
        }

        if ($free === null) {
            skip('certificatie starten en stoppen', 'alle borden zijn bezet');
        } else {
            $id = start_certification([
                'perid' => '123456',
                'board' => $free,
                'location' => 'Testlocatie',
                'duration_minutes' => 5,
            ], $userId);

            $state = board_state($free);
            check('bord loopt na het starten', $state['running']);
            check('perid wordt bewaard', $state['certification']['perid'] === '123456');
            check('duur in seconden', $state['certification']['durationSeconds'] === 300);

            $error = null;
            try {
                start_certification([
                    'perid' => '123456',
                    'board' => $free,
                    'location' => 'Testlocatie',
                ], $userId);
            } catch (InvalidArgumentException $exception) {
                $error = $exception->getMessage();
            }
            check('bezet bord kan niet opnieuw starten', $error !== null);

            $error = null;
            try {
                start_certification(['perid' => 'abc', 'board' => $free, 'location' => 'X'], $userId);
            } catch (InvalidArgumentException $exception) {
                $error = $exception->getMessage();
            }
            check('ongeldig perid wordt geweigerd', $error !== null);

            $error = null;
            try {
                start_certification(['perid' => '123456', 'board' => 4, 'location' => 'X'], $userId);
            } catch (InvalidArgumentException $exception) {
                $error = $exception->getMessage();
            }
            check('bord 4 wordt geweigerd', $error !== null);

            stop_certification($id);
            check('bord is vrij na het stoppen', !board_state($free)['running']);

            db()->prepare('DELETE FROM certifications WHERE id = ?')->execute([$id]);
        }
    } finally {
        db()->prepare('DELETE FROM users WHERE id = ?')->execute([$userId]);
    }
}

echo "\n$passed geslaagd, $failed gefaald, $skipped overgeslagen\n";
exit($failed === 0 ? 0 : 1);
