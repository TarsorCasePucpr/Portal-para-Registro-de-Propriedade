<?php
declare(strict_types=1);

ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Lax');
session_start();

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../utils/response.php';
require_once __DIR__ . '/../utils/admin_otp_flow.php';

$tokenRaw = trim($_GET['token'] ?? '');

if ($tokenRaw === '') {
    redirect('../../frontend/pages/admin-login.html?erro=' . urlencode('Link de confirmação inválido.'));
}

$tokenHash = hash('sha256', $tokenRaw);

try {
    $pdo  = getAdminDb();
    $stmt = $pdo->prepare(
        "SELECT t.id, t.user_id, ap.email
         FROM   tokens t
         JOIN   admin_profiles ap ON ap.user_id = t.user_id
         JOIN   users u           ON u.id       = t.user_id
         WHERE  t.token_hash = :hash
           AND  t.type       = 'admin_email'
           AND  t.used_at    IS NULL
           AND  t.expires_at > NOW()
           AND  u.deleted_at IS NULL
           AND  u.is_active  = 1
         LIMIT 1"
    );
    $stmt->execute(['hash' => $tokenHash]);
    $row = $stmt->fetch();
} catch (\PDOException $e) {
    error_log('[admin-confirm-email] DB: ' . $e->getMessage());
    redirect('../../frontend/pages/admin-login.html?erro=' . urlencode('Erro interno.'));
}

if (!$row) {
    redirect('../../frontend/pages/admin-login.html?erro=' . urlencode('Link expirado ou inválido. Solicite o acesso novamente.'));
}

try {
    $pdo->prepare('UPDATE tokens SET used_at = NOW() WHERE id = ?')->execute([$row['id']]);
} catch (\PDOException $e) {
    error_log('[admin-confirm-email] mark used: ' . $e->getMessage());
}

$_SESSION['admin_pending_id']     = (int) $row['user_id'];
$_SESSION['admin_pending_at']     = time();
$_SESSION['admin_email_verified'] = true;
unset($_SESSION['admin_fallback_questions']);

triggerAdminTelegramStep($pdo, (int) $row['user_id'], (string) $row['email']);
