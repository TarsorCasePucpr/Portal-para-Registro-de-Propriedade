<?php
declare(strict_types=1);

require_once __DIR__ . '/../utils/crypto.php';
require_once __DIR__ . '/../utils/response.php';

header('Cache-Control: no-store');

try {
    $kp = getHybridKeyPair();
} catch (\Throwable $e) {
    error_log('[hybrid_pubkey] ' . $e->getMessage());
    jsonError('No se pudo obtener la chave pública.', 500);
}

jsonSuccess([
    'algorithm' => 'RSA-OAEP',
    'hash'      => 'SHA-1',
    'modulus_bits' => 2048,
    'public_key' => $kp['public_pem'],
]);
