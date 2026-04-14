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
    if ($acao === 'solicitar_link' || $acao === 'reenviar') {

        if ($acao === 'solicitar_link' && !validateCsrfToken($_POST['csrf'] ?? '')) {
            jsonResponse(['success' => false, 'message' => 'Token de segurança inválido.'], 403);
            exit;
        }

        if (!checkRateLimit($pdo, $ip, 'recover', 3, 15)) {
            jsonResponse(['success' => false, 'message' => 'Muitas tentativas. Aguarde 15 minutos.'], 429);
            exit;
        }

        $email = trim($_POST['email'] ?? '');

        // Anti-enumeration: sempre responder sucesso para o frontend
        if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            jsonResponse(['success' => true]); 
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

            // TODO: Enviar via PHPMailer
            error_log("[recover] Link de recuperação para {$email}: {$link}");
        }

        jsonResponse(['success' => true]);
        exit;
    }

    // ─── REDEFINIR SENHA ─────────────────────────────────────────────────────
    if ($acao === 'redefinir_senha') {

        if (!validateCsrfToken($_POST['csrf'] ?? '')) {
            jsonResponse(['success' => false, 'message' => 'Token de segurança inválido.'], 403);
            exit;
        }

        if (!checkRateLimit($pdo, $ip, 'reset_password', 5, 10)) {
            jsonResponse(['success' => false, 'message' => 'Muitas tentativas.'], 429);
            exit;
        }

        $token     = $_POST['token']     ?? '';
        $novaSenha = $_POST['nova_senha'] ?? '';

        if (!$token || !$novaSenha) {
            jsonResponse(['success' => false, 'message' => 'Dados inválidos.'], 400);
            exit;
        }

        if (!preg_match('/(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&]).{12,}/', $novaSenha)) {
            jsonResponse(['success' => false, 'message' => 'A senha não atende aos requisitos.'], 400);
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
            jsonResponse(['success' => false, 'message' => 'Link inválido ou expirado.'], 400);
            exit;
        }

        $stmt = $pdo->prepare('UPDATE users SET password_hash = :senha WHERE id = :id');
        $stmt->execute(['senha' => hashPassword($novaSenha), 'id' => $tokenData['user_id']]);

        $stmt = $pdo->prepare('UPDATE tokens SET used_at = NOW() WHERE id = :id');
        $stmt->execute(['id' => $tokenData['id']]);

        // VERSÃO HEAD: Retorna JSON para o fetch do JavaScript tratar
        jsonResponse(['success' => true]);
        exit;
    }
}