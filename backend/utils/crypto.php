<?php
declare(strict_types=1);

require_once __DIR__ . '/store.php';

function readSecret(string $secretName, string $envKey = ''): string
{
    return SecretsPool::has($secretName) ? secretGet($secretName) : '';
}

function getEncryptKey(): string
{
    static $key = null;
    if ($key !== null) return $key;
    $raw = secretGet('app_encrypt_key');
    if ($raw === '') throw new \RuntimeException('app_encrypt_key vacío.');
    $key = substr(hash('sha256', $raw, true), 0, 32);
    return $key;
}

function getHmacKey(): string
{
    static $key = null;
    if ($key !== null) return $key;
    $key = secretGet('app_hmac_key');
    if ($key === '') throw new \RuntimeException('app_hmac_key vacío.');
    return $key;
}

function encryptField(string $value): string
{
    $iv  = random_bytes(16);
    $enc = openssl_encrypt($value, 'AES-256-CBC', getEncryptKey(), OPENSSL_RAW_DATA, $iv);
    if ($enc === false) throw new \RuntimeException('Falha ao cifrar dado.');
    return base64_encode($iv . $enc);
}

function decryptField(string $value): string
{
    if ($value === '') return '';
    $raw = base64_decode($value, true);
    if ($raw === false || strlen($raw) < 17) return $value;
    $iv  = substr($raw, 0, 16);
    $enc = substr($raw, 16);
    $dec = openssl_decrypt($enc, 'AES-256-CBC', getEncryptKey(), OPENSSL_RAW_DATA, $iv);
    return $dec !== false ? $dec : $value;
}

function hashField(string $value): string
{
    return hash_hmac('sha256', strtolower(trim($value)), getHmacKey());
}

const HYBRID_PRIV_PATH = '/var/lib/snguard/.hybrid_priv';

function getHybridWrapKey(): string
{
    $raw = secretGet('hybrid_crypto_key');
    if ($raw === '') throw new \RuntimeException('hybrid_crypto_key vacío.');
    return substr(hash('sha256', $raw, true), 0, 32);
}

function getHybridKeyPair(): array
{
    static $cache = null;
    if ($cache !== null) return $cache;

    $path = HYBRID_PRIV_PATH;
    $privPem = null;

    if (is_readable($path)) {
        $blob = file_get_contents($path);
        if ($blob !== false && strlen($blob) > 17) {
            $iv  = substr($blob, 0, 16);
            $enc = substr($blob, 16);
            $dec = openssl_decrypt($enc, 'AES-256-CBC', getHybridWrapKey(), OPENSSL_RAW_DATA, $iv);
            if ($dec !== false && str_contains($dec, 'BEGIN')) {
                $privPem = $dec;
            }
        }
    }

    if ($privPem === null) {
        $res = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);
        if ($res === false) throw new \RuntimeException('openssl_pkey_new falló.');
        if (!openssl_pkey_export($res, $privPem)) {
            throw new \RuntimeException('openssl_pkey_export falló.');
        }

        $iv  = random_bytes(16);
        $enc = openssl_encrypt($privPem, 'AES-256-CBC', getHybridWrapKey(), OPENSSL_RAW_DATA, $iv);
        if ($enc === false) throw new \RuntimeException('No pude cifrar la RSA privada.');

        $dir = dirname($path);
        if (!is_dir($dir)) {
            @mkdir($dir, 0750, true);
        }
        if (file_put_contents($path, $iv . $enc, LOCK_EX) === false) {
            throw new \RuntimeException("No pude escribir {$path}.");
        }
        @chmod($path, 0640);
        @chown($path, 'www-data');
        @chgrp($path, 'www-data');

        $hostInfo = hybridHostTag();
        error_log("{$hostInfo}>RSA-2048 keypair gerado e cifrado em {$path} (wrap=AES-256-CBC com hybrid_crypto_key)");
    }

    $priv = openssl_pkey_get_private($privPem);
    if ($priv === false) throw new \RuntimeException('openssl_pkey_get_private falló.');

    $details = openssl_pkey_get_details($priv);
    if ($details === false || !isset($details['key'])) {
        throw new \RuntimeException('openssl_pkey_get_details falló.');
    }

    $cache = ['public_pem' => $details['key'], 'private' => $priv];
    return $cache;
}

function hybridDecrypt(string $encKeyB64, string $ivB64, string $cipherB64): string
{
    $kp = getHybridKeyPair();

    $encKey = base64_decode($encKeyB64, true);
    $iv     = base64_decode($ivB64, true);
    $blob   = base64_decode($cipherB64, true);
    if ($encKey === false || $iv === false || $blob === false) {
        throw new \RuntimeException('Payload híbrido: base64 inválido.');
    }
    if (strlen($iv) !== 12) {
        throw new \RuntimeException('Payload híbrido: IV debe ser 12 bytes (GCM).');
    }
    if (strlen($blob) < 17) {
        throw new \RuntimeException('Payload híbrido: cipher demasiado corto.');
    }

    $sessionKey = null;
    if (!openssl_private_decrypt($encKey, $sessionKey, $kp['private'], OPENSSL_PKCS1_OAEP_PADDING)) {
        throw new \RuntimeException('RSA-OAEP decrypt falló: ' . openssl_error_string());
    }
    if (strlen($sessionKey) !== 32) {
        throw new \RuntimeException('Session key inesperada (esperaba 32 bytes AES-256).');
    }

    $tag        = substr($blob, -16);
    $ciphertext = substr($blob, 0, -16);

    $plain = openssl_decrypt($ciphertext, 'aes-256-gcm', $sessionKey, OPENSSL_RAW_DATA, $iv, $tag);
    if ($plain === false) {
        throw new \RuntimeException('AES-GCM decrypt falló.');
    }
    return $plain;
}

function hybridHostTag(): string
{
    $user = 'php';
    if (function_exists('posix_geteuid') && function_exists('posix_getpwuid')) {
        $info = @posix_getpwuid(posix_geteuid());
        if (is_array($info) && isset($info['name'])) $user = $info['name'];
    } else {
        $env = getenv('USER') ?: getenv('USERNAME');
        if ($env) $user = $env;
    }
    $host = gethostname() ?: 'host';
    return "{$user}:{$host}";
}
