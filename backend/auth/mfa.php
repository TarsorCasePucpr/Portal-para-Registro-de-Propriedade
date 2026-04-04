<?php
declare(strict_types=1);

ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Strict');
session_start();

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../middleware/csrf.php';
require_once __DIR__ . '/../middleware/rate_limiter.php';
require_once __DIR__ . '/../utils/response.php';

function base32Decode(string $base32): string {
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $base32   = strtoupper(rtrim($base32, '='));
    $binary   = '';
    for ($i = 0, $len = strlen($base32); $i < $len; $i++) {
        $pos = strpos($alphabet, $base32[$i]);
        if ($pos === false) continue;
        $binary .= str_pad(decbin($pos), 5, '0', STR_PAD_LEFT);
    }
    $bytes = '';
    for ($i = 0; $i + 8 <= strlen($binary); $i += 8) {
        $bytes .= chr(bindec(substr($binary, $i, 8)));
    }
    return $bytes;
}

function verifyTotp(string $secret, string $code, int $window = 2): bool {
    $key      = base32Decode($secret);
    $timeStep = (int) floor(time() / 30);
    for ($i = -$window; $i <= $window; $i++) {
        $step   = $timeStep + $i;
        $data   = pack('J', $step);
        $hash   = hash_hmac('sha1', $data, $key, true);
        $offset = ord($hash[19]) & 0x0F;
        $otp    = (
            ((ord($hash[$offset])     & 0x7F) << 24) |
            ((ord($hash[$offset + 1]) & 0xFF) << 16) |
            ((ord($hash[$offset + 2]) & 0xFF) << 8)  |
            ((ord($hash[$offset + 3]) & 0xFF))
        ) % 1_000_000;
        if (hash_equals(
            str_pad((string) $otp, 6, '0', STR_PAD_LEFT),
            str_pad($code, 6, '0', STR_PAD_LEFT)
        )) {
            return true;
        }
    }
    return false;
}

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

if (!checkRateLimit($pdo, $ip, 'mfa', 3, 10)) {
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
    redirect('../../frontend/pages/mfa.html?erro=' .
        urlencode('Código incorreto ou expirado. Tente novamente.'));
}

$userId = $pendingId;
unset($_SESSION['mfa_pending_user_id'], $_SESSION['mfa_pending_at']);
session_regenerate_id(true);
$_SESSION['user_id'] = $userId;

redirect('../../frontend/pages/dashboard.html');
