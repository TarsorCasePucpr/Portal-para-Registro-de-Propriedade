<?php
declare(strict_types=1);

ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.cookie_secure',   '1');
ini_set('session.gc_maxlifetime',  '14400');
session_start();

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../middleware/csrf.php';
require_once __DIR__ . '/../middleware/rate_limiter.php';
require_once __DIR__ . '/../utils/response.php';
require_once __DIR__ . '/../utils/logger.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('../../frontend/pages/admin-otp.html');
}

if (!validateCsrfToken($_POST['csrf'] ?? '')) {
    redirect('../../frontend/pages/admin-otp.html?erro=' . urlencode('Token de segurança inválido.'));
}

$pendingId = $_SESSION['admin_pending_id'] ?? null;
$pendingAt = $_SESSION['admin_pending_at'] ?? 0;

if (!$pendingId || (time() - (int) $pendingAt) > 900) {
    session_unset();
    redirect('../../frontend/pages/admin-login.html?erro=' . urlencode('Sessão expirada. Faça login novamente.'));
}

$pdo = getDb();
$ip  = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

if (isRateLimited($pdo, $ip, 'admin_otp_verify', 5, 10)) {
    redirect('../../frontend/pages/admin-otp.html?erro=' . urlencode('Muitas tentativas. Aguarde.'));
}

$otp = trim($_POST['otp'] ?? '');
if (!preg_match('/^\d{6}$/', $otp)) {
    recordFailedAttempt($pdo, $ip, 'admin_otp_verify');
    redirect('../../frontend/pages/admin-otp.html?erro=' . urlencode('Código inválido. Digite os 6 dígitos.'));
}

try {
    $stmt = $pdo->prepare(
        "SELECT id, token_hash, expires_at
         FROM   tokens
         WHERE  user_id = :uid AND type = 'admin_otp' AND used_at IS NULL
         ORDER  BY created_at DESC
         LIMIT  1"
    );
    $stmt->execute(['uid' => $pendingId]);
    $token = $stmt->fetch();
} catch (\PDOException $e) {
    error_log('[admin-verify-otp] DB: ' . $e->getMessage());
    redirect('../../frontend/pages/admin-otp.html?erro=' . urlencode('Erro interno.'));
}

if (!$token) {
    recordFailedAttempt($pdo, $ip, 'admin_otp_verify');
    redirect('../../frontend/pages/admin-otp.html?erro=' . urlencode('Código não encontrado. Solicite um novo.'));
}

if (new \DateTime() > new \DateTime($token['expires_at'])) {
    redirect('../../frontend/pages/admin-login.html?erro=' . urlencode('Código expirado. Solicite um novo.'));
}

if (!hash_equals($token['token_hash'], hash('sha256', $otp))) {
    recordFailedAttempt($pdo, $ip, 'admin_otp_verify');
    redirect('../../frontend/pages/admin-otp.html?erro=' . urlencode('Código incorreto.'));
}

$pdo->prepare('UPDATE tokens SET used_at = NOW() WHERE id = ?')->execute([$token['id']]);

unset($_SESSION['admin_pending_id'], $_SESSION['admin_pending_at']);
session_regenerate_id(true);

$_SESSION['user_id']       = (int) $pendingId;
$_SESSION['is_admin']      = true;
$_SESSION['user_agent']    = $_SERVER['HTTP_USER_AGENT'] ?? '';
$_SESSION['ip']            = $ip;
$_SESSION['last_activity'] = time();

logAction($pdo, (int) $pendingId, 'admin_login', 'user', (int) $pendingId, ['ip' => $ip], 'admin');

redirect('../../frontend/pages/admin-dashboard.html');
