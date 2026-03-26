<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../utils/response.php';

$tokenRaw = trim($_GET['token'] ?? '');

if ($tokenRaw === '') {
    redirect('../../frontend/pages/login.html?erro=' .
        urlencode('Link de confirmação inválido.'));
}

$tokenHash = hash('sha256', $tokenRaw);

try {
    $pdo  = getDb();
    $stmt = $pdo->prepare(
        "SELECT id, user_id FROM tokens
         WHERE token_hash = :hash
           AND type       = 'confirm'
           AND used_at    IS NULL
           AND expires_at > NOW()
         LIMIT 1"
    );
    $stmt->execute(['hash' => $tokenHash]);
    $token = $stmt->fetch();
} catch (PDOException $e) {
    error_log('[confirm] DB fetch token: ' . $e->getMessage());
    redirect('../../frontend/pages/login.html?erro=' .
        urlencode('Erro interno. Tente novamente.'));
}

if (!$token) {
    redirect('../../frontend/pages/confirmacao-cadastro.html?status=error');
}

try {
    $pdo->beginTransaction();

    $pdo->prepare(
        'UPDATE tokens SET used_at = NOW() WHERE id = :id'
    )->execute(['id' => $token['id']]);

    $pdo->prepare(
        'UPDATE users SET is_active = 1 WHERE id = :uid'
    )->execute(['uid' => $token['user_id']]);

    $pdo->commit();
} catch (PDOException $e) {
    $pdo->rollBack();
    error_log('[confirm] DB activate user: ' . $e->getMessage());
    redirect('../../frontend/pages/login.html?erro=' .
        urlencode('Erro ao ativar conta. Tente novamente.'));
}

redirect('../../frontend/pages/confirmacao-cadastro.html?status=success');
