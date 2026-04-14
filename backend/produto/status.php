<?php
declare(strict_types=1);

/**
 * status.php — Alteração de status de um objeto do usuário autenticado
 */

ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Strict');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ── Headers de segurança ─────────────────────────────────────────
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Cache-Control: no-store');

// ── Dependências ─────────────────────────────────────────────────
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../middleware/csrf.php';
require_once __DIR__ . '/../middleware/auth_guard.php';
require_once __DIR__ . '/../middleware/rate_limiter.php';
require_once __DIR__ . '/../utils/response.php';

// ── Autenticação ─────────────────────────────────────────────────
requireAuth();
$userId = (int) $_SESSION['user_id'];

// ── Apenas POST ──────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Método não permitido.', 405);
}

// ── CSRF ─────────────────────────────────────────────────────────
if (!validateCsrfToken($_POST['csrf'] ?? '')) {
    jsonError('Token de segurança inválido.', 403);
}

$pdo = getDb();
$ip  = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

// ── Rate limit ───────────────────────────────────────────────────
if (!checkRateLimit($pdo, $ip, 'alterar_status', 20, 60)) {
    jsonError('Muitas alterações. Aguarde e tente novamente.', 429);
}

// ── Validar ID ───────────────────────────────────────────────────
$id = filter_var($_POST['id'] ?? '', FILTER_VALIDATE_INT);

if ($id === false || $id <= 0) {
    jsonError('ID do produto inválido.');
}

// ── Validar status ───────────────────────────────────────────────
$statusPermitidos = ['normal', 'roubado', 'perdido'];
$novoStatus = trim($_POST['status'] ?? '');

if (!in_array($novoStatus, $statusPermitidos, true)) {
    jsonError('Status inválido. Use: normal, roubado ou perdido.');
}

// ── Atualizar no banco ───────────────────────────────────────────
try {
    $stmt = $pdo->prepare(
        'UPDATE objects
         SET status = :status,
             updated_at = NOW()
         WHERE id = :id
           AND user_id = :uid
           AND deleted_at IS NULL'
    );

    $stmt->execute([
        'status' => $novoStatus,
        'id'     => $id,
        'uid'    => $userId,
    ]);

    if ($stmt->rowCount() === 0) {
        jsonError('Produto não encontrado ou sem permissão para alterar.', 403);
    }

} catch (PDOException $e) {
    error_log('[status.php] DB error: ' . $e->getMessage());
    jsonError('Erro interno. Tente novamente.', 500);
}

// ── Mensagens amigáveis ──────────────────────────────────────────
$mensagens = [
    'normal'  => 'Status atualizado para Protegido. Nenhum alerta ativo.',
    'perdido' => 'Produto marcado como Perdido. Um alerta foi ativado na busca pública.',
    'roubado' => 'Produto marcado como Roubado. Alerta crítico ativado na busca pública.',
];

// ── Resposta ─────────────────────────────────────────────────────
jsonSuccess([
    'status'   => $novoStatus,
    'mensagem' => $mensagens[$novoStatus],
]);