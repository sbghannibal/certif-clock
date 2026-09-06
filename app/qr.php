<?php
/**
 * Compacte QR-code generator (byte-modus, foutcorrectieniveau M, versies 1-10)
 * zonder externe afhankelijkheden. Voldoende voor de korte bord-URL's.
 */

declare(strict_types=1);

if (!defined('CERTIF_CLOCK')) {
    http_response_code(403);
    exit('Directe toegang is niet toegestaan.');
}

final class QrCode
{
    /** Maximaal aantal databytes per versie bij foutcorrectieniveau M. */
    private const CAPACITY = [
        1 => 14, 2 => 26, 3 => 42, 4 => 62, 5 => 84,
        6 => 106, 7 => 122, 8 => 152, 9 => 180, 10 => 213,
    ];

    /** [ec-codewords per blok, [[aantal blokken, databytes per blok], ...]] */
    private const BLOCKS = [
        1 => [10, [[1, 16]]],
        2 => [16, [[1, 28]]],
        3 => [26, [[1, 44]]],
        4 => [18, [[2, 32]]],
        5 => [24, [[2, 43]]],
        6 => [16, [[4, 27]]],
        7 => [18, [[4, 31]]],
        8 => [22, [[2, 38], [2, 39]]],
        9 => [22, [[3, 36], [2, 37]]],
        10 => [26, [[4, 43], [1, 44]]],
    ];

    private const ALIGNMENT = [
        1 => [], 2 => [6, 18], 3 => [6, 22], 4 => [6, 26], 5 => [6, 30],
        6 => [6, 34], 7 => [6, 22, 38], 8 => [6, 24, 42], 9 => [6, 26, 46], 10 => [6, 28, 50],
    ];

    /** @var int[] */
    private static array $expTable = [];
    /** @var int[] */
    private static array $logTable = [];

    /**
     * Genereert de QR-matrix voor $text als array van rijen met 0/1 waarden.
     *
     * @return array<int, array<int, int>>
     */
    public static function matrix(string $text): array
    {
        $version = self::pickVersion(strlen($text));
        $codewords = self::encodeCodewords($text, $version);
        $size = 17 + 4 * $version;

        $best = null;
        $bestPenalty = PHP_INT_MAX;
        for ($mask = 0; $mask < 8; $mask++) {
            [$modules, $functions] = self::buildTemplate($version, $size);
            self::placeData($modules, $functions, $codewords, $size);
            self::applyMask($modules, $functions, $mask, $size);
            self::placeFormatInfo($modules, $mask, $size);
            $penalty = self::penalty($modules, $size);
            if ($penalty < $bestPenalty) {
                $bestPenalty = $penalty;
                $best = $modules;
            }
        }

        return $best;
    }

    private static function pickVersion(int $length): int
    {
        foreach (self::CAPACITY as $version => $capacity) {
            if ($length <= $capacity) {
                return $version;
            }
        }

        throw new InvalidArgumentException('Tekst is te lang voor deze QR-generator.');
    }

    /** Bouwt de volledige (geïnterleavede) reeks codewords voor de gegeven versie. */
    private static function encodeCodewords(string $text, int $version): array
    {
        [$ecPerBlock, $groups] = self::BLOCKS[$version];
        $dataCodewords = 0;
        foreach ($groups as [$count, $size]) {
            $dataCodewords += $count * $size;
        }

        $countBits = $version < 10 ? 8 : 16;
        $bits = '0100' . str_pad(decbin(strlen($text)), $countBits, '0', STR_PAD_LEFT);
        for ($i = 0, $len = strlen($text); $i < $len; $i++) {
            $bits .= str_pad(decbin(ord($text[$i])), 8, '0', STR_PAD_LEFT);
        }

        $capacityBits = $dataCodewords * 8;
        $bits .= str_repeat('0', min(4, $capacityBits - strlen($bits)));
        if (strlen($bits) % 8 !== 0) {
            $bits .= str_repeat('0', 8 - (strlen($bits) % 8));
        }

        $data = [];
        foreach (str_split($bits, 8) as $byte) {
            $data[] = bindec($byte);
        }
        $padBytes = [236, 17];
        $padIndex = 0;
        while (count($data) < $dataCodewords) {
            $data[] = $padBytes[$padIndex % 2];
            $padIndex++;
        }

        $dataBlocks = [];
        $ecBlocks = [];
        $offset = 0;
        foreach ($groups as [$count, $size]) {
            for ($i = 0; $i < $count; $i++) {
                $block = array_slice($data, $offset, $size);
                $offset += $size;
                $dataBlocks[] = $block;
                $ecBlocks[] = self::reedSolomon($block, $ecPerBlock);
            }
        }

        $result = [];
        $maxData = max(array_map('count', $dataBlocks));
        for ($i = 0; $i < $maxData; $i++) {
            foreach ($dataBlocks as $block) {
                if (isset($block[$i])) {
                    $result[] = $block[$i];
                }
            }
        }
        for ($i = 0; $i < $ecPerBlock; $i++) {
            foreach ($ecBlocks as $block) {
                $result[] = $block[$i];
            }
        }

        return $result;
    }

