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

echo "Locaties\n";
check('slug van "Sint-Niklaas" is generiek', location_slug('Sint-Niklaas') === 'sint-niklaas');
check('slug negeert hoofdletters/spaties', location_slug('  GENT  ') === 'gent');

echo "i18n\n";
check('Engels is de fallbacktaal', normalize_language('xx') === 'en');
check('Duits is ondersteund', is_supported_language('de'));
set_translation_language('fr');
check('vertaling wordt geladen', t('nav.login') === 'Connexion');
check('ontbrekende key valt veilig terug op key', t('niet.bestaand') === 'niet.bestaand');
$referenceKeys = array_keys(load_language_file(DEFAULT_LANGUAGE));
foreach (supported_languages() as $language) {
    check(
        "taalbestand $language bevat alle sleutels",
        array_keys(load_language_file($language)) === $referenceKeys
    );
}
set_translation_language(DEFAULT_LANGUAGE);

echo "Geluidskeuze\n";
foreach (['beep', 'bell', 'chime', 'alert'] as $sound) {
    check("geluidsbestand $sound.mp3 bestaat", is_file(dirname(__DIR__) . '/assets/sounds/' . $sound . '.mp3'));
    check("vertaling sound.$sound bestaat", t('sound.' . $sound) !== 'sound.' . $sound);
}
check('vertaling sound.select bestaat', t('sound.select') !== 'sound.select');
check('vertaling sound.test bestaat', t('sound.test') !== 'sound.test');
check('vertaling sound.volume bestaat', t('sound.volume') !== 'sound.volume');
check('vertaling cert.pause bestaat', t('cert.pause') !== 'cert.pause');
check('vertaling cert.resume bestaat', t('cert.resume') !== 'cert.resume');
check('vertaling history.extended_by bestaat', t('history.extended_by') !== 'history.extended_by');
check('vertaling board.rules_title bestaat', t('board.rules_title') !== 'board.rules_title');
check('vertaling board.rules_none bestaat', t('board.rules_none') !== 'board.rules_none');
check('vertaling board.expired_status bestaat', t('board.expired_status') !== 'board.expired_status');
check('Nederlandse afgelopen-status is "Afgelopen"', load_language_file('nl')['board.expired_status'] === 'Afgelopen');

echo "Status-sync (bordweergave)\n";
// Contract tussen backend en klok-script: "verlopen" bestaat enkel als er geen
// resterende tijd is én de certificatie niet gepauzeerd is. Zo kan de badge
// nooit "Tijd is om!" tonen terwijl de klok nog loopt.
$clockScript = (string) file_get_contents(dirname(__DIR__) . '/assets/js/clock.js');
check('klok leidt expired af van resterende tijd en pauze', str_contains($clockScript, 'expired: !paused && remaining <= 0'));
check('klok toont "Tijd is om!" enkel als echt verlopen', str_contains($clockScript, 'expiredMessage.hidden = !state.expired'));
check('polling past klok en badge meteen opnieuw toe', str_contains($clockScript, "if (data && data.boards) updateAdminBoards(data.boards);"));
$boardView = (string) file_get_contents(dirname(__DIR__) . '/views/board.php');
check('bordweergave toont afgelopen-melding bij verlopen certificatie', str_contains($boardView, "\$certification['finished'] ? '' : ' hidden'"));
check('bordweergave bevat schakelterbare pauze-badge', str_contains($boardView, 'data-paused-badge'));

