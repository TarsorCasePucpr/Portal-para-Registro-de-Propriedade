<?php
declare(strict_types=1);

function readSecret(string $secretName, string $envKey = ''): string
{
    $file = '/run/secrets/' . $secretName;
    if (is_readable($file)) return trim((string) file_get_contents($file));
    $key = $envKey ?: strtoupper(str_replace('-', '_', $secretName));
    return (string) (getenv($key) ?: ($_ENV[$key] ?? ''));
}

function getEncryptKey(): string
{
    static $key = null;
    if ($key !== null) return $key;
    $raw = readSecret('app_encrypt_key', 'APP_ENCRYPT_KEY');
    if ($raw === '') throw new \RuntimeException('APP_ENCRYPT_KEY não configurado.');
    $key = substr(hash('sha256', $raw, true), 0, 32);
    return $key;
}

function getHmacKey(): string
{
    static $key = null;
    if ($key !== null) return $key;
    $key = readSecret('app_hmac_key', 'APP_HMAC_KEY');
    if ($key === '') throw new \RuntimeException('APP_HMAC_KEY não configurado.');
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
