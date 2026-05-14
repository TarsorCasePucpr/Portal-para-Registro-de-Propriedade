<?php
declare(strict_types=1);

ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Strict');
session_start();

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../middleware/csrf.php';
require_once __DIR__ . '/../middleware/rate_limiter.php';
require_once __DIR__ . '/../utils/response.php';
require_once __DIR__ . '/../utils/crypto.php';
require_once __DIR__ . '/../utils/admin_otp_flow.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('../../frontend/pages/admin-email-confirm.html');
}

if (!validateCsrfToken($_POST['csrf'] ?? '')) {
    redirect('../../frontend/pages/admin-email-confirm.html?erro=' . urlencode('Token de segurança inválido.'));
}

$pendingId = (int) ($_SESSION['admin_pending_id'] ?? 0);
$pendingAt = (int) ($_SESSION['admin_pending_at'] ?? 0);

if ($pendingId <= 0 || (time() - $pendingAt) > 900) {
    session_unset();
    redirect('../../frontend/pages/admin-login.html?erro=' . urlencode('Sessão expirada. Solicite o e-mail novamente.'));
}

$pdo   = getAdminDb();
$ip    = getClientIp();
$email = trim(strtolower($_POST['email'] ?? ''));
$code  = strtoupper(trim($_POST['code']  ?? ''));

if ($code === '' || strlen($code) !== 6) {
    redirect('../../frontend/pages/admin-email-confirm.html?email=' . urlencode($email) . '&erro=' . urlencode('Informe o código de 6 caracteres.'));
}

if (isRateLimited($pdo, $ip, 'admin_email_verify', 5, 10)) {
    redirect('../../frontend/pages/admin-email-confirm.html?email=' . urlencode($email) . '&erro=' . urlencode('Muitas tentativas. Aguarde alguns minutos.'));
}

try {
    $stmt = $pdo->prepare(
        "SELECT id FROM tokens
         WHERE user_id    = :uid
           AND short_code = :code
           AND type       = 'admin_email'
           AND used_at    IS NULL
           AND expires_at > NOW()
         LIMIT 1"
    );
    $stmt->execute(['uid' => $pendingId, 'code' => $code]);
    $token = $stmt->fetch();
} catch (\PDOException $e) {
    error_log('[admin-verify-email] DB: ' . $e->getMessage());
    redirect('../../frontend/pages/admin-email-confirm.html?email=' . urlencode($email) . '&erro=' . urlencode('Erro interno.'));
}

if (!$token) {
    recordFailedAttempt($pdo, $ip, 'admin_email_verify');
    redirect('../../frontend/pages/admin-email-confirm.html?email=' . urlencode($email) . '&erro=' . urlencode('Código inválido, já utilizado ou expirado.'));
}

try {
    $pdo->prepare('UPDATE tokens SET used_at = NOW() WHERE id = ?')->execute([$token['id']]);
} catch (\PDOException $e) {
    error_log('[admin-verify-email] mark used: ' . $e->getMessage());
}

$_SESSION['admin_email_verified'] = true;
$_SESSION['admin_pending_at']     = time();

triggerAdminTelegramStep($pdo, $pendingId, $email);
