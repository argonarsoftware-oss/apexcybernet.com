<?php
/**
 * Pure PHP QR Code Generator — no external dependencies
 * Based on the QR code specification (ISO/IEC 18004)
 * Supports alphanumeric mode which is perfect for EMVCo data
 *
 * Usage:
 *   require_once 'qrcode.php';
 *   $png = QRCodeGenerator::png('Hello World', 400);
 *   // $png is raw PNG binary data
 */

class QRCodeGenerator {

    // Error correction level M (15%)
    const EC_LEVEL = 1; // 0=L, 1=M, 2=Q, 3=H

    // Alphanumeric character set
    const ALPHANUM = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ $%*+-./:';

    /**
     * Generate QR code as PNG binary string
     */
    public static function png(string $data, int $size = 400, int $margin = 4): string {
        $modules = self::encode($data);
        $dim = count($modules);

        $scale = max(1, intdiv($size, $dim + $margin * 2));
        $imgSize = ($dim + $margin * 2) * $scale;

        $im = imagecreatetruecolor($imgSize, $imgSize);
        $white = imagecolorallocate($im, 255, 255, 255);
        $black = imagecolorallocate($im, 0, 0, 0);
        imagefill($im, 0, 0, $white);

        for ($y = 0; $y < $dim; $y++) {
            for ($x = 0; $x < $dim; $x++) {
                if ($modules[$y][$x]) {
                    imagefilledrectangle($im,
                        ($x + $margin) * $scale,
                        ($y + $margin) * $scale,
                        ($x + $margin + 1) * $scale - 1,
                        ($y + $margin + 1) * $scale - 1,
                        $black
                    );
                }
            }
        }

        ob_start();
        imagepng($im);
        $png = ob_get_clean();
        imagedestroy($im);

        return $png;
    }

    /**
     * Generate QR code and save to file
     */
    public static function toFile(string $data, string $filepath, int $size = 400): void {
        $png = self::png($data, $size);
        file_put_contents($filepath, $png);
    }

    /**
     * Encode data into QR code module matrix
     */
    private static function encode(string $data): array {
        // Always use byte mode to preserve case (important for EMVCo data)
        $isAlphanumeric = false;
        $encoded = self::encodeByte($data);
        $mode = 0b0100;
        $charCount = strlen($data);

        // Find minimum version
        $version = self::findVersion($charCount, $isAlphanumeric);
        $totalBits = self::getDataCapacity($version);

        // Build data bitstream
        $bits = '';
        $bits .= str_pad(decbin($mode), 4, '0', STR_PAD_LEFT);

        $ccBits = $isAlphanumeric ? self::getCharCountBits($version, 'alphanumeric') : self::getCharCountBits($version, 'byte');
        $bits .= str_pad(decbin($charCount), $ccBits, '0', STR_PAD_LEFT);
        $bits .= $encoded;

        // Terminator
        $bits .= str_pad('', min(4, $totalBits - strlen($bits)), '0');

        // Pad to byte boundary
        if (strlen($bits) % 8 !== 0) {
            $bits .= str_pad('', 8 - (strlen($bits) % 8), '0');
        }

        // Pad bytes
        $padBytes = [0xEC, 0x11];
        $padIdx = 0;
        while (strlen($bits) < $totalBits) {
            $bits .= str_pad(decbin($padBytes[$padIdx % 2]), 8, '0', STR_PAD_LEFT);
            $padIdx++;
        }

        // Convert to bytes
        $dataBytes = [];
        for ($i = 0; $i < strlen($bits); $i += 8) {
            $dataBytes[] = bindec(substr($bits, $i, 8));
        }

        // Generate error correction
        $ecInfo = self::getECInfo($version);
        $blocks = self::splitBlocks($dataBytes, $ecInfo);
        $ecBlocks = [];
        foreach ($blocks as $block) {
            $ecBlocks[] = self::generateEC($block, $ecInfo['ecPerBlock']);
        }

        // Interleave
        $interleaved = self::interleave($blocks, $ecBlocks);

        // Place modules
        $dim = 17 + $version * 4;
        $modules = array_fill(0, $dim, array_fill(0, $dim, null));
        $reserved = array_fill(0, $dim, array_fill(0, $dim, false));

        self::placeFinderPatterns($modules, $reserved, $dim);
        self::placeAlignmentPatterns($modules, $reserved, $version, $dim);
        self::placeTimingPatterns($modules, $reserved, $dim);
        self::placeDarkModule($modules, $reserved, $version);
        self::reserveFormatInfo($reserved, $dim);
        if ($version >= 7) self::reserveVersionInfo($reserved, $version, $dim);

        self::placeDataBits($modules, $reserved, $interleaved, $dim);

        // Apply best mask
        $bestMask = self::findBestMask($modules, $reserved, $dim);
        self::applyMask($modules, $reserved, $bestMask, $dim);
        self::placeFormatInfo($modules, $bestMask, $dim);
        if ($version >= 7) self::placeVersionInfo($modules, $version, $dim);

        // Convert nulls to false
        for ($y = 0; $y < $dim; $y++) {
            for ($x = 0; $x < $dim; $x++) {
                if ($modules[$y][$x] === null) $modules[$y][$x] = false;
            }
        }

        return $modules;
    }

    private static function encodeAlphanumeric(string $data): string {
        $bits = '';
        for ($i = 0; $i < strlen($data); $i += 2) {
            if ($i + 1 < strlen($data)) {
                $val = strpos(self::ALPHANUM, $data[$i]) * 45 + strpos(self::ALPHANUM, $data[$i + 1]);
                $bits .= str_pad(decbin($val), 11, '0', STR_PAD_LEFT);
            } else {
                $val = strpos(self::ALPHANUM, $data[$i]);
                $bits .= str_pad(decbin($val), 6, '0', STR_PAD_LEFT);
            }
        }
        return $bits;
    }

    private static function encodeByte(string $data): string {
        $bits = '';
        for ($i = 0; $i < strlen($data); $i++) {
            $bits .= str_pad(decbin(ord($data[$i])), 8, '0', STR_PAD_LEFT);
        }
        return $bits;
    }

    private static function findVersion(int $charCount, bool $isAlphanumeric): int {
        // Data capacities for EC level M (alphanumeric / byte)
        $caps = [
            1 => [20, 14], 2 => [38, 26], 3 => [61, 42], 4 => [90, 62],
            5 => [122, 84], 6 => [154, 106], 7 => [178, 122], 8 => [221, 152],
            9 => [262, 180], 10 => [311, 213], 11 => [366, 251], 12 => [419, 287],
            13 => [483, 331], 14 => [528, 362], 15 => [600, 412], 16 => [656, 450],
        ];
        $idx = $isAlphanumeric ? 0 : 1;
        foreach ($caps as $v => $cap) {
            if ($charCount <= $cap[$idx]) return $v;
        }
        return 16;
    }

    private static function getCharCountBits(int $version, string $mode): int {
        if ($mode === 'alphanumeric') return $version <= 9 ? 9 : 11;
        return $version <= 9 ? 8 : 16; // byte mode
    }

    private static function getDataCapacity(int $version): int {
        // Total data codewords * 8 for EC level M
        $caps = [0, 128, 224, 352, 512, 688, 864, 992, 1232, 1456, 1728,
                 2032, 2320, 2672, 2920, 3320, 3624];
        return $caps[$version] ?? 3624;
    }

    private static function getECInfo(int $version): array {
        // [total codewords, ec per block, blocks group1, data per block g1, blocks group2, data per block g2]
        $table = [
            1  => [26, 10, 1, 16, 0, 0],
            2  => [44, 16, 1, 28, 0, 0],
            3  => [70, 26, 1, 44, 0, 0],
            4  => [100, 18, 2, 32, 0, 0],
            5  => [134, 24, 2, 43, 0, 0],
            6  => [172, 16, 4, 27, 0, 0],
            7  => [196, 18, 4, 31, 0, 0],
            8  => [242, 22, 2, 38, 2, 39],
            9  => [292, 22, 3, 36, 2, 37],
            10 => [346, 26, 4, 43, 1, 44],
            11 => [404, 30, 1, 50, 4, 51],
            12 => [466, 22, 6, 36, 2, 37],
            13 => [532, 22, 8, 37, 1, 38],
            14 => [581, 24, 4, 40, 5, 41],
            15 => [655, 24, 5, 41, 5, 42],
            16 => [733, 28, 7, 45, 3, 46],
        ];
        $info = $table[$version] ?? $table[10];
        return [
            'total' => $info[0],
            'ecPerBlock' => $info[1],
            'g1Blocks' => $info[2],
            'g1DataPer' => $info[3],
            'g2Blocks' => $info[4],
            'g2DataPer' => $info[5],
        ];
    }

