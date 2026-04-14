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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $acao = $_POST['acao'] ?? '';

    // ─── SOLICITAR LINK / REENVIAR ─────────────────────────────────────
    if ($acao === 'solicitar_link' || $acao === 'reenviar') {

        // CSRF apenas na primeira solicitação
        if ($acao === 'solicitar_link' && !validateCsrfToken($_POST['csrf'] ?? '')) {
            jsonResponse(['success' => false, 'message' => 'Token de segurança inválido.'], 403);
            exit;
        }

        if (!checkRateLimit($pdo, $ip, 'recover', 3, 15)) {
            jsonResponse(['success' => false, 'message' => 'Muitas tentativas. Aguarde 15 minutos.'], 429);
            exit;
        }

        $email = trim($_POST['email'] ?? '');

        // Anti-enumeration: sempre retorna sucesso
        if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            jsonResponse(['success' => true]);
            exit;
        }

        $stmt = $pdo->prepare('SELECT id, name FROM users WHERE email = :email AND deleted_at IS NULL LIMIT 1');
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            $token     = bin2hex(random_bytes(32));
            $tokenHash = hash('sha256', $token);

            $stmt = $pdo->prepare(
                "INSERT INTO tokens (user_id, token_hash, type, expires_at)
                 VALUES (:user_id, :hash, 'recovery', DATE_ADD(NOW(), INTERVAL 1 HOUR))"
            );
            $stmt->execute([
                'user_id' => $user['id'],
                'hash'    => $tokenHash
            ]);

            $link = $baseUrl . '/frontend/pages/redefinicao-senha.html?token=' . urlencode($token);

            try {
                enviarEmail(
                    destinatario: $email,
                    nome:         $user['name'],
                    assunto:      'SNGuard — Redefinição de senha',
                    corpo:        "Olá, {$user['name']}!\n\n" .
                                  "Recebemos uma solicitação para redefinir sua senha.\n\n" .
                                  "Clique no link abaixo (válido por 1 hora):\n\n" .
                                  "{$link}\n\n" .
                                  "Se não foi você, ignore este e-mail.\n\n" .
                                  "— Equipe SNGuard"
                );
            } catch (Throwable $e) {
                error_log('[recover] Erro ao enviar email: ' . $e->getMessage());
            }
        }

        jsonResponse(['success' => true]);
        exit;
    }

    // ─── REDEFINIR SENHA ───────────────────────────────────────────────
    if ($acao === 'redefinir_senha') {

        if (!validateCsrfToken($_POST['csrf'] ?? '')) {
            jsonResponse(['success' => false, 'message' => 'Token de segurança inválido.'], 403);
            exit;
        }

        if (!checkRateLimit($pdo, $ip, 'reset_password', 5, 10)) {
            jsonResponse(['success' => false, 'message' => 'Muitas tentativas.'], 429);
            exit;
        }

        $token     = $_POST['token'] ?? '';
        $novaSenha = $_POST['nova_senha'] ?? '';

        if (!$token || !$novaSenha) {
            jsonResponse(['success' => false, 'message' => 'Dados inválidos.'], 400);
            exit;
        }

        // Validação de senha forte (padronizada)
        if (!validarSenhaForte($novaSenha)) {
            jsonResponse([
                'success' => false,
                'message' => 'A senha não atende aos requisitos mínimos.'
            ], 400);
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
        $tokenData = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$tokenData) {
            jsonResponse(['success' => false, 'message' => 'Link inválido ou expirado.'], 400);
            exit;
        }

        // Atualiza senha
        $stmt = $pdo->prepare(
            'UPDATE users SET password_hash = :senha WHERE id = :id'
        );
        $stmt->execute([
            'senha' => hashPassword($novaSenha),
            'id'    => $tokenData['user_id']
        ]);

        // Invalida token
        $stmt = $pdo->prepare(
            'UPDATE tokens SET used_at = NOW() WHERE id = :id'
        );
        $stmt->execute(['id' => $tokenData['id']]);

        jsonResponse(['success' => true]);
        exit;
    }

    // ─── AÇÃO INVÁLIDA ───────────────────────────────────────────────
    jsonResponse(['success' => false, 'message' => 'Ação inválida.'], 400);
    exit;
}