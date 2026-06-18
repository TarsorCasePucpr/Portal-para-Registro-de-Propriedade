<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only.\n");
}

require_once __DIR__ . '/../backend/utils/store.php';

function hostTag(): string {
    $user = function_exists('posix_geteuid')
        ? (posix_getpwuid(posix_geteuid())['name'] ?? 'php')
        : (getenv('USER') ?: 'php');
    $host = gethostname() ?: 'host';
    return "{$user}:{$host}";
}

$json = stream_get_contents(STDIN);
$input = json_decode($json ?: '', true);
if (!is_array($input)) {
    fwrite(STDERR, "Input inválido: esperaba JSON objeto en stdin.\n");
    exit(1);
}

$tag = hostTag();
$layout = SecretsPool::layout();
foreach (array_keys($input) as $name) {
    if (!isset($layout[$name])) continue;
    [$off, $len] = $layout[$name];
    echo sprintf(
        "%s>S.3.2.b — secret '%s' armazenado em pool XOR-cifrado (offset=0x%04x, length=%d bytes, keystream=HKDF-HMAC-SHA256)\n",
        $tag, $name, $off, $len
    );
}

try {
    $pool = SecretsPool::build($input);
} catch (\Throwable $e) {
    fwrite(STDERR, "ERROR build: " . $e->getMessage() . "\n");
    exit(1);
}

$target = SecretsPool::POOL_PATH;
$dir = dirname($target);
if (!is_dir($dir)) {
    if (!mkdir($dir, 0700, true) && !is_dir($dir)) {
        fwrite(STDERR, "No pude crear {$dir}\n");
        exit(1);
    }
}

if (file_put_contents($target, $pool, LOCK_EX) === false) {
    fwrite(STDERR, "No pude escribir {$target}\n");
    exit(1);
}

chmod($target, 0640);
@chown($target, 'www-data');
@chgrp($target, 'www-data');
chmod($dir, 0750);
@chown($dir, 'www-data');
@chgrp($dir, 'www-data');

echo "{$tag}>Pool escrito: {$target} (" . strlen($pool) . " bytes, "
    . count($input) . " secretos)\n";