    private static function initGaloisTables(): void
    {
        if (self::$expTable !== []) {
            return;
        }
        $value = 1;
        for ($i = 0; $i < 256; $i++) {
            self::$expTable[$i] = $value;
            self::$logTable[$value] = $i;
            $value <<= 1;
            if ($value & 0x100) {
                $value ^= 0x11D;
            }
        }
        for ($i = 256; $i < 512; $i++) {
            self::$expTable[$i] = self::$expTable[$i - 255];
        }
    }

    private static function gfMultiply(int $a, int $b): int
    {
        if ($a === 0 || $b === 0) {
            return 0;
        }

        return self::$expTable[(self::$logTable[$a] + self::$logTable[$b]) % 255];
    }

    /** @param int[] $data */
    private static function reedSolomon(array $data, int $ecLength): array
    {
        self::initGaloisTables();

        $generator = [1];
        for ($i = 0; $i < $ecLength; $i++) {
            $next = array_fill(0, count($generator) + 1, 0);
            foreach ($generator as $index => $coefficient) {
                $next[$index] ^= $coefficient;
                $next[$index + 1] ^= self::gfMultiply($coefficient, self::$expTable[$i]);
            }
            $generator = $next;
        }
        $divisor = array_slice($generator, 1);

        $remainder = array_fill(0, $ecLength, 0);
        foreach ($data as $byte) {
            $factor = $byte ^ $remainder[0];
            array_shift($remainder);
            $remainder[] = 0;
            foreach ($divisor as $index => $coefficient) {
                $remainder[$index] ^= self::gfMultiply($coefficient, $factor);
            }
        }

        return $remainder;
    }

    /**
     * Bouwt de matrix met alle functiepatronen (finder, timing, alignment,
     * gereserveerde format- en versievelden).
     */
    private static function buildTemplate(int $version, int $size): array
    {
        $modules = array_fill(0, $size, array_fill(0, $size, 0));
        $functions = array_fill(0, $size, array_fill(0, $size, false));

        $finder = static function (int $row, int $col) use (&$modules, &$functions, $size): void {
            for ($r = -1; $r <= 7; $r++) {
                for ($c = -1; $c <= 7; $c++) {
                    $rr = $row + $r;
                    $cc = $col + $c;
                    if ($rr < 0 || $rr >= $size || $cc < 0 || $cc >= $size) {
                        continue;
                    }
                    $inRing = ($r >= 0 && $r <= 6 && ($c === 0 || $c === 6))
                        || ($c >= 0 && $c <= 6 && ($r === 0 || $r === 6));
                    $inCore = $r >= 2 && $r <= 4 && $c >= 2 && $c <= 4;
                    $modules[$rr][$cc] = ($inRing || $inCore) ? 1 : 0;
                    $functions[$rr][$cc] = true;
                }
            }
        };
        $finder(0, 0);
        $finder(0, $size - 7);
        $finder($size - 7, 0);

        for ($i = 8; $i < $size - 8; $i++) {
            $bit = $i % 2 === 0 ? 1 : 0;
            $modules[6][$i] = $bit;
            $functions[6][$i] = true;
            $modules[$i][6] = $bit;
            $functions[$i][6] = true;
        }

        $centers = self::ALIGNMENT[$version];
        foreach ($centers as $row) {
            foreach ($centers as $col) {
                if (($row === 6 && $col === 6)
                    || ($row === 6 && $col === $size - 7)
                    || ($row === $size - 7 && $col === 6)) {
                    continue;
                }
                for ($r = -2; $r <= 2; $r++) {
                    for ($c = -2; $c <= 2; $c++) {
                        $modules[$row + $r][$col + $c] = (max(abs($r), abs($c)) !== 1) ? 1 : 0;
                        $functions[$row + $r][$col + $c] = true;
                    }
                }
            }
        }

        // Donkere module en gereserveerde formatvelden.
        $modules[$size - 8][8] = 1;
        $functions[$size - 8][8] = true;
        for ($i = 0; $i < 9; $i++) {
            $functions[8][$i] = true;
            $functions[$i][8] = true;
        }
        for ($i = 0; $i < 8; $i++) {
            $functions[8][$size - 1 - $i] = true;
            $functions[$size - 1 - $i][8] = true;
        }

        if ($version >= 7) {
            $bits = self::versionInfoBits($version);
            for ($i = 0; $i < 18; $i++) {
                $bit = ($bits >> $i) & 1;
                $row = intdiv($i, 3);
                $col = $size - 11 + ($i % 3);
                $modules[$row][$col] = $bit;
                $functions[$row][$col] = true;
                $modules[$col][$row] = $bit;
                $functions[$col][$row] = true;
            }
        }

        return [$modules, $functions];
    }