    private static function splitBlocks(array $data, array $ecInfo): array {
        $blocks = [];
        $offset = 0;
        for ($i = 0; $i < $ecInfo['g1Blocks']; $i++) {
            $blocks[] = array_slice($data, $offset, $ecInfo['g1DataPer']);
            $offset += $ecInfo['g1DataPer'];
        }
        for ($i = 0; $i < $ecInfo['g2Blocks']; $i++) {
            $blocks[] = array_slice($data, $offset, $ecInfo['g2DataPer']);
            $offset += $ecInfo['g2DataPer'];
        }
        return $blocks;
    }

    private static function generateEC(array $data, int $ecCount): array {
        // Reed-Solomon error correction using GF(256)
        $gfExp = array_fill(0, 512, 0);
        $gfLog = array_fill(0, 256, 0);

        $x = 1;
        for ($i = 0; $i < 255; $i++) {
            $gfExp[$i] = $x;
            $gfLog[$x] = $i;
            $x <<= 1;
            if ($x >= 256) $x ^= 0x11D;
        }
        for ($i = 255; $i < 512; $i++) {
            $gfExp[$i] = $gfExp[$i - 255];
        }

        // Generator polynomial
        $gen = [1];
        for ($i = 0; $i < $ecCount; $i++) {
            $newGen = array_fill(0, count($gen) + 1, 0);
            for ($j = 0; $j < count($gen); $j++) {
                $newGen[$j] ^= $gen[$j];
                $newGen[$j + 1] ^= self::gfMul($gen[$j], $gfExp[$i], $gfExp, $gfLog);
            }
            $gen = $newGen;
        }

        // Division
        $msg = array_merge($data, array_fill(0, $ecCount, 0));
        for ($i = 0; $i < count($data); $i++) {
            $coef = $msg[$i];
            if ($coef !== 0) {
                for ($j = 0; $j < count($gen); $j++) {
                    $msg[$i + $j] ^= self::gfMul($gen[$j], $coef, $gfExp, $gfLog);
                }
            }
        }

        return array_slice($msg, count($data));
    }

    private static function gfMul(int $a, int $b, array $exp, array $log): int {
        if ($a === 0 || $b === 0) return 0;
        return $exp[$log[$a] + $log[$b]];
    }

    private static function interleave(array $dataBlocks, array $ecBlocks): array {
        $result = [];

        // Interleave data
        $maxLen = max(array_map('count', $dataBlocks));
        for ($i = 0; $i < $maxLen; $i++) {
            foreach ($dataBlocks as $block) {
                if (isset($block[$i])) $result[] = $block[$i];
            }
        }

        // Interleave EC
        $maxEC = max(array_map('count', $ecBlocks));
        for ($i = 0; $i < $maxEC; $i++) {
            foreach ($ecBlocks as $block) {
                if (isset($block[$i])) $result[] = $block[$i];
            }
        }

        return $result;
    }

    private static function placeFinderPatterns(array &$modules, array &$reserved, int $dim): void {
        $positions = [[0, 0], [0, $dim - 7], [$dim - 7, 0]];
        foreach ($positions as [$row, $col]) {
            for ($r = -1; $r <= 7; $r++) {
                for ($c = -1; $c <= 7; $c++) {
                    $y = $row + $r;
                    $x = $col + $c;
                    if ($y < 0 || $y >= $dim || $x < 0 || $x >= $dim) continue;

                    if (($r >= 0 && $r <= 6 && ($c === 0 || $c === 6)) ||
                        ($c >= 0 && $c <= 6 && ($r === 0 || $r === 6)) ||
                        ($r >= 2 && $r <= 4 && $c >= 2 && $c <= 4)) {
                        $modules[$y][$x] = true;
                    } else {
                        $modules[$y][$x] = false;
                    }
                    $reserved[$y][$x] = true;
                }
            }
        }
    }

