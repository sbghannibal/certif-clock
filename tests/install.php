<?php
/** Installer-tests; MySQL uitsluitend in een zelf aangemaakte tijdelijke database. */

declare(strict_types=1);

define('CERTIF_CLOCK', true);
require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/install.php';

ob_start();
session_start();

function install_input(string $username = 'eerste-owner'): array
{
    return [
        'username' => $username,
        'password' => 'veilig-testwachtwoord',
        'password_confirm' => 'veilig-testwachtwoord',
        'csrf_token' => 'installer-test-token',
    ];
}

$_SESSION = ['csrf_token' => 'installer-test-token'];
if (($argv[1] ?? '') === '--worker') {
    try {
        installer_install(db(), install_input($argv[2]));
        echo 'installed';
    } catch (Throwable $exception) {
        echo 'blocked';
    }
    exit;
}

$failed = 0;
function verify_install(string $name, bool $condition): void
{
    global $failed;
    echo ($condition ? 'ok   ' : 'FAIL ') . $name . "\n";
    if (!$condition) {
        $failed++;
    }
}

function install_rejected(callable $action): bool
{
    try {
        $action();
    } catch (Throwable $exception) {
        return true;
    }

    return false;
}

$schema = installer_schema();
verify_install('canoniek schema bevat alle actuele tabellen', $schema['tables'] === [
    'users', 'settings', 'locations', 'certifications', 'certification_extensions',
]);
verify_install('schema-DDL is gescheiden van seeds', count($schema['ddl']) === 5 && count($schema['seeds']) === 1);
verify_install('geldige invoer wordt getrimd', installer_validate(install_input('  owner  '))[0] === 'owner');
$invalid = [
    'korte gebruikersnaam' => ['username' => 'ab'],
    'lange gebruikersnaam' => ['username' => str_repeat('é', 101)],
    'kort wachtwoord' => ['password' => '1234567', 'password_confirm' => '1234567'],
    'afwijkende bevestiging' => ['password_confirm' => 'anders'],
    'lege bevestiging' => ['password_confirm' => ''],
    'ongeldig CSRF' => ['csrf_token' => 'onjuist'],
    'array-invoer' => ['username' => ['owner']],
];
foreach ($invalid as $name => $overrides) {
    verify_install($name, install_rejected(fn () => installer_validate(array_replace(install_input(), $overrides))));
}
$missing = install_input();
unset($missing['csrf_token']);
verify_install('ontbrekend CSRF', install_rejected(fn () => installer_validate($missing)));
$missingConfirm = install_input();
unset($missingConfirm['password_confirm']);
verify_install('ontbrekende bevestiging', install_rejected(fn () => installer_validate($missingConfirm)));

$unreadable = new class extends PDO {
    public function __construct()
    {
    }

    public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): PDOStatement|false
    {
        throw new PDOException('Inspectie geweigerd');
    }
};
verify_install('inspectiefout wordt niet als leeg beschouwd', install_rejected(fn () => installer_available($unreadable)));

if (getenv('INSTALL_TEST_MYSQL') !== '1') {
    echo "skip MySQL-integratie (zet INSTALL_TEST_MYSQL=1 op een testserver)\n";
    exit($failed === 0 ? 0 : 1);
}

