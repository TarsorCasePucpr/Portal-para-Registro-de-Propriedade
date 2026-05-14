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
require_once __DIR__ . '/../utils/mailer.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('../../frontend/pages/admin-login.html');
}

if (!validateCsrfToken($_POST['csrf'] ?? '')) {
    redirect('../../frontend/pages/admin-login.html?erro=' . urlencode('Token de segurança inválido.'));
}

$pdo = getAdminDb();
$ip  = getClientIp();

if (isRateLimited($pdo, $ip, 'admin_otp_request', 5, 15)) {
    redirect('../../frontend/pages/admin-login.html?erro=' . urlencode('Muitas tentativas. Aguarde 15 minutos.'));
}

$email = trim(strtolower($_POST['email'] ?? ''));
if ($email === '') {
    redirect('../../frontend/pages/admin-login.html?erro=' . urlencode('Informe o e-mail.'));
}

try {
    $stmt = $pdo->prepare(
        'SELECT u.id AS user_id, u.name
         FROM   users u
         JOIN   admin_profiles ap ON ap.user_id = u.id
         WHERE  u.email_hash = :eh AND u.deleted_at IS NULL AND u.is_active = 1'
    );
    $stmt->execute(['eh' => hashField($email)]);
    $admin = $stmt->fetch();
} catch (\PDOException $e) {
    error_log('[admin-request-otp] DB: ' . $e->getMessage());
    redirect('../../frontend/pages/admin-login.html?erro=' . urlencode('Erro interno.'));
}

if (!$admin) {
    recordFailedAttempt($pdo, $ip, 'admin_otp_request');
    redirect('../../frontend/pages/admin-login.html?erro=' . urlencode('Usuário administrador não encontrado.'));
}

$tokenRaw  = bin2hex(random_bytes(32));
$tokenHash = hash('sha256', $tokenRaw);
$expires   = date('Y-m-d H:i:s', time() + 900);

$codeChars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
$shortCode = '';
for ($i = 0; $i < 6; $i++) {
    $shortCode .= $codeChars[random_int(0, strlen($codeChars) - 1)];
}

try {
    $pdo->prepare(
        "UPDATE tokens SET used_at = NOW()
         WHERE  user_id = ? AND type = 'admin_email' AND used_at IS NULL"
    )->execute([$admin['user_id']]);

    $pdo->prepare(
        "INSERT INTO tokens (user_id, token_hash, type, short_code, expires_at)
         VALUES (:uid, :hash, 'admin_email', :code, :exp)"
    )->execute([
        'uid'  => $admin['user_id'],
        'hash' => $tokenHash,
        'code' => $shortCode,
        'exp'  => $expires,
    ]);
} catch (\PDOException $e) {
    error_log('[admin-request-otp] token: ' . $e->getMessage());
    redirect('../../frontend/pages/admin-login.html?erro=' . urlencode('Erro ao gerar código.'));
}

$baseUrl = rtrim(
    getenv('APP_URL') ?:
    ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
    . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost')),
    '/'
);
$linkConfirmacao = $baseUrl . '/backend/auth/admin-confirm-email.php?token=' . urlencode($tokenRaw);
$nomeAdmin       = trim((string) ($admin['name'] ?? 'Administrador'));

try {
    enviarEmail(
        destinatario: $email,
        nome:         $nomeAdmin,
        assunto:      'SNGuard — Acesso administrativo (passo 1 de 2)',
        corpo:        "Olá, {$nomeAdmin}!\n\n" .
                      "Foi solicitado um acesso ao painel administrativo. Para continuar, confirme seu e-mail (válido por 15 minutos):\n\n" .
                      "1) Clique no link abaixo:\n\n" .
                      "{$linkConfirmacao}\n\n" .
                      "2) Ou, se o link não funcionar, informe na página de confirmação o código:\n\n" .
                      "Código: {$shortCode}\n\n" .
                      "Após confirmar o e-mail, um segundo código será enviado pelo Telegram para finalizar o acesso.\n\n" .
                      "Se você não solicitou este acesso, ignore esta mensagem e considere revisar a segurança da sua conta.\n\n" .
                      "— Equipe SNGuard"
    );
} catch (Throwable $e) {
    error_log('[admin-request-otp] mailer: ' . $e->getMessage());
    redirect('../../frontend/pages/admin-login.html?erro=' . urlencode('Falha ao enviar e-mail. Tente novamente em instantes.'));
}

$_SESSION['admin_pending_id']     = (int) $admin['user_id'];
$_SESSION['admin_pending_at']     = time();
$_SESSION['admin_email_verified'] = false;
unset($_SESSION['admin_fallback_questions']);

redirect('../../frontend/pages/admin-email-confirm.html?email=' . urlencode($email));
