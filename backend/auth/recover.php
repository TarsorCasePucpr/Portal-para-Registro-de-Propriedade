<?php
declare(strict_types=1);
require_once "../middleware/csrf.php";
require_once "../middleware/rate_limiter.php";
require_once "../config/db.php";
require_once "../utils/hash.php";
require_once "../utils/response.php";
require_once "../utils/mailer.php";
require_once "../utils/validadores.php";

startSessionSafe();

$pdo = getDb();
$ip  = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

$baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
         . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('../../frontend/pages/recuperacao-senha.html');
}

$acao = $_POST['acao'] ?? '';

if ($acao === 'solicitar_link') {

    if (!validateCsrfToken($_POST['csrf'] ?? '')) {
        jsonError('Token de segurança inválido.', 403);
    }

    if (!checkRateLimit($pdo, $ip, 'recover', 3, 15)) {
        jsonError('Muitas tentativas. Aguarde 15 minutos.', 429);
    }

    $email = trim($_POST['email'] ?? '');

    if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        jsonSuccess();
    }

    try {
        _gerarEEnviarToken($pdo, $email, $baseUrl);
    } catch (PDOException $e) {
        error_log('[recover] DB error em solicitar_link: ' . $e->getMessage());
        jsonError('Erro interno. Tente novamente mais tarde.', 500);
    }

    jsonSuccess();
}

if ($acao === 'reenviar') {

    if (!validateCsrfToken($_POST['csrf'] ?? '')) {
        jsonError('Token de segurança inválido.', 403);
    }

    if (!checkRateLimit($pdo, $ip, 'recover', 3, 15)) {
        jsonError('Muitas tentativas. Aguarde 15 minutos.', 429);
    }

    $email = trim($_POST['email'] ?? '');

    if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        jsonSuccess();
    }

    try {
        _gerarEEnviarToken($pdo, $email, $baseUrl);
    } catch (PDOException $e) {
        error_log('[recover] DB error em reenviar: ' . $e->getMessage());
        jsonError('Erro interno. Tente novamente mais tarde.', 500);
    }

    jsonSuccess();
}

if ($acao === 'redefinir_senha') {

    if (!validateCsrfToken($_POST['csrf'] ?? '')) {
        jsonError('Token de segurança inválido.', 403);
    }

    if (!checkRateLimit($pdo, $ip, 'reset_password', 5, 10)) {
        jsonError('Muitas tentativas. Tente novamente em 10 minutos.', 429);
    }

    $token     = $_POST['token']     ?? '';
    $novaSenha = $_POST['nova_senha'] ?? '';

    if (!$token || !$novaSenha) {
        jsonError('Dados inválidos.');
    }

    if (!validarSenhaForte($novaSenha)) {
        jsonError('A senha não atende aos requisitos mínimos.');
    }

    try {
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
            http_response_code(400);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'token_invalido' => true, 'error' => 'Link inválido ou expirado.']);
            exit;
        }

        $pdo->prepare('UPDATE users SET password_hash = :senha WHERE id = :id')
            ->execute(['senha' => hashPassword($novaSenha), 'id' => $tokenData['user_id']]);

        $pdo->prepare('UPDATE tokens SET used_at = NOW() WHERE id = :id')
            ->execute(['id' => $tokenData['id']]);

    } catch (PDOException $e) {
        error_log('[recover] DB error em redefinir_senha: ' . $e->getMessage());
        jsonError('Erro interno. Tente novamente mais tarde.', 500);
    }

    jsonSuccess();
}

function _gerarEEnviarToken(PDO $pdo, string $email, string $baseUrl): void
{
    $stmt = $pdo->prepare('SELECT id, name FROM users WHERE email = :email AND deleted_at IS NULL LIMIT 1');
    $stmt->execute(['email' => $email]);
    $user = $stmt->fetch();

    if (!$user) {
        return;
    }

    $token     = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $token);

    $pdo->prepare(
        "INSERT INTO tokens (user_id, token_hash, type, expires_at)
         VALUES (:user_id, :hash, 'recovery', DATE_ADD(NOW(), INTERVAL 1 HOUR))"
    )->execute(['user_id' => $user['id'], 'hash' => $tokenHash]);

    $link = $baseUrl . '/frontend/pages/redefinicao-senha.html?token=' . urlencode($token);

    try {
        enviarEmail(
            destinatario: $email,
            nome:         $user['name'],
            assunto:      'SNGuard — Redefinição de senha',
            corpo:        "Olá, {$user['name']}!\n\n" .
                          "Recebemos uma solicitação para redefinir a senha da sua conta.\n\n" .
                          "Clique no link abaixo para criar uma nova senha (válido por 1 hora):\n\n" .
                          "{$link}\n\n" .
                          "Se você não solicitou isso, ignore esta mensagem — sua senha permanece a mesma.\n\n" .
                          "— Equipe SNGuard"
        );
    } catch (Throwable $e) {
        error_log('[recover] Falha ao enviar email para ' . $email . ': ' . $e->getMessage());
    }
}
