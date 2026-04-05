<?php
declare(strict_types=1);

ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Strict');
session_start();

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../middleware/csrf.php';
require_once __DIR__ . '/../utils/response.php';
require_once __DIR__ . '/../utils/totp.php';

$userId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;
if ($userId === 0) {
    jsonError('Não autenticado.', 401);
}

// ── GET: gerar e retornar o secret temporário ─────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (empty($_SESSION['mfa_setup_secret'])) {
        $_SESSION['mfa_setup_secret'] = generateTotpSecret();
    }
    $secret = $_SESSION['mfa_setup_secret'];

    try {
        $pdo  = getDb();
        $stmt = $pdo->prepare("SELECT email FROM users WHERE id = :id AND deleted_at IS NULL");
        $stmt->execute(['id' => $userId]);
        $user = $stmt->fetch();
    } catch (PDOException $e) {
        error_log('[setup_mfa] GET: ' . $e->getMessage());
        jsonError('Erro interno.', 500);
    }

    if (!$user) {
        jsonError('Usuário não encontrado.', 404);
    }

    $label = 'SNGuard:' . rawurlencode($user['email']);
    $uri   = 'otpauth://totp/' . $label . '?secret=' . $secret . '&issuer=SNGuard';

    session_write_close();
    jsonSuccess(['secret' => $secret, 'uri' => $uri]);
}

// ── POST: confirmar código e salvar ──────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST['csrf'] ?? '')) {
        jsonError('Token de segurança inválido.', 403);
    }

    $code   = trim($_POST['code'] ?? '');
    $secret = $_SESSION['mfa_setup_secret'] ?? '';

    if (empty($secret)) {
        jsonError('Sessão expirada. Recarregue a página.');
    }

    if (!preg_match('/^\d{6}$/', $code)) {
        jsonError('O código deve ter 6 dígitos numéricos.');
    }

    if (!verifyTotp($secret, $code)) {
        jsonError('Código incorreto. Verifique o app e tente novamente.');
    }

    try {
        $pdo  = getDb();
        $stmt = $pdo->prepare(
            "UPDATE users SET mfa_secret = :secret, mfa_enabled = 1 WHERE id = :id"
        );
        $stmt->execute(['secret' => $secret, 'id' => $userId]);
    } catch (PDOException $e) {
        error_log('[setup_mfa] POST: ' . $e->getMessage());
        jsonError('Erro interno. Tente novamente.', 500);
    }

    unset($_SESSION['mfa_setup_secret']);
    session_write_close();

    jsonSuccess(['message' => '2FA ativado com sucesso.']);
}

jsonError('Método não permitido.', 405);
