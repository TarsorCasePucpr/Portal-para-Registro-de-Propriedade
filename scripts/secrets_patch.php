<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only.\n");
}

require_once __DIR__ . '/../backend/utils/store.php';

$path = SecretsPool::POOL_PATH;
if (!is_readable($path)) {
    fwrite(STDERR, "Pool no existe en {$path} — corré primero secrets_build.php\n");
    exit(1);
}

$json = stream_get_contents(STDIN);
$input = json_decode($json ?: '', true);
if (!is_array($input) || $input === []) {
    fwrite(STDERR, "Input inválido: esperaba JSON objeto no-vacío en stdin.\n");
    exit(1);
}

$pool = file_get_contents($path);
if ($pool === false || strlen($pool) !== SecretsPool::POOL_SIZE) {
    fwrite(STDERR, "Pool corrupto.\n");
    exit(1);
}

try {
    $pool = SecretsPool::writeSlots($pool, $input);
} catch (\Throwable $e) {
    fwrite(STDERR, "ERROR patch: " . $e->getMessage() . "\n");
    exit(1);
}

if (file_put_contents($path, $pool, LOCK_EX) === false) {
    fwrite(STDERR, "No pude reescribir {$path}\n");
    exit(1);
}

echo "Pool patcheado: " . count($input) . " slot(s) actualizado(s).\n";
