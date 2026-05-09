<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../middleware/rate_limiter.php';
require_once __DIR__ . '/../utils/mailer.php';
require_once __DIR__ . '/../utils/response.php';
require_once __DIR__ . '/../utils/validadores.php';

if (session_status() === PHP_SESSION_NONE) session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Método não permitido.', 405);
}

$pdo = getDb();
$ip  = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

// Generic response regardless of outcome — prevents email enumeration
$genericMsg = 'Se o e-mail existir, um novo link será enviado.';

if (!checkRateLimit($pdo, $ip, 'resend_confirm', 3, 10)) {
    jsonSuccess(['message' => $genericMsg]);
}

$email = strtolower(trim($_POST['email'] ?? ''));

if (!validarEmail($email)) {
    jsonSuccess(['message' => $genericMsg]);
}

try {
    $stmt = $pdo->prepare(
        'SELECT id, name FROM users
         WHERE email = :email AND is_active = 0 AND deleted_at IS NULL
         LIMIT 1'
    );
    $stmt->execute(['email' => $email]);
    $user = $stmt->fetch();

    if (!$user) {
        jsonSuccess(['message' => $genericMsg]);
    }

    $pdo->prepare(
        "UPDATE tokens SET used_at = NOW()
         WHERE user_id = :uid AND type = 'confirm' AND used_at IS NULL"
    )->execute(['uid' => $user['id']]);

    $tokenRaw  = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $tokenRaw);
    $expira    = date('Y-m-d H:i:s', time() + 86400);

    $codeChars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $shortCode = '';
    for ($i = 0; $i < 6; $i++) {
        $shortCode .= $codeChars[random_int(0, strlen($codeChars) - 1)];
    }

    $pdo->prepare(
        "INSERT INTO tokens (user_id, token_hash, type, short_code, expires_at)
         VALUES (:uid, :hash, 'confirm', :code, :exp)"
    )->execute([
        'uid'  => $user['id'],
        'hash' => $tokenHash,
        'code' => $shortCode,
        'exp'  => $expira,
    ]);

    $baseUrl = rtrim(
        getenv('APP_URL') ?:
        ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
        . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost')),
        '/'
    );
    $link = $baseUrl . '/backend/auth/confirm.php?token=' . urlencode($tokenRaw);

    enviarEmail(
        destinatario: $email,
        nome:         $user['name'],
        assunto:      'SNGuard — Confirme seu e-mail',
        corpo:        "Olá, {$user['name']}!\n\n" .
                      "Você solicitou um novo link de confirmação. Clique abaixo para ativar sua conta (válido por 24 horas):\n\n" .
                      "{$link}\n\n" .
                      "Se o link não funcionar, acesse a página de confirmação e informe seu e-mail com o código:\n\n" .
                      "Código de confirmação: {$shortCode}\n\n" .
                      "Se você não solicitou isso, ignore esta mensagem.\n\n" .
                      "— Equipe SNGuard"
    );

} catch (Throwable $e) {
    error_log('[resend_confirm] ' . $e->getMessage());
}

jsonSuccess(['message' => $genericMsg]);