    private static function placeAlignmentPatterns(array &$modules, array &$reserved, int $version, int $dim): void {
        if ($version < 2) return;
        $positions = self::getAlignmentPositions($version);
        foreach ($positions as $row) {
            foreach ($positions as $col) {
                if ($reserved[$row][$col]) continue;
                for ($r = -2; $r <= 2; $r++) {
                    for ($c = -2; $c <= 2; $c++) {
                        $y = $row + $r;
                        $x = $col + $c;
                        $modules[$y][$x] = (abs($r) === 2 || abs($c) === 2 || ($r === 0 && $c === 0));
                        $reserved[$y][$x] = true;
                    }
                }
            }
        }
    }

    private static function getAlignmentPositions(int $version): array {
        $table = [
            2 => [6, 18], 3 => [6, 22], 4 => [6, 26], 5 => [6, 30], 6 => [6, 34],
            7 => [6, 22, 38], 8 => [6, 24, 42], 9 => [6, 26, 46], 10 => [6, 28, 50],
            11 => [6, 30, 54], 12 => [6, 32, 58], 13 => [6, 34, 62], 14 => [6, 26, 46, 66],
            15 => [6, 26, 48, 70], 16 => [6, 26, 50, 74],
        ];
        return $table[$version] ?? [6, 30];
    }

    private static function placeTimingPatterns(array &$modules, array &$reserved, int $dim): void {
        for ($i = 8; $i < $dim - 8; $i++) {
            if (!$reserved[6][$i]) {
                $modules[6][$i] = ($i % 2 === 0);
                $reserved[6][$i] = true;
            }
            if (!$reserved[$i][6]) {
                $modules[$i][6] = ($i % 2 === 0);
                $reserved[$i][6] = true;
            }
        }
    }

    private static function placeDarkModule(array &$modules, array &$reserved, int $version): void {
        $row = 4 * $version + 9;
        $modules[$row][8] = true;
        $reserved[$row][8] = true;
    }

    private static function reserveFormatInfo(array &$reserved, int $dim): void {
        for ($i = 0; $i <= 8; $i++) {
            if (!$reserved[8][$i]) $reserved[8][$i] = true;
            if (!$reserved[$i][8]) $reserved[$i][8] = true;
        }
        for ($i = $dim - 8; $i < $dim; $i++) {
            $reserved[8][$i] = true;
            $reserved[$i][8] = true;
        }
    }

    private static function reserveVersionInfo(array &$reserved, int $version, int $dim): void {
        if ($version < 7) return;
        for ($i = 0; $i < 6; $i++) {
            for ($j = $dim - 11; $j < $dim - 8; $j++) {
                $reserved[$i][$j] = true;
                $reserved[$j][$i] = true;
            }
        }
    }

    private static function placeDataBits(array &$modules, array &$reserved, array $data, int $dim): void {
        $bitIdx = 0;
        $totalBits = count($data) * 8;
        $bits = '';
        foreach ($data as $byte) {
            $bits .= str_pad(decbin($byte), 8, '0', STR_PAD_LEFT);
        }

        $col = $dim - 1;
        $upward = true;

        while ($col >= 0) {
            if ($col === 6) $col--;

            for ($i = 0; $i < $dim; $i++) {
                $row = $upward ? ($dim - 1 - $i) : $i;

                for ($c = 0; $c <= 1; $c++) {
                    $x = $col - $c;
                    if ($x < 0) continue;
                    if ($reserved[$row][$x]) continue;

                    if ($bitIdx < strlen($bits)) {
                        $modules[$row][$x] = ($bits[$bitIdx] === '1');
                        $bitIdx++;
                    } else {
                        $modules[$row][$x] = false;
                    }
                }
            }

            $col -= 2;
            $upward = !$upward;
        }
    }

    private static function findBestMask(array $modules, array $reserved, int $dim): int {
        $bestMask = 0;
        $bestScore = PHP_INT_MAX;

        for ($mask = 0; $mask < 8; $mask++) {
            $test = $modules;
            self::applyMask($test, $reserved, $mask, $dim);
            $score = self::evaluatePenalty($test, $dim);
            if ($score < $bestScore) {
                $bestScore = $score;
                $bestMask = $mask;
            }
        }

        return $bestMask;
    }

