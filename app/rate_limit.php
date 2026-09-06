<?php
/** Eenvoudige rate limiting per IP-adres op basis van tijdelijke bestanden. */

declare(strict_types=1);

if (!defined('CERTIF_CLOCK')) {
    http_response_code(403);
    exit('Directe toegang is niet toegestaan.');
}

function rate_limit_check(?string $bucket = null, ?int $maxOverride = null, ?int $windowOverride = null): void
{
    $app = config('app');
    $max = $maxOverride ?? (int) $app['rate_limit_max'];
    $window = $windowOverride ?? (int) $app['rate_limit_window'];
    if ($max <= 0 || $window <= 0 || PHP_SAPI === 'cli') {
        return;
    }

    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $directory = sys_get_temp_dir() . '/certif-clock-rate';
    if (!is_dir($directory) && !@mkdir($directory, 0700, true) && !is_dir($directory)) {
        return;
    }

    $file = $directory . '/' . hash('sha256', $ip . '|' . ($bucket ?? 'global')) . '.json';
    $now = time();
    $state = ['count' => 0, 'reset_at' => $now + $window];

    $handle = @fopen($file, 'c+');
    if ($handle === false) {
        return;
    }
    if (flock($handle, LOCK_EX)) {
        $contents = stream_get_contents($handle);
        $decoded = $contents !== '' ? json_decode($contents, true) : null;
        if (is_array($decoded) && isset($decoded['reset_at'], $decoded['count'])
            && (int) $decoded['reset_at'] > $now) {
            $state = ['count' => (int) $decoded['count'] + 1, 'reset_at' => (int) $decoded['reset_at']];
        } else {
            $state = ['count' => 1, 'reset_at' => $now + $window];
        }
        ftruncate($handle, 0);
        rewind($handle);
        fwrite($handle, (string) json_encode($state));
        fflush($handle);
        flock($handle, LOCK_UN);
    }
    fclose($handle);

    if ($state['count'] > $max) {
        http_response_code(429);
        header('Retry-After: ' . max(1, $state['reset_at'] - $now));
        exit('Te veel aanvragen, probeer later opnieuw.');
    }
}
