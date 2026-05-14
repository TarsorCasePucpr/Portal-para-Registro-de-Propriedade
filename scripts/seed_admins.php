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

$admins = [
    [
        'name'    => 'Gerard Gonzalez',
        'email'   => 'gerard.gonzalez@pucpr.edu.br',
        'cpf'     => '000.000.000-00',
        'chat_id' => '8199427665',
    ],
    [
        'name'    => 'Henrique Woehl',
        'email'   => 'henrique.woehl@pucpr.edu.br',
        'cpf'     => '000.000.000-02',
        'chat_id' => '8532317226',
    ],
    [
        'name'    => 'Kauã Rubbo',
        'email'   => 'kaua.rubbo@pucpr.edu.br',
        'cpf'     => '000.000.000-03',
        'chat_id' => '',
    ],
];

$answers = [
    'pucpr',
    '4',
    '2025',
    'experiência criativa',
];

$answerHashes = array_map(
    fn(string $a) => password_hash($a, PASSWORD_BCRYPT, ['cost' => 13]),
    $answers
);

try {
    $pdo = getAdminDb();
    $pdo->beginTransaction();

    foreach ($admins as $a) {
        $emailHash = hashField($a['email']);
        $cpfHash   = hashField($a['cpf']);
        $emailEnc  = encryptField($a['email']);
        $cpfEnc    = encryptField($a['cpf']);
        $chatEnc   = $a['chat_id'] !== '' ? encryptField($a['chat_id']) : null;
        $pwdHash   = password_hash(bin2hex(random_bytes(32)), PASSWORD_BCRYPT, ['cost' => 13]);

        $stmt = $pdo->prepare('SELECT id FROM users WHERE email_hash = :eh AND deleted_at IS NULL');
        $stmt->execute(['eh' => $emailHash]);
        $userId = $stmt->fetchColumn();

        if ($userId === false) {
            $pdo->prepare(
                'INSERT INTO users (name, email, email_hash, cpf, cpf_hash, password_hash, is_active)
                 VALUES (:n, :e, :eh, :c, :ch, :p, 1)'
            )->execute([
                'n'  => $a['name'],
                'e'  => $emailEnc,
                'eh' => $emailHash,
                'c'  => $cpfEnc,
                'ch' => $cpfHash,
                'p'  => $pwdHash,
            ]);
            $userId = (int) $pdo->lastInsertId();
            echo "users: criado #{$userId} ({$a['name']})\n";
        } else {
            $userId = (int) $userId;
            echo "users: já existe #{$userId} ({$a['name']})\n";
        }

        $hasProfile = $pdo->prepare('SELECT id FROM admin_profiles WHERE user_id = ?');
        $hasProfile->execute([$userId]);
        $profileId = $hasProfile->fetchColumn();

        if ($profileId === false) {
            $pdo->prepare(
                'INSERT INTO admin_profiles (user_id, email, telegram_chat_id)
                 VALUES (:uid, :em, :cid)'
            )->execute(['uid' => $userId, 'em' => $a['email'], 'cid' => $chatEnc]);
            echo "  admin_profiles: criado (chat_id " . ($a['chat_id'] !== '' ? 'definido' : 'vazio — fallback perguntas') . ")\n";
        } else {
            $pdo->prepare(
                'UPDATE admin_profiles SET email = :em, telegram_chat_id = :cid WHERE user_id = :uid'
            )->execute(['em' => $a['email'], 'cid' => $chatEnc, 'uid' => $userId]);
            echo "  admin_profiles: atualizado\n";
        }

        $pdo->prepare(
            'INSERT INTO admin_security_answers
                (user_id, answer1_hash, answer2_hash, answer3_hash, answer4_hash)
             VALUES (:uid, :h1, :h2, :h3, :h4)
             ON DUPLICATE KEY UPDATE
                answer1_hash = VALUES(answer1_hash),
                answer2_hash = VALUES(answer2_hash),
                answer3_hash = VALUES(answer3_hash),
                answer4_hash = VALUES(answer4_hash),
                updated_at   = CURRENT_TIMESTAMP'
        )->execute([
            'uid' => $userId,
            'h1'  => $answerHashes[0],
            'h2'  => $answerHashes[1],
            'h3'  => $answerHashes[2],
            'h4'  => $answerHashes[3],
        ]);
        echo "  admin_security_answers: ok\n";
    }

    $pdo->commit();
    echo "\nSEED OK — 3 admins criados/atualizados.\n";
    echo "Respostas de segurança (mesmas para todos):\n";
    echo "  q1: PUCPR\n";
    echo "  q2: 4\n";
    echo "  q3: 2025\n";
    echo "  q4: Experiência Criativa\n";
} catch (\PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    fwrite(STDERR, "ERRO de DB: " . $e->getMessage() . "\n");
    exit(1);
} catch (\RuntimeException $e) {
    fwrite(STDERR, "ERRO: " . $e->getMessage() . "\n");
    exit(1);
}