echo "Bordregels\n";
check('regelkolommen bestaan per taal', board_rule_columns() === [
    'nl' => 'board_rules_nl',
    'en' => 'board_rules_en',
    'fr' => 'board_rules_fr',
    'de' => 'board_rules_de',
]);
$rulesLocation = ['board_rules_nl' => 'Nederlandse regels', 'board_rules_en' => 'English rules'];
check('regels in gevraagde taal', board_rules_for_language($rulesLocation, 'nl') === 'Nederlandse regels');
check('regels vallen terug op Engels', board_rules_for_language($rulesLocation, 'de') === 'English rules');
check('regels geven leeg bij geen inhoud', board_rules_for_language(['board_rules_en' => ''], 'en') === '');
$schema = (string) file_get_contents(dirname(__DIR__) . '/database/schema.sql');
check('schema bevat board_rules_nl', str_contains($schema, 'board_rules_nl TEXT'));
check('schema bevat board_rules_de', str_contains($schema, 'board_rules_de TEXT'));
check('migratie 0003 bestaat', is_file(dirname(__DIR__) . '/database/migrations/0003_board_rules.sql'));
check('migratie 0004 bestaat', is_file(dirname(__DIR__) . '/database/migrations/0004_extensions_and_pause.sql'));
check('schema bevat certification_extensions', str_contains($schema, 'certification_extensions'));
check('schema bevat paused_at', str_contains($schema, 'paused_at'));
check('schema bevat auto_closed', str_contains($schema, 'auto_closed'));

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
    skip('locaties CRUD', 'geen MySQL-verbinding of schema');
    skip('wachtwoord wijzigen en genereren', 'geen MySQL-verbinding of schema');
} else {
    $statement = db()->prepare("INSERT INTO users (username, password_hash, role, language) VALUES (?, ?, 'expert', 'de')");
    $username = 'test-' . bin2hex(random_bytes(4));
    $statement->execute([$username, password_hash('test-wachtwoord', PASSWORD_DEFAULT)]);
    $userId = (int) db()->lastInsertId();

    $locationId = create_location('Testlocatie-' . bin2hex(random_bytes(4)));
    $location = find_location_by_id($locationId);

    try {
        $board = board_count();
        $free = null;
        for ($i = 1; $i <= $board; $i++) {
            if (!board_state($location, $i)['running']) {
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
                'location_id' => $locationId,
                'duration_minutes' => 5,
            ], $userId);

            $state = board_state($location, $free);
            check('bord loopt na het starten', $state['running']);
            check('perid wordt bewaard', $state['certification']['perid'] === '123456');
            check('duur in seconden', $state['certification']['durationSeconds'] === 300);

            $error = null;
            try {
                start_certification([
                    'perid' => '123456',
                    'board' => $free,
                    'location_id' => $locationId,
                ], $userId);
            } catch (InvalidArgumentException $exception) {
                $error = $exception->getMessage();
            }
            check('bezet bord kan niet opnieuw starten', $error !== null);

            $error = null;
            try {
                start_certification(['perid' => 'abc', 'board' => $free, 'location_id' => $locationId], $userId);
            } catch (InvalidArgumentException $exception) {
                $error = $exception->getMessage();
            }
            check('ongeldig perid wordt geweigerd', $error !== null);

            $error = null;
            try {
                start_certification(['perid' => '123456', 'board' => 4, 'location_id' => $locationId], $userId);
            } catch (InvalidArgumentException $exception) {
                $error = $exception->getMessage();
            }
            check('bord 4 wordt geweigerd', $error !== null);

            $error = null;
            try {
                start_certification(['perid' => '123456', 'board' => $free, 'location_id' => 999999], $userId);
            } catch (InvalidArgumentException $exception) {
                $error = $exception->getMessage();
            }
            check('onbestaande locatie wordt geweigerd', $error !== null);

            stop_certification($id);
            check('bord is vrij na het stoppen', !board_state($location, $free)['running']);

            $error = null;
            try {
                stop_certification($id);
            } catch (InvalidArgumentException $exception) {
                $error = $exception->getMessage();
            }
            check('een al gestopte certificatie kan niet nog eens stoppen', $error !== null);

            echo "Extra tijd\n";
            $id2 = start_certification([
                'perid' => '654321',
                'board' => $free,
                'location_id' => $locationId,
                'duration_minutes' => 5,
            ], $userId);
            $beforeEndsAt = strtotime(db()->query('SELECT ends_at FROM certifications WHERE id = ' . $id2)->fetchColumn());

            $newEndsAt = extend_certification($id2, 60, $userId);
            check('extend_certification verlengt ends_at', strtotime($newEndsAt) === $beforeEndsAt + 60);
            check('extensie wordt gelogd', certification_extension_total($id2) === 60);

            $error = null;
            try {
                extend_certification($id2, MAX_EXTENSION_SECONDS, $userId);
            } catch (InvalidArgumentException $exception) {
                $error = $exception->getMessage();
            }
            check('meer dan 120 minuten totale extra tijd wordt geweigerd', $error !== null);

            $error = null;
            try {
                extend_certification(999999, 60, $userId);
            } catch (InvalidArgumentException $exception) {
                $error = $exception->getMessage();
            }
            check('extra tijd op onbestaande certificatie wordt geweigerd', $error !== null);

            echo "Pauzeren\n";
            pause_certification($id2);
            $pausedState = board_state($location, $free);
            check('certificatie is gepauzeerd', $pausedState['certification']['paused'] === true);

            $error = null;
            try {
                pause_certification($id2);
            } catch (InvalidArgumentException $exception) {
                $error = $exception->getMessage();
            }
            check('een al gepauzeerde certificatie kan niet nogmaals pauzeren', $error !== null);

            $endsAtBeforeResume = strtotime(db()->query('SELECT ends_at FROM certifications WHERE id = ' . $id2)->fetchColumn());
            sleep(1);
            resume_certification($id2);
            $endsAtAfterResume = strtotime(db()->query('SELECT ends_at FROM certifications WHERE id = ' . $id2)->fetchColumn());
            check('hervatten verschuift ends_at met het pauze-interval', $endsAtAfterResume > $endsAtBeforeResume);
            check('certificatie is niet meer gepauzeerd na hervatten', !board_state($location, $free)['certification']['paused']);

            $error = null;
            try {
                resume_certification($id2);
            } catch (InvalidArgumentException $exception) {
                $error = $exception->getMessage();
            }
            check('een niet-gepauzeerde certificatie kan niet hervatten', $error !== null);

            echo "Statusconsistentie\n";
            // board_state mag nooit een tegenspreidige payload geven
            // (resterende tijd > 0 terwijl finished = true, of omgekeerd),
            // zodat klok en badge niet kunnen verspringen.
            $runningCert = board_state($location, $free)['certification'];
            check('lopende certificatie met resterende tijd is niet finished',
                $runningCert['remainingSeconds'] > 0 && $runningCert['finished'] === false);
            pause_certification($id2);
            $pausedCert = board_state($location, $free)['certification'];
            check('gepauzeerde certificatie is nooit finished', $pausedCert['finished'] === false);
            resume_certification($id2);

            stop_certification($id2);

            $id3 = start_certification([
                'perid' => '111222',
                'board' => $free,
                'location_id' => $locationId,
                'duration_minutes' => 5,
            ], $userId);
            db()->prepare('UPDATE certifications SET ends_at = ? WHERE id = ?')
                ->execute([date('Y-m-d H:i:s', time() - 5), $id3]);
            $expiredCert = board_state($location, $free)['certification'];
            check('afgelopen certificatie heeft 0 resterende seconden', $expiredCert['remainingSeconds'] === 0);
            check('afgelopen certificatie is finished', $expiredCert['finished'] === true);
            extend_certification($id3, 300, $userId);
            $extendedCert = board_state($location, $free)['certification'];
            check('verlengde certificatie is meteen niet meer finished',
                $extendedCert['remainingSeconds'] > 0 && $extendedCert['finished'] === false);
            db()->prepare('DELETE FROM certification_extensions WHERE certification_id = ?')->execute([$id3]);
            db()->prepare('DELETE FROM certifications WHERE id = ?')->execute([$id3]);

            echo "Auto-sluiten\n";
            db()->prepare('UPDATE certifications SET stopped_at = ? WHERE id = ?')
                ->execute([date('Y-m-d H:i:s', time() - AUTO_CLOSE_AFTER_SECONDS - 60), $id2]);
            $closedCount = auto_close_expired_certifications();
            check('auto_close_expired_certifications sluit verlopen certificaties', $closedCount >= 1);
            $autoClosedRow = db()->query('SELECT auto_closed FROM certifications WHERE id = ' . $id2)->fetch();
            check('certificatie is als auto_closed gemarkeerd', (int) $autoClosedRow['auto_closed'] === 1);

            db()->prepare('DELETE FROM certification_extensions WHERE certification_id = ?')->execute([$id2]);
            db()->prepare('DELETE FROM certifications WHERE id = ?')->execute([$id2]);

            db()->prepare('DELETE FROM certifications WHERE id = ?')->execute([$id]);
        }

        echo "Locaties\n";
        check('nieuwe locatie valt terug op Engels', $location['default_language'] === 'en');
        $duplicate = null;
        try {
            create_location($location['name']);
        } catch (InvalidArgumentException $exception) {
            $duplicate = $exception->getMessage();
        }
        check('dubbele locatienaam wordt geweigerd', $duplicate !== null);

        rename_location($locationId, $location['name'] . '-hernoemd', 'fr');
        $renamedLocation = find_location_by_id($locationId);
        check('locatie hernoemen werkt', $renamedLocation['name'] === $location['name'] . '-hernoemd');
        check('locatietaal wijzigen werkt', $renamedLocation['default_language'] === 'fr');

        save_board_rules($locationId, ['nl' => 'Regel 1', 'en' => 'Rule 1', 'fr' => '', 'de' => '']);
        $rulesLocation = find_location_by_id($locationId);
        check('bordregels worden bewaard per taal', $rulesLocation['board_rules_nl'] === 'Regel 1'
            && $rulesLocation['board_rules_en'] === 'Rule 1');
        check('lege regels worden NULL en leeg na hydrate', ($rulesLocation['board_rules_fr'] ?? '') === '');
        check('board_rules_for_language valt terug op Engels', board_rules_for_language($rulesLocation, 'fr') === 'Rule 1');

        check('resolve_location vindt via slug', resolve_location(location_slug($location['name'] . '-hernoemd'))['id'] === $locationId);
        check('resolve_location vindt via id', resolve_location((string) $locationId)['id'] === $locationId);

        echo "Wachtwoorden\n";
        $created = create_user('test-' . bin2hex(random_bytes(4)), null, 'expert', 'fr');
        check('wachtwoord wordt automatisch gegenereerd', $created['password'] !== null && strlen($created['password']) >= 8);
        $createdRow = db()->query('SELECT language FROM users WHERE id = ' . (int) $created['id'])->fetch();
        check('gebruikerstaal wordt bewaard', $createdRow['language'] === 'fr');

        change_password($userId, 'test-wachtwoord', 'nieuw-wachtwoord-123', 'nieuw-wachtwoord-123');
        $row = db()->query('SELECT password_hash FROM users WHERE id = ' . $userId)->fetch();
        check('wachtwoord wijzigen slaat nieuwe hash op', password_verify('nieuw-wachtwoord-123', $row['password_hash']));

        $error = null;
        try {
            change_password($userId, 'fout-wachtwoord', 'iets-nieuws-123', 'iets-nieuws-123');
        } catch (InvalidArgumentException $exception) {
            $error = $exception->getMessage();
        }
        check('foutief huidig wachtwoord wordt geweigerd', $error !== null);

        $error = null;
        try {
            change_password($userId, 'nieuw-wachtwoord-123', 'iets-nieuws-123', 'andere-bevestiging');
        } catch (InvalidArgumentException $exception) {
            $error = $exception->getMessage();
        }
        check('niet-overeenkomende bevestiging wordt geweigerd', $error !== null);

        db()->prepare('DELETE FROM users WHERE id = ?')->execute([$created['id']]);
    } finally {
        delete_location($locationId);
        db()->prepare('DELETE FROM users WHERE id = ?')->execute([$userId]);
    }
}

echo "\n$passed geslaagd, $failed gefaald, $skipped overgeslagen\n";
exit($failed === 0 ? 0 : 1);
