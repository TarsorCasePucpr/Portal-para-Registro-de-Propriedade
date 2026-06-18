<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only.\n");
}

require_once __DIR__ . '/../backend/config/db.php';
require_once __DIR__ . '/../backend/utils/crypto.php';

$limit = isset($argv[1]) ? max(1, min(50, (int) $argv[1])) : 5;

try {
    $pdo = getDb();
} catch (\Throwable $e) {
    fwrite(STDERR, "DB indisponível: " . $e->getMessage() . "\n");
    exit(1);
}

$tag = hybridHostTag();

try {
    $stmt = $pdo->prepare(
        'SELECT id, name, email, cpf, is_active, created_at
           FROM users
          WHERE deleted_at IS NULL
          ORDER BY id DESC
          LIMIT ' . $limit
    );
    $stmt->execute();
    $rows = $stmt->fetchAll();
} catch (\Throwable $e) {
    fwrite(STDERR, "Falha ao consultar users: " . $e->getMessage() . "\n");
    exit(1);
}

if (!$rows) {
    echo "{$tag}>nenhum cadastro encontrado em users.\n";
    exit(0);
}

foreach ($rows as $r) {
    $nome  = decryptField((string) $r['name']);
    $email = decryptField((string) $r['email']);
    $cpf   = decryptField((string) $r['cpf']);
    echo "{$tag}>cadastro id={$r['id']} ativo={$r['is_active']} criado={$r['created_at']} "
       . "nome={$nome} email={$email} cpf={$cpf}\n";
}
