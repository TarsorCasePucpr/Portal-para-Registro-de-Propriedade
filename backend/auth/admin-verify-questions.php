<?php
declare(strict_types=1);

ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.cookie_secure', '1');
ini_set('session.gc_maxlifetime', '14400');
session_start();

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../middleware/csrf.php';
require_once __DIR__ . '/../middleware/rate_limiter.php';
require_once __DIR__ . '/../utils/response.php';
require_once __DIR__ . '/../utils/logger.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('../../frontend/pages/admin-questions.html');
}

if (!validateCsrfToken($_POST['csrf'] ?? '')) {
    redirect('../../frontend/pages/admin-questions.html?erro=' . urlencode('Token de segurança inválido.'));
}

$pendingId = $_SESSION['admin_pending_id'] ?? null;
$pendingAt = $_SESSION['admin_pending_at'] ?? 0;
$fallback  = $_SESSION['admin_fallback_questions'] ?? false;

if (!$pendingId || !$fallback || (time() - (int) $pendingAt) > 900) {
    session_unset();
    redirect('../../frontend/pages/admin-login.html?erro=' . urlencode('Sessão expirada. Faça login novamente.'));
}

if (empty($_SESSION['admin_email_verified'])) {
    redirect('../../frontend/pages/admin-login.html?erro=' . urlencode('Confirme seu e-mail antes de responder as perguntas de segurança.'));
}

$pdo = getAdminDb();
$ip  = getClientIp();

if (isRateLimited($pdo, $ip, 'admin_question_verify', 5, 10)) {
    redirect('../../frontend/pages/admin-questions.html?erro=' . urlencode('Muitas tentativas. Aguarde.'));
}


$q1 = strtolower(trim($_POST['q1'] ?? ''));
$q2 = strtolower(trim($_POST['q2'] ?? ''));
$q3 = trim($_POST['q3'] ?? '');
$q4 = strtolower(trim($_POST['q4'] ?? ''));

// q2: aceita "4" ou "quatro"
$q2Normalized = preg_replace('/\D+/', '', $q2);
if ($q2Normalized === '') {
    $q2Normalized = ($q2 === 'quatro') ? '4' : $q2;
}

// q3: extrai só dígitos
$q3Normalized = preg_replace('/\D+/', '', $q3);

// ── Busca hashes no banco
try {
    $stmt = $pdo->prepare(
        'SELECT sa.answer1_hash, sa.answer2_hash, sa.answer3_hash, sa.answer4_hash
         FROM   admin_security_answers sa
         JOIN   users u           ON u.id  = sa.user_id
         JOIN   admin_profiles ap ON ap.user_id = u.id
         WHERE  sa.user_id    = :uid
           AND  u.deleted_at  IS NULL
           AND  u.is_active   = 1'
    );
    $stmt->execute(['uid' => $pendingId]);
    $row = $stmt->fetch();
} catch (\PDOException $e) {
    error_log('[admin-verify-questions] DB: ' . $e->getMessage());
    redirect('../../frontend/pages/admin-questions.html?erro=' . urlencode('Erro interno.'));
}

if (!$row) {
    session_unset();
    redirect('../../frontend/pages/admin-login.html?erro=' . urlencode('Sessão inválida.'));
}

// ── Comparação
$ok =  password_verify($q1,           $row['answer1_hash'])
    && password_verify($q2Normalized, $row['answer2_hash'])
    && password_verify($q3Normalized, $row['answer3_hash'])
    && password_verify($q4,           $row['answer4_hash']);

if (!$ok) {
    recordFailedAttempt($pdo, $ip, 'admin_question_verify');
    $email = urlencode($_POST['email'] ?? '');
    redirect('../../frontend/pages/admin-questions.html?email=' . $email
           . '&erro=' . urlencode('Respostas incorretas. Tente novamente.'));
}

// ── Autenticação
unset($_SESSION['admin_pending_id'], $_SESSION['admin_pending_at'], $_SESSION['admin_fallback_questions']);
session_regenerate_id(true);

$_SESSION['user_id']       = (int) $pendingId;
$_SESSION['is_admin']      = true;
$_SESSION['user_agent']    = $_SERVER['HTTP_USER_AGENT'] ?? '';
$_SESSION['ip']            = $ip;
$_SESSION['last_activity'] = time();

logAction($pdo, (int) $pendingId, 'admin_question_login', 'user', (int) $pendingId, ['ip' => $ip], 'admin');

redirect('../../frontend/pages/admin-dashboard.html');