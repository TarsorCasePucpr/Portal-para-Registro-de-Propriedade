<?php
declare(strict_types=1);

function base32Decode(string $base32): string {
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $base32    = strtoupper(rtrim($base32, '='));
    $binary    = '';
    for ($i = 0, $len = strlen($base32); $i < $len; $i++) {
        $pos = strpos($alphabet, $base32[$i]);
        if ($pos === false) continue;
        $binary .= str_pad(decbin($pos), 5, '0', STR_PAD_LEFT);
    }
    $bytes = '';
    for ($i = 0; $i + 8 <= strlen($binary); $i += 8) {
        $bytes .= chr(bindec(substr($binary, $i, 8)));
    }
    return $bytes;
}

function verifyTotp(string $secret, string $code, int $window = 2): bool {
    $key      = base32Decode($secret);
    $timeStep = (int) floor(time() / 30);
    for ($i = -$window; $i <= $window; $i++) {
        $step   = $timeStep + $i;
        $data   = pack('J', $step);
        $hash   = hash_hmac('sha1', $data, $key, true);
        $offset = ord($hash[19]) & 0x0F;
        $otp    = (
            ((ord($hash[$offset])     & 0x7F) << 24) |
            ((ord($hash[$offset + 1]) & 0xFF) << 16) |
            ((ord($hash[$offset + 2]) & 0xFF) << 8)  |
            ((ord($hash[$offset + 3]) & 0xFF))
        ) % 1_000_000;
        if (hash_equals(
            str_pad((string) $otp, 6, '0', STR_PAD_LEFT),
            str_pad($code,         6, '0', STR_PAD_LEFT)
        )) {
            return true;
        }
    }
    return false;
}

function generateTotpSecret(): string {
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $bytes    = random_bytes(20);
    $secret   = '';
    for ($i = 0; $i < 20; $i++) {
        $secret .= $alphabet[ord($bytes[$i]) & 0x1F];
    }
    return $secret;
}
