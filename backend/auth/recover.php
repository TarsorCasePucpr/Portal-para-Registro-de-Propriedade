<?php
declare(strict_types=1);
require_once "../middleware/csrf.php";
require_once "../middleware/rate_limiter.php";
require_once "../config/db.php";
require_once "../utils/hash.php";
require_once "../utils/response.php";

startSessionSafe();

$pdo = getDb();
$ip  = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

$baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
         . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $acao = $_POST['acao'] ?? '';

    // ─── SOLICITAR LINK ───────────────────────────────────────────────────────
    if ($acao === 'solicitar_link') {

        if (!validateCsrfToken($_POST['csrf'] ?? '')) {
            header('Location: ../../frontend/pages/recuperacao-senha.html?erro=' . urlencode('Token de segurança inválido.'));
            exit;
        }

        if (!checkRateLimit($pdo, $ip, 'recover', 3, 15)) {
            header('Location: ../../frontend/pages/recuperacao-senha.html?erro=' . urlencode('Muitas tentativas. Aguarde 15 minutos.'));
            exit;
        }

        $email = trim($_POST['email'] ?? '');

        // Anti-enumeration: sempre redirecionar com msg=ok
        if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            header('Location: ../../frontend/pages/recuperacao-senha.html?msg=ok');
            exit;
        }

        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = :email AND deleted_at IS NULL LIMIT 1');
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();

        if ($user) {
            $token     = bin2hex(random_bytes(32));
            $tokenHash = hash('sha256', $token);

            $stmt = $pdo->prepare(
                "INSERT INTO tokens (user_id, token_hash, type, expires_at)
                 VALUES (:user_id, :hash, 'recovery', DATE_ADD(NOW(), INTERVAL 1 HOUR))"
            );
            $stmt->execute(['user_id' => $user['id'], 'hash' => $tokenHash]);

            $link = $baseUrl . '/frontend/pages/redefinicao-senha.html?token=' . urlencode($token);

            // TODO: enviar por email via PHPMailer
            error_log("[recover] Link de recuperação para {$email}: {$link}");
        }

        header('Location: ../../frontend/pages/recuperacao-senha.html?msg=ok');
        exit;
    }

    // ─── REDEFINIR SENHA ─────────────────────────────────────────────────────
    if ($acao === 'redefinir_senha') {

        if (!validateCsrfToken($_POST['csrf'] ?? '')) {
            header('Location: ../../frontend/pages/redefinicao-senha.html?erro=' . urlencode('Token de segurança inválido.'));
            exit;
        }

        if (!checkRateLimit($pdo, $ip, 'reset_password', 5, 10)) {
            header('Location: ../../frontend/pages/redefinicao-senha.html?erro=' . urlencode('Muitas tentativas. Tente novamente em 10 minutos.'));
            exit;
        }

        $token     = $_POST['token']     ?? '';
        $novaSenha = $_POST['nova_senha'] ?? '';

        if (!$token || !$novaSenha) {
            header('Location: ../../frontend/pages/redefinicao-senha.html?erro=' . urlencode('Dados inválidos.'));
            exit;
        }

        if (!preg_match('/(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&]).{12,}/', $novaSenha)) {
            header('Location: ../../frontend/pages/redefinicao-senha.html?erro=' . urlencode('A senha não atende aos requisitos mínimos.'));
            exit;
        }

        $tokenHash = hash('sha256', $token);

        $stmt = $pdo->prepare(
            "SELECT * FROM tokens
             WHERE token_hash = :hash
               AND type = 'recovery'
               AND used_at IS NULL
               AND expires_at > NOW()
             LIMIT 1"
        );
        $stmt->execute(['hash' => $tokenHash]);
        $tokenData = $stmt->fetch();

        if (!$tokenData) {
            header('Location: ../../frontend/pages/redefinicao-senha.html?erro=' . urlencode('Link inválido ou expirado.'));
            exit;
        }

        $stmt = $pdo->prepare('UPDATE users SET password_hash = :senha WHERE id = :id');
        $stmt->execute(['senha' => hashPassword($novaSenha), 'id' => $tokenData['user_id']]);

        $stmt = $pdo->prepare('UPDATE tokens SET used_at = NOW() WHERE id = :id');
        $stmt->execute(['id' => $tokenData['id']]);

<<<<<<< HEAD
        jsonResponse(['success' => true]);
        
=======
        header('Location: ../../frontend/pages/login.html?reset=success');
        exit;
>>>>>>> origin/develop
    }
}