    private static function versionInfoBits(int $version): int
    {
        $remainder = $version;
        for ($i = 0; $i < 12; $i++) {
            $remainder = ($remainder << 1) ^ ((($remainder >> 11) & 1) * 0x1F25);
        }

        return ($version << 12) | $remainder;
    }

    /** @param int[] $codewords */
    private static function placeData(array &$modules, array $functions, array $codewords, int $size): void
    {
        $bitIndex = 0;
        $totalBits = count($codewords) * 8;
        $upward = true;
        for ($right = $size - 1; $right >= 1; $right -= 2) {
            if ($right === 6) {
                $right = 5;
            }
            for ($step = 0; $step < $size; $step++) {
                $row = $upward ? $size - 1 - $step : $step;
                for ($offset = 0; $offset < 2; $offset++) {
                    $col = $right - $offset;
                    if ($functions[$row][$col]) {
                        continue;
                    }
                    $bit = 0;
                    if ($bitIndex < $totalBits) {
                        $bit = ($codewords[$bitIndex >> 3] >> (7 - ($bitIndex & 7))) & 1;
                        $bitIndex++;
                    }
                    $modules[$row][$col] = $bit;
                }
            }
            $upward = !$upward;
        }
    }

    private static function maskBit(int $mask, int $row, int $col): bool
    {
        switch ($mask) {
            case 0: return ($row + $col) % 2 === 0;
            case 1: return $row % 2 === 0;
            case 2: return $col % 3 === 0;
            case 3: return ($row + $col) % 3 === 0;
            case 4: return (intdiv($row, 2) + intdiv($col, 3)) % 2 === 0;
            case 5: return ($row * $col) % 2 + ($row * $col) % 3 === 0;
            case 6: return ((($row * $col) % 2 + ($row * $col) % 3) % 2) === 0;
            default: return ((($row + $col) % 2 + ($row * $col) % 3) % 2) === 0;
        }
    }

    private static function applyMask(array &$modules, array $functions, int $mask, int $size): void
    {
        for ($row = 0; $row < $size; $row++) {
            for ($col = 0; $col < $size; $col++) {
                if (!$functions[$row][$col] && self::maskBit($mask, $row, $col)) {
                    $modules[$row][$col] ^= 1;
                }
            }
        }
    }