// Configuratie nog niet via config() cachen: db() moet de nieuwe testnaam zien.
$configuration = require dirname(__DIR__) . '/config.php';
$cfg = $configuration['db'];
try {
    $server = new PDO(
        sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $cfg['host'], $cfg['port']),
        $cfg['user'],
        $cfg['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $exception) {
    echo "skip MySQL-integratie (geen verbinding met testserver)\n";
    exit($failed === 0 ? 0 : 1);
}

$database = 'certif_clock_test_' . bin2hex(random_bytes(12));
// Geen IF NOT EXISTS: een bestaande database mag nooit overgenomen worden.
$server->exec("CREATE DATABASE `$database` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
putenv('DB_NAME=' . $database);

function empty_test_schema(PDO $pdo): void
{
    // Alleen aangeroepen binnen de hierboven nieuw aangemaakte testdatabase.
    foreach (array_reverse(installer_schema()['tables']) as $table) {
        $pdo->exec("DROP TABLE IF EXISTS `$table`");
    }
}

try {
    $pdo = db();
    verify_install('lege database beschikbaar; inspectie schrijft niets', installer_available($pdo)
        && (int) $pdo->query('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()')->fetchColumn() === 0);
    foreach ($invalid as $name => $overrides) {
        verify_install("$name schrijft geen schema", install_rejected(
            fn () => installer_install($pdo, array_replace(install_input(), $overrides))
        ) && (int) $pdo->query('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()')->fetchColumn() === 0);
    }
    verify_install('ontbrekend CSRF schrijft niets', install_rejected(fn () => installer_install($pdo, $missing))
        && installer_available($pdo));
    verify_install('ontbrekende bevestiging schrijft niets', install_rejected(fn () => installer_install($pdo, $missingConfirm))
        && installer_available($pdo));

    installer_install($pdo, install_input());
    $owner = $pdo->query('SELECT * FROM users')->fetchAll();
    verify_install('precies één gehashte Nederlandstalige owner', count($owner) === 1
        && $owner[0]['role'] === 'owner' && $owner[0]['language'] === 'nl'
        && password_verify(install_input()['password'], $owner[0]['password_hash']));
    verify_install('alle actuele tabellen aangemaakt', (int) $pdo->query(
        'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()'
    )->fetchColumn() === count($schema['tables']));
    verify_install('standaardlocaties aangemaakt', $pdo->query('SELECT name FROM locations ORDER BY name')->fetchAll(PDO::FETCH_COLUMN)
        === ['Aarschot', 'Berchem', 'Gent']);
    verify_install('voltooiing duurzaam vastgelegd', $pdo->query(
        "SELECT setting_value FROM settings WHERE setting_key = 'installation_completed'"
    )->fetchColumn() === '1');

    // De bestaande loginfunctie werkt met het installer-account.
    session_start_secure();
    verify_install('bestaande login accepteert owner', attempt_login(install_input()['username'], install_input()['password']));
    verify_install('GET-inspectie van gevulde installatie gesloten', !installer_available($pdo));
    verify_install('stale POST met andere naam geblokkeerd', install_rejected(
        fn () => installer_install($pdo, install_input('tweede-owner'))
    ) && (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn() === 1);
    $pdo->exec('DELETE FROM users');
    verify_install('gevulde database zonder user gesloten', !installer_available($pdo)
        && install_rejected(fn () => installer_install($pdo, install_input())));
    $pdo->exec('DELETE FROM locations');
    verify_install('completion alleen houdt installatie gesloten', !installer_available($pdo)
        && install_rejected(fn () => installer_install($pdo, install_input())));

    empty_test_schema($pdo);
    // Handmatige import inclusief seeddata: geen publieke bootstrap.
    foreach ($schema['ddl'] as $ddl) {
        $pdo->exec($ddl);
    }
    foreach ($schema['seeds'] as $seed) {
        $pdo->exec($seed);
    }
    verify_install('vooraf geïmporteerde seeds blokkeren', !installer_available($pdo)
        && install_rejected(fn () => installer_install($pdo, install_input()))
        && (int) $pdo->query('SELECT COUNT(*) FROM locations')->fetchColumn() === 3
        && (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn() === 0);

    empty_test_schema($pdo);
    $pdo->exec($schema['ddl'][0]);
    verify_install('lege gedeeltelijke installatie herbruikbaar', installer_available($pdo));
    installer_install($pdo, install_input());
    verify_install('herstart voltooit gedeeltelijk schema', !installer_available($pdo));

    empty_test_schema($pdo);
    $pdo->exec('CREATE TABLE users (username VARCHAR(100)) ENGINE=InnoDB');
    verify_install('DDL-fout wordt gemeld', install_rejected(fn () => installer_install($pdo, install_input())));
    verify_install('DDL-fout laat geen seeddata achter', (int) $pdo->query('SELECT COUNT(*) FROM locations')->fetchColumn() === 0
        && (int) $pdo->query('SELECT COUNT(*) FROM settings')->fetchColumn() === 0);

    empty_test_schema($pdo);
    // Verkeerde bestaande kolom veroorzaakt een fout na DDL en seed-inserts.
    $pdo->exec('CREATE TABLE users (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY) ENGINE=InnoDB');
    verify_install('gedeeltelijke fout wordt gemeld', install_rejected(fn () => installer_install($pdo, install_input())));
    verify_install('seeddata en completion teruggedraaid', (int) $pdo->query('SELECT COUNT(*) FROM locations')->fetchColumn() === 0
        && (int) $pdo->query('SELECT COUNT(*) FROM settings')->fetchColumn() === 0);
    // Herstel door de testbeheerder, met respect voor de foreign keys.
    $pdo->exec('DROP TABLE certification_extensions');
    $pdo->exec('DROP TABLE certifications');
    $pdo->exec('DROP TABLE users');
    installer_install($pdo, install_input());
    verify_install('herstel na transactiefout veilig', (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn() === 1);

    empty_test_schema($pdo);
    $pdo->exec('CREATE TABLE unrelated (id INT) ENGINE=InnoDB');
    verify_install('onbekende lege tabel conservatief geblokkeerd', !installer_available($pdo));
    $pdo->exec('DROP TABLE unrelated');
    $pdo->exec('CREATE TABLE users (id INT) ENGINE=MyISAM');
    verify_install('niet-transactioneel schema geblokkeerd', !installer_available($pdo));
    $pdo->exec('DROP TABLE users');

    if (function_exists('proc_open')) {
        $processes = [];
        foreach (['parallel-een', 'parallel-twee'] as $name) {
            $pipes = [];
            $process = proc_open([PHP_BINARY, __FILE__, '--worker', $name], [
                1 => ['pipe', 'w'], 2 => ['pipe', 'w'],
            ], $pipes);
            $processes[] = [$process, $pipes];
        }
        $results = [];
        foreach ($processes as [$process, $pipes]) {
            $results[] = stream_get_contents($pipes[1]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            proc_close($process);
        }
        sort($results);
        verify_install('parallelle requests maken exact één owner', $results === ['blocked', 'installed']
            && (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn() === 1);
    } else {
        echo "skip paralleltest (proc_open ontbreekt)\n";
        installer_install($pdo, install_input());
    }

    // Bestaande suite uitsluitend op deze geïsoleerde geïnstalleerde database.
    passthru(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/run.php'), $status);
    verify_install('bestaande tests', $status === 0);
} finally {
    $server->exec("DROP DATABASE `$database`");
}

exit($failed === 0 ? 0 : 1);
