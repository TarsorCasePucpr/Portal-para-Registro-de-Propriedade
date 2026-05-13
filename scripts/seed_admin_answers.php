<?php
declare(strict_types=1);

/**
 * Seed das respostas de segurança do administrador.
 *
 * Uso:
 *   php scripts/seed_admin_answers.php
 *
 * Lê do .env (ou ambiente):
 *   ADMIN_EMAIL       — email do admin alvo (default: gerard.gonzalez@pucpr.edu.br)
 *   ADMIN_ANSWER_1..4 — respostas em texto plano (NUNCA commitar)
 *
 * Normalização aplicada (idêntica a admin-verify-questions.php):
 *   q1, q4 — mb_strtolower(trim())
 *   q2     — só dígitos; aceita "quatro" → "4"
 *   q3     — só dígitos
 *
 * Idempotente: usa ON DUPLICATE KEY UPDATE.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Este script só pode ser executado via CLI.\n");
}

require_once __DIR__ . '/../backend/config/db.php';

$base = dirname(__DIR__);
loadEnv($base . '/.env');

$adminEmail = getenv('ADMIN_EMAIL') ?: 'gerard.gonzalez@pucpr.edu.br';

$raw = [
    1 => getenv('ADMIN_ANSWER_1'),
    2 => getenv('ADMIN_ANSWER_2'),
    3 => getenv('ADMIN_ANSWER_3'),
    4 => getenv('ADMIN_ANSWER_4'),
];

foreach ($raw as $i => $v) {
    if ($v === false || $v === '') {
        fwrite(STDERR, "ERRO: ADMIN_ANSWER_{$i} não definido no .env\n");
        exit(1);
    }
}

// Mesmas regras de admin-verify-questions.php — manter sincronizado.
$q1 = mb_strtolower(trim((string) $raw[1]));
$q2 = mb_strtolower(trim((string) $raw[2]));
$q2 = preg_replace('/\D+/', '', $q2) ?: (($q2 === 'quatro') ? '4' : $q2);
$q3 = preg_replace('/\D+/', '', trim((string) $raw[3])) ?? '';
$q4 = mb_strtolower(trim((string) $raw[4]));

if ($q1 === '' || $q2 === '' || $q3 === '' || $q4 === '') {
    fwrite(STDERR, "ERRO: alguma resposta normalizada ficou vazia.\n");
    exit(1);
}

$normalized = [$q1, $q2, $q3, $q4];
$hashes     = array_map(
    fn(string $r) => password_hash($r, PASSWORD_BCRYPT, ['cost' => 13]),
    $normalized
);

try {
    $pdo  = getDb();
    $stmt = $pdo->prepare(
        'INSERT INTO admin_security_answers
            (user_id, answer1_hash, answer2_hash, answer3_hash, answer4_hash)
         SELECT u.id, :h1, :h2, :h3, :h4
         FROM   users u
         WHERE  u.email = :email
         ON DUPLICATE KEY UPDATE
             answer1_hash = VALUES(answer1_hash),
             answer2_hash = VALUES(answer2_hash),
             answer3_hash = VALUES(answer3_hash),
             answer4_hash = VALUES(answer4_hash),
             updated_at   = CURRENT_TIMESTAMP'
    );
    $stmt->execute([
        'h1'    => $hashes[0],
        'h2'    => $hashes[1],
        'h3'    => $hashes[2],
        'h4'    => $hashes[3],
        'email' => $adminEmail,
    ]);

    if ($stmt->rowCount() === 0) {
        fwrite(STDERR, "ERRO: usuário admin com email {$adminEmail} não existe na tabela users.\n");
        exit(1);
    }

    echo "OK — respostas seedadas/atualizadas para {$adminEmail}.\n";
    echo "Lembrete: zere ADMIN_ANSWER_1..4 do .env após validar o login.\n";
} catch (\PDOException $e) {
    fwrite(STDERR, "ERRO de DB: " . $e->getMessage() . "\n");
    exit(1);
}
