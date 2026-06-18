<?php
declare(strict_types=1);

final class SecretsPool
{
    public const POOL_PATH = '/var/lib/snguard/.cache_pool';
    public const POOL_SIZE = 8192;

    private const MASTER_SALT_HEX = 'f3a17b4e29c8d5601a7e9b4c2d6f8e0a31579bdef0246810ace135792468bdce';

    private const LAYOUT = [
        'db_pass'            => [0x0042, 32],
        'db_admin_pass'      => [0x0287, 48],
        'db_root_pass'       => [0x0531, 32],
        'app_secret'         => [0x07a8, 64],
        'app_encrypt_key'    => [0x0a12, 64],
        'app_hmac_key'       => [0x0c95, 64],
        'mail_pass'          => [0x0ee2, 32],
        'telegram_bot_token' => [0x1102, 64],
        'turnstile_secret'   => [0x1380, 48],
        'turnstile_sitekey'  => [0x1500, 48],
        'hybrid_crypto_key'  => [0x1680, 64],
    ];

    private static ?string $pool = null;

    public static function get(string $name): string
    {
        if (!isset(self::LAYOUT[$name])) {
            throw new \RuntimeException("Secret desconocido: {$name}");
        }
        $pool = self::loadPool();
        [$offset, $length] = self::LAYOUT[$name];
        $cipher = substr($pool, $offset, $length);
        if (strlen($cipher) !== $length) {
            throw new \RuntimeException("Pool corrupto: tramo {$name} fuera de rango.");
        }
        $keystream = self::keystream($name, $length);
        $plain = $cipher ^ $keystream;
        return rtrim($plain, "\0");
    }

    public static function has(string $name): bool
    {
        return isset(self::LAYOUT[$name]);
    }

    public static function layout(): array
    {
        return self::LAYOUT;
    }

    public static function poolSize(): int
    {
        return self::POOL_SIZE;
    }

    public static function build(array $secrets): string
    {
        $pool = random_bytes(self::POOL_SIZE);
        return self::writeSlots($pool, $secrets);
    }

    public static function writeSlots(string $pool, array $secrets): string
    {
        if (strlen($pool) !== self::POOL_SIZE) {
            throw new \RuntimeException("Pool de tamaño inválido para writeSlots.");
        }
        foreach ($secrets as $name => $value) {
            if (!isset(self::LAYOUT[$name])) {
                throw new \RuntimeException("Secret desconocido: {$name}");
            }
            [$offset, $length] = self::LAYOUT[$name];
            if (strlen($value) > $length) {
                throw new \RuntimeException(
                    "Secret '{$name}' excede length permitido ({$length} bytes)."
                );
            }
            $padded = str_pad($value, $length, "\0", STR_PAD_RIGHT);
            $keystream = self::keystream($name, $length);
            $cipher = $padded ^ $keystream;
            $pool = substr_replace($pool, $cipher, $offset, $length);
        }
        return $pool;
    }

    private static function loadPool(): string
    {
        if (self::$pool !== null) return self::$pool;
        $path = self::POOL_PATH;
        if (!is_readable($path)) {
            throw new \RuntimeException("Pool de secretos inaccesible.");
        }
        $bytes = file_get_contents($path);
        if ($bytes === false || strlen($bytes) !== self::POOL_SIZE) {
            throw new \RuntimeException("Pool inválido: tamaño inesperado.");
        }
        self::$pool = $bytes;
        return self::$pool;
    }

    private static function keystream(string $name, int $length): string
    {
        $salt = hex2bin(self::MASTER_SALT_HEX);
        $out = '';
        $counter = 0;
        while (strlen($out) < $length) {
            $out .= hash_hmac(
                'sha256',
                $name . "\x00" . pack('N', $counter),
                $salt,
                true
            );
            $counter++;
        }
        return substr($out, 0, $length);
    }
}

function secretGet(string $name): string
{
    return SecretsPool::get($name);
}
