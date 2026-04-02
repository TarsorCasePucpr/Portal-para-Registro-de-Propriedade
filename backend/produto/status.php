<?php
declare(strict_types=1);

/**
 * status.php — Alteração de status de um objeto do usuário autenticado
 *
 * POST /backend/produto/status.php
 * Campos: csrf, id (int), status (normal|roubado|perdido)
 *
 * Segurança:
 *   - Autenticação obrigatória
 *   - CSRF obrigatório
 *   - Status validado contra lista branca — jamais valor livre do usuário
 *   - UPDATE filtra por id E user_id DA SESSÃO:
 *       sem esse duplo filtro, qualquer usuário logado poderia alterar
 *       o status de qualquer objeto do sistema
 *   - Rate limit: 20 alterações / hora por IP (impede automação massiva)
 *
 * LGPD:
 *   - O status é informação pública (aparece na busca)
 *   - Qualquer alteração é auditável via updated_at
 */

ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Strict');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Cache-Control: no-store');

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

// ── Rate limit ────────────────────────────────────────────────────
if (!checkRateLimit($pdo, $ip, 'alterar_status', 20, 60)) {
    jsonError('Muitas alterações. Aguarde e tente novamente.', 429);
}

// ── Validar ID ────────────────────────────────────────────────────
$id = filter_var($_POST['id'] ?? '', FILTER_VALIDATE_INT);

if ($id === false || $id <= 0) {
    jsonError('ID do produto inválido.');
}

// ── Validar status contra lista branca ───────────────────────────
$statusPermitidos = ['normal', 'roubado', 'perdido'];
$novoStatus = trim($_POST['status'] ?? '');

if (!in_array($novoStatus, $statusPermitidos, true)) {
    jsonError('Status inválido. Use: normal, roubado ou perdido.');
}

// ── Atualizar — obrigatório filtrar por user_id DA SESSÃO ────────
try {
    $stmt = $pdo->prepare(
        'UPDATE objects
         SET    status     = :status,
                updated_at = NOW()
         WHERE  id         = :id
           AND  user_id    = :uid
           AND  deleted_at IS NULL'
    );
    $stmt->execute([
        'status' => $novoStatus,
        'id'     => $id,
        'uid'    => $userId,
    ]);

    // rowCount() = 0 significa que o id não pertence ao usuário, não existe,
    // ou já foi soft-deleted — em qualquer caso: 403
    if ($stmt->rowCount() === 0) {
        jsonError('Produto não encontrado ou sem permissão para alterar.', 403);
    }

} catch (PDOException $e) {
    error_log('[status.php] DB error: ' . $e->getMessage());
    jsonError('Erro interno. Tente novamente.', 500);
}

// ── Mensagem amigável por status ──────────────────────────────────
$mensagens = [
    'normal'  => 'Status atualizado para Protegido. Nenhum alerta ativo.',
    'perdido' => 'Produto marcado como Perdido. Um alerta foi ativado na busca pública.',
    'roubado' => 'Produto marcado como Roubado. Alerta crítico ativado na busca pública.',
];

jsonSuccess([
    'status'   => $novoStatus,
    'mensagem' => $mensagens[$novoStatus],
]);