<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only.\n");
}

require_once __DIR__ . '/../backend/config/db.php';
require_once __DIR__ . '/../backend/utils/crypto.php';

$base = dirname(__DIR__);
loadEnv($base . '/.env');

$adminEmail = getenv('ADMIN_EMAIL') ?: '';
if ($adminEmail === '') {
    fwrite(STDERR, "ERRO: ADMIN_EMAIL não definido no .env\n");
    exit(1);
}

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

$q1 = mb_strtolower(trim((string) $raw[1]));
$q2 = mb_strtolower(trim((string) $raw[2]));
$q2 = preg_replace('/\D+/', '', $q2) ?: (($q2 === 'quatro') ? '4' : $q2);
$q3 = preg_replace('/\D+/', '', trim((string) $raw[3])) ?? '';
$q4 = mb_strtolower(trim((string) $raw[4]));

if ($q1 === '' || $q2 === '' || $q3 === '' || $q4 === '') {
    fwrite(STDERR, "ERRO: alguma resposta normalizada ficou vazia.\n");
    exit(1);
}

$hashes = array_map(
    fn(string $r) => password_hash($r, PASSWORD_BCRYPT, ['cost' => 13]),
    [$q1, $q2, $q3, $q4]
);

try {
    $pdo  = getDb();
    $stmt = $pdo->prepare(
        'INSERT INTO admin_security_answers
            (user_id, answer1_hash, answer2_hash, answer3_hash, answer4_hash)
         SELECT u.id, :h1, :h2, :h3, :h4
         FROM   users u
         JOIN   admin_profiles ap ON ap.user_id = u.id
         WHERE  u.email_hash = :eh AND u.deleted_at IS NULL AND u.is_active = 1
         ON DUPLICATE KEY UPDATE
             answer1_hash = VALUES(answer1_hash),
             answer2_hash = VALUES(answer2_hash),
             answer3_hash = VALUES(answer3_hash),
             answer4_hash = VALUES(answer4_hash),
             updated_at   = CURRENT_TIMESTAMP'
    );
    $stmt->execute([
        'h1' => $hashes[0],
        'h2' => $hashes[1],
        'h3' => $hashes[2],
        'h4' => $hashes[3],
        'eh' => hashField($adminEmail),
    ]);

    if ($stmt->rowCount() === 0) {
        fwrite(STDERR, "ERRO: admin ativo com esse email não existe (verifique users.email_hash + admin_profiles).\n");
        exit(1);
    }

    echo "OK\n";
} catch (\PDOException $e) {
    fwrite(STDERR, "ERRO de DB: " . $e->getMessage() . "\n");
    exit(1);
}
