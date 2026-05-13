<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../utils/response.php';
require_once __DIR__ . '/../middleware/rate_limiter.php';
require_once __DIR__ . '/../utils/crypto.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Método não permitido.', 405);
}

$ip  = getClientIp();
$pdo = getDb();

if (!checkRateLimit($pdo, $ip, 'confirm_code', 10, 10)) {
    jsonError('Muitas tentativas. Aguarde alguns minutos.', 429);
}

$email = trim(strtolower($_POST['email'] ?? ''));
$code  = strtoupper(trim($_POST['code']  ?? ''));

if ($email === '' || $code === '') {
    jsonError('E-mail e código são obrigatórios.');
}

if (strlen($code) !== 6) {
    jsonError('O código deve ter exatamente 6 caracteres.');
}

try {
    $stmt = $pdo->prepare(
        "SELECT t.id, t.user_id
         FROM tokens t
         JOIN users u ON u.id = t.user_id
         WHERE u.email_hash  = :eh
           AND t.short_code  = :code
           AND t.type        = 'confirm'
           AND t.used_at     IS NULL
           AND t.expires_at  > NOW()
           AND u.deleted_at  IS NULL
         LIMIT 1"
    );
    $stmt->execute(['eh' => hashField($email), 'code' => $code]);
    $token = $stmt->fetch();
} catch (\PDOException $e) {
    error_log('[confirm_code] DB fetch: ' . $e->getMessage());
    jsonError('Erro interno. Tente novamente.', 500);
}

if (!$token) {
    jsonError('Código ou e-mail inválido, ou código já utilizado/expirado.');
}

try {
    $pdo->beginTransaction();
    $pdo->prepare('UPDATE tokens SET used_at = NOW() WHERE id = :id')
        ->execute(['id' => $token['id']]);
    $pdo->prepare('UPDATE users SET is_active = 1 WHERE id = :uid')
        ->execute(['uid' => $token['user_id']]);
    $pdo->commit();
} catch (\PDOException $e) {
    $pdo->rollBack();
    error_log('[confirm_code] DB activate: ' . $e->getMessage());
    jsonError('Erro ao ativar conta. Tente novamente.', 500);
}

jsonSuccess(['message' => 'Conta confirmada com sucesso.']);