    private static function placeFormatInfo(array &$modules, int $mask, int $size): void
    {
        $data = (0b00 << 3) | $mask; // 0b00 = foutcorrectieniveau M
        $remainder = $data;
        for ($i = 0; $i < 10; $i++) {
            $remainder = ($remainder << 1) ^ ((($remainder >> 9) & 1) * 0x537);
        }
        $bits = (($data << 10) | $remainder) ^ 0x5412;

        for ($i = 0; $i < 15; $i++) {
            $bit = ($bits >> $i) & 1;

            // Verticale kopie naast het linkerbovenste en linkeronderste zoekpatroon.
            if ($i < 6) {
                $modules[$i][8] = $bit;
            } elseif ($i < 8) {
                $modules[$i + 1][8] = $bit;
            } else {
                $modules[$size - 15 + $i][8] = $bit;
            }

            // Horizontale kopie naast het linkerbovenste en rechterbovenste zoekpatroon.
            if ($i < 8) {
                $modules[8][$size - 1 - $i] = $bit;
            } elseif ($i === 8) {
                $modules[8][7] = $bit;
            } else {
                $modules[8][14 - $i] = $bit;
            }
        }

        $modules[$size - 8][8] = 1;
    }

    private static function penalty(array $modules, int $size): int
    {
        $score = 0;

        // Regel 1: opeenvolgende modules met dezelfde kleur.
        for ($i = 0; $i < $size; $i++) {
            for ($direction = 0; $direction < 2; $direction++) {
                $run = 1;
                for ($j = 1; $j < $size; $j++) {
                    $current = $direction === 0 ? $modules[$i][$j] : $modules[$j][$i];
                    $previous = $direction === 0 ? $modules[$i][$j - 1] : $modules[$j - 1][$i];
                    if ($current === $previous) {
                        $run++;
                        continue;
                    }
                    if ($run >= 5) {
                        $score += $run - 2;
                    }
                    $run = 1;
                }
                if ($run >= 5) {
                    $score += $run - 2;
                }
            }
        }

        // Regel 2: blokken van 2x2 met dezelfde kleur.
        for ($row = 0; $row < $size - 1; $row++) {
            for ($col = 0; $col < $size - 1; $col++) {
                $value = $modules[$row][$col];
                if ($value === $modules[$row][$col + 1]
                    && $value === $modules[$row + 1][$col]
                    && $value === $modules[$row + 1][$col + 1]) {
                    $score += 3;
                }
            }
        }

        // Regel 3: patronen die op een finder lijken.
        $patterns = [
            [1, 0, 1, 1, 1, 0, 1, 0, 0, 0, 0],
            [0, 0, 0, 0, 1, 0, 1, 1, 1, 0, 1],
        ];
        for ($i = 0; $i < $size; $i++) {
            for ($j = 0; $j <= $size - 11; $j++) {
                foreach ($patterns as $pattern) {
                    $horizontal = true;
                    $vertical = true;
                    for ($k = 0; $k < 11; $k++) {
                        if ($modules[$i][$j + $k] !== $pattern[$k]) {
                            $horizontal = false;
                        }
                        if ($modules[$j + $k][$i] !== $pattern[$k]) {
                            $vertical = false;
                        }
                    }
                    if ($horizontal) {
                        $score += 40;
                    }
                    if ($vertical) {
                        $score += 40;
                    }
                }
            }
        }

        // Regel 4: verhouding donkere modules.
        $dark = 0;
        foreach ($modules as $row) {
            $dark += array_sum($row);
        }
        $percentage = ($dark * 100) / ($size * $size);
        $score += (int) (abs($percentage - 50) / 5) * 10;

        return $score;
    }

    /** Rendert de QR-code als PNG-string. */
    public static function png(string $text, int $scale = 8, int $quietZone = 4): string
    {
        $matrix = self::matrix($text);
        $size = count($matrix);
        $pixels = ($size + 2 * $quietZone) * $scale;

        $image = imagecreatetruecolor($pixels, $pixels);
        $white = imagecolorallocate($image, 255, 255, 255);
        $dark = imagecolorallocate($image, 28, 12, 46);
        imagefilledrectangle($image, 0, 0, $pixels - 1, $pixels - 1, $white);

        for ($row = 0; $row < $size; $row++) {
            for ($col = 0; $col < $size; $col++) {
                if ($matrix[$row][$col] !== 1) {
                    continue;
                }
                $x = ($col + $quietZone) * $scale;
                $y = ($row + $quietZone) * $scale;
                imagefilledrectangle($image, $x, $y, $x + $scale - 1, $y + $scale - 1, $dark);
            }
        }

        ob_start();
        imagepng($image);
        imagedestroy($image);

        return (string) ob_get_clean();
    }
}
