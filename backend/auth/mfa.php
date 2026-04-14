<?php
declare(strict_types=1);

ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Strict');
session_start();

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../middleware/csrf.php';
require_once __DIR__ . '/../middleware/rate_limiter.php';
require_once __DIR__ . '/../utils/response.php';
require_once __DIR__ . '/../utils/totp.php';

$pendingId = isset($_SESSION['mfa_pending_user_id'])
    ? (int) $_SESSION['mfa_pending_user_id']
    : 0;

if ($pendingId === 0) {
    redirect('../../frontend/pages/login.html');
}

if (isset($_SESSION['mfa_pending_at']) &&
    time() - (int) $_SESSION['mfa_pending_at'] > 300) {
    session_unset();
    session_destroy();
    redirect('../../frontend/pages/login.html?erro=' .
        urlencode('Sessão expirada. Faça login novamente.'));
}

$pdo = getDb();
$ip  = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('../../frontend/pages/mfa.html');
}

if (!validateCsrfToken($_POST['csrf'] ?? '')) {
    redirect('../../frontend/pages/mfa.html?erro=' .
        urlencode('Token de segurança inválido.'));
}

if (isRateLimited($pdo, $ip, 'mfa', 3, 10)) {
    redirect('../../frontend/pages/mfa.html?erro=' .
        urlencode('Muitas tentativas incorretas. Aguarde 10 minutos.'));
}

$code = trim($_POST['code'] ?? '');

if (!preg_match('/^\d{6}$/', $code)) {
    redirect('../../frontend/pages/mfa.html?erro=' . urlencode('Código inválido.'));
}

try {
    $stmt = $pdo->prepare(
        'SELECT mfa_secret FROM users WHERE id = :id AND deleted_at IS NULL'
    );
    $stmt->execute(['id' => $pendingId]);
    $usuario = $stmt->fetch();
} catch (PDOException $e) {
    error_log('[mfa] DB fetch for validation: ' . $e->getMessage());
    redirect('../../frontend/pages/mfa.html?erro=' .
        urlencode('Erro interno. Tente novamente.'));
}

if (!$usuario) {
    session_unset();
    session_destroy();
    redirect('../../frontend/pages/login.html');
}

if (empty($usuario['mfa_secret'])) {
    redirect('../../frontend/pages/mfa.html?erro=' .
        urlencode('App autenticador não configurado. Contate o suporte.'));
}

if (!verifyTotp($usuario['mfa_secret'], $code)) {
    recordFailedAttempt($pdo, $ip, 'mfa');
    redirect('../../frontend/pages/mfa.html?erro=' .
        urlencode('Código incorreto ou expirado. Tente novamente.'));
}

$userId = $pendingId;
unset($_SESSION['mfa_pending_user_id'], $_SESSION['mfa_pending_at']);
session_regenerate_id(true);
$_SESSION['user_id'] = $userId;

redirect('../../frontend/pages/dashboard.html');
