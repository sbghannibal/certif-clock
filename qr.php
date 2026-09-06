<?php
/** QR-code (PNG) die naar de directe link van een bord verwijst. */

declare(strict_types=1);

define('CERTIF_CLOCK', true);
require_once __DIR__ . '/app/bootstrap.php';
require_once __DIR__ . '/app/qr.php';

app_boot();

$board = $_GET['board'] ?? null;
if (!is_valid_board($board)) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    exit('Onbekend bord.');
}

$url = base_url() . '/board.php?board=' . (int) $board;

header('Content-Type: image/png');
header('Cache-Control: public, max-age=3600');
echo QrCode::png($url, 6);
