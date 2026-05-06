<?php
declare(strict_types=1);

ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Strict');
session_start();

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../middleware/csrf.php';
require_once __DIR__ . '/../middleware/rate_limiter.php';
require_once __DIR__ . '/../utils/response.php';
require_once __DIR__ . '/../utils/telegram.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('../../frontend/pages/admin-login.html');
}

if (!validateCsrfToken($_POST['csrf'] ?? '')) {
    redirect('../../frontend/pages/admin-login.html?erro=' . urlencode('Token de segurança inválido.'));
}

$pdo = getDb();
$ip  = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

if (isRateLimited($pdo, $ip, 'admin_otp_request', 5, 15)) {
    redirect('../../frontend/pages/admin-login.html?erro=' . urlencode('Muitas tentativas. Aguarde 15 minutos.'));
}

$email = trim(strtolower($_POST['email'] ?? ''));
if ($email === '') {
    redirect('../../frontend/pages/admin-login.html?erro=' . urlencode('Informe o e-mail.'));
}

try {
    $stmt = $pdo->prepare(
        'SELECT u.id AS user_id, ap.telegram_chat_id
         FROM   users u
         JOIN   admin_profiles ap ON ap.user_id = u.id
         WHERE  u.email = :email AND u.deleted_at IS NULL AND u.is_active = 1'
    );
    $stmt->execute(['email' => $email]);
    $admin = $stmt->fetch();
} catch (\PDOException $e) {
    error_log('[admin-request-otp] DB: ' . $e->getMessage());
    redirect('../../frontend/pages/admin-login.html?erro=' . urlencode('Erro interno.'));
}

if (!$admin || !$admin['telegram_chat_id']) {
    recordFailedAttempt($pdo, $ip, 'admin_otp_request');
    redirect('../../frontend/pages/admin-otp.html?email=' . urlencode($email));
}

$otp      = (string) random_int(100000, 999999);
$otpHash  = hash('sha256', $otp);
$expires  = date('Y-m-d H:i:s', time() + 600); 

try {
    
    $pdo->prepare(
        "UPDATE tokens SET used_at = NOW()
         WHERE  user_id = ? AND type = 'admin_otp' AND used_at IS NULL"
    )->execute([$admin['user_id']]);

    $pdo->prepare(
        'INSERT INTO tokens (user_id, token_hash, type, expires_at)
         VALUES (:uid, :hash, :type, :exp)'
    )->execute([
        'uid'  => $admin['user_id'],
        'hash' => $otpHash,
        'type' => 'admin_otp',
        'exp'  => $expires,
    ]);
} catch (\PDOException $e) {
    error_log('[admin-request-otp] token: ' . $e->getMessage());
    redirect('../../frontend/pages/admin-login.html?erro=' . urlencode('Erro ao gerar código.'));
}

$sent = sendTelegramMessage(
    $admin['telegram_chat_id'],
    "🔐 <b>SNGuard — Código de acesso admin</b>\n\nSeu código: <code>{$otp}</code>\n\nVálido por 10 minutos. Não compartilhe."
);

if (!$sent) {
    error_log("[admin-request-otp] Telegram falhou para user_id={$admin['user_id']}");
}

$_SESSION['admin_pending_id'] = $admin['user_id'];
$_SESSION['admin_pending_at'] = time();

redirect('../../frontend/pages/admin-otp.html?email=' . urlencode($email));