    private static function applyMask(array &$modules, array $reserved, int $mask, int $dim): void {
        for ($y = 0; $y < $dim; $y++) {
            for ($x = 0; $x < $dim; $x++) {
                if ($reserved[$y][$x]) continue;
                $invert = false;
                switch ($mask) {
                    case 0: $invert = (($y + $x) % 2 === 0); break;
                    case 1: $invert = ($y % 2 === 0); break;
                    case 2: $invert = ($x % 3 === 0); break;
                    case 3: $invert = (($y + $x) % 3 === 0); break;
                    case 4: $invert = ((intdiv($y, 2) + intdiv($x, 3)) % 2 === 0); break;
                    case 5: $invert = (($y * $x) % 2 + ($y * $x) % 3 === 0); break;
                    case 6: $invert = ((($y * $x) % 2 + ($y * $x) % 3) % 2 === 0); break;
                    case 7: $invert = ((($y + $x) % 2 + ($y * $x) % 3) % 2 === 0); break;
                }
                if ($invert) $modules[$y][$x] = !$modules[$y][$x];
            }
        }
    }

    private static function evaluatePenalty(array $modules, int $dim): int {
        $score = 0;
        // Rule 1: consecutive same-color modules in row/col
        for ($y = 0; $y < $dim; $y++) {
            $count = 1;
            for ($x = 1; $x < $dim; $x++) {
                if ($modules[$y][$x] === $modules[$y][$x - 1]) {
                    $count++;
                    if ($count === 5) $score += 3;
                    elseif ($count > 5) $score++;
                } else {
                    $count = 1;
                }
            }
        }
        for ($x = 0; $x < $dim; $x++) {
            $count = 1;
            for ($y = 1; $y < $dim; $y++) {
                if ($modules[$y][$x] === $modules[$y - 1][$x]) {
                    $count++;
                    if ($count === 5) $score += 3;
                    elseif ($count > 5) $score++;
                } else {
                    $count = 1;
                }
            }
        }
        return $score;
    }

    private static function placeFormatInfo(array &$modules, int $mask, int $dim): void {
        $formatBits = self::getFormatBits(self::EC_LEVEL, $mask);

        // Around top-left finder
        $positions1 = [[8,0],[8,1],[8,2],[8,3],[8,4],[8,5],[8,7],[8,8],[7,8],[5,8],[4,8],[3,8],[2,8],[1,8],[0,8]];
        for ($i = 0; $i < 15; $i++) {
            $modules[$positions1[$i][0]][$positions1[$i][1]] = (($formatBits >> (14 - $i)) & 1) === 1;
        }

        // Bottom-left and top-right
        for ($i = 0; $i < 7; $i++) {
            $modules[$dim - 1 - $i][8] = (($formatBits >> $i) & 1) === 1;
        }
        for ($i = 7; $i < 15; $i++) {
            $modules[8][$dim - 15 + $i] = (($formatBits >> $i) & 1) === 1;
        }
    }

    private static function getFormatBits(int $ecLevel, int $mask): int {
        $formats = [
            [0x77C4, 0x72F3, 0x7DAA, 0x789D, 0x662F, 0x6318, 0x6C41, 0x6976], // L
            [0x5412, 0x5125, 0x5E7C, 0x5B4B, 0x45F9, 0x40CE, 0x4F97, 0x4AA0], // M
            [0x355F, 0x3068, 0x3F31, 0x3A06, 0x24B4, 0x2183, 0x2EDA, 0x2BED], // Q
            [0x1689, 0x13BE, 0x1CE7, 0x19D0, 0x0762, 0x0255, 0x0D0C, 0x083B], // H
        ];
        return $formats[$ecLevel][$mask];
    }

    private static function placeVersionInfo(array &$modules, int $version, int $dim): void {
        if ($version < 7) return;
        $versionBits = self::getVersionBits($version);
        for ($i = 0; $i < 18; $i++) {
            $bit = (($versionBits >> $i) & 1) === 1;
            $row = intdiv($i, 3);
            $col = $dim - 11 + ($i % 3);
            $modules[$row][$col] = $bit;
            $modules[$col][$row] = $bit;
        }
    }

    private static function getVersionBits(int $version): int {
        $table = [7=>0x07C94,8=>0x085BC,9=>0x09A99,10=>0x0A4D3,11=>0x0BBF6,
                  12=>0x0C762,13=>0x0D847,14=>0x0E60D,15=>0x0F928,16=>0x10B78];
        return $table[$version] ?? 0;
    }
}
