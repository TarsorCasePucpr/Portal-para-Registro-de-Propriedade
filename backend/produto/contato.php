<?php
declare(strict_types=1);

/**
 * contato.php — Mensagens anônimas ao proprietário de objeto
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
require_once __DIR__ . '/../utils/mailer.php';

$pdo    = getDb();
$ip     = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$metodo = $_SERVER['REQUEST_METHOD'];

// ════════════════════════════════════════════════════════════════
// GET — listar mensagens pendentes (dashboard)
// ════════════════════════════════════════════════════════════════
if ($metodo === 'GET' && ($_GET['acao'] ?? '') === 'listar_pendentes') {

    requireAuth();
    $userId = (int) $_SESSION['user_id'];

    try {
        $stmt = $pdo->prepare(
            'SELECT cm.id,
                    o.serial_number AS serial,
                    cm.mensagem,
                    cm.lida,
                    DATE_FORMAT(cm.created_at, "%d/%m/%Y %H:%i") AS recebida_em
             FROM contact_messages cm
             JOIN objects o ON o.id = cm.object_id
             WHERE o.user_id = :uid
               AND o.deleted_at IS NULL
               AND cm.lida = 0
             ORDER BY cm.created_at DESC
             LIMIT 50'
        );

        $stmt->execute(['uid' => $userId]);
        $mensagens = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Marcar como lidas
        if (!empty($mensagens)) {
            $ids = implode(',', array_map('intval', array_column($mensagens, 'id')));
            $pdo->exec("UPDATE contact_messages SET lida = 1 WHERE id IN ({$ids})");
        }

        jsonSuccess(['mensagens' => $mensagens]);

    } catch (PDOException $e) {
        error_log('[contato/listar] ' . $e->getMessage());
        jsonError('Erro interno.', 500);
    }
}

// ════════════════════════════════════════════════════════════════
// POST — enviar mensagem anônima
// ════════════════════════════════════════════════════════════════
if ($metodo !== 'POST') {
    jsonError('Método não permitido.', 405);
}

// ── CSRF ─────────────────────────────────────────────────────────
if (!validateCsrfToken($_POST['csrf'] ?? '')) {
    jsonError('Token de segurança inválido.', 403);
}

// ── Sanitização ──────────────────────────────────────────────────
$serial   = trim(strip_tags($_POST['serial'] ?? ''));
$mensagem = trim(htmlspecialchars($_POST['mensagem'] ?? '', ENT_QUOTES, 'UTF-8'));

$serial   = preg_replace('/[\x00-\x1F\x7F]/u', '', $serial);
$mensagem = preg_replace('/[\x00-\x08\x0B-\x1F\x7F]/u', '', $mensagem);

// ── Validação ────────────────────────────────────────────────────
if ($serial === '' || mb_strlen($serial) > 100) {
    jsonError('Número de série inválido.');
}

if ($mensagem === '' || mb_strlen($mensagem) < 10) {
    jsonError('Mensagem muito curta. Descreva melhor a situação.');
}

if (mb_strlen($mensagem) > 500) {
    jsonError('Mensagem muito longa (máximo 500 caracteres).');
}

// ── Rate limit (por serial + IP) ─────────────────────────────────
$acaoRL = 'contato_' . hash('crc32', $serial);

if (!checkRateLimit($pdo, $ip, $acaoRL, 3, 10)) {
    jsonError('Muitas mensagens para este produto. Aguarde alguns minutos.', 429);
}

// ── Buscar objeto ────────────────────────────────────────────────
try {
    $stmt = $pdo->prepare(
        'SELECT o.id AS object_id,
                o.status,
                o.descricao,
                u.email AS dono_email,
                u.name AS dono_nome
         FROM objects o
         JOIN users u ON u.id = o.user_id
         WHERE o.serial_number = :serial
           AND o.deleted_at IS NULL
           AND u.deleted_at IS NULL
           AND u.is_active = 1
         LIMIT 1'
    );

    $stmt->execute(['serial' => $serial]);
    $objeto = $stmt->fetch(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log('[contato/buscar] ' . $e->getMessage());
    jsonError('Erro interno.', 500);
}

if (!$objeto) {
    jsonError('Número de série não encontrado.');
}

// Só permite contato se estiver roubado/perdido
if (!in_array($objeto['status'], ['roubado', 'perdido'], true)) {
    jsonError('Este produto não possui alerta ativo.');
}

// ── Salvar mensagem ──────────────────────────────────────────────
try {
    $pdo->prepare(
        'INSERT INTO contact_messages (object_id, mensagem, ip_remetente)
         VALUES (:oid, :msg, :ip)'
    )->execute([
        'oid' => $objeto['object_id'],
        'msg' => $mensagem,
        'ip'  => $ip,
    ]);

} catch (PDOException $e) {
    error_log('[contato/insert] ' . $e->getMessage());
    jsonError('Erro ao salvar mensagem.', 500);
}

// ── Enviar e-mail ────────────────────────────────────────────────
$statusFormatado = ucfirst($objeto['status']);

$baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
         . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');

$corpo = "Olá, {$objeto['dono_nome']}!\n\n"
       . "Alguém encontrou um objeto seu e enviou uma mensagem:\n\n"
       . "Produto: {$objeto['descricao']}\n"
       . "Status:  {$statusFormatado}\n\n"
       . "--- MENSAGEM (anônima) ---\n"
       . strip_tags($mensagem) . "\n"
       . "--------------------------\n\n"
       . "Acesse seu painel:\n{$baseUrl}/frontend/pages/dashboard.html\n\n"
       . "— Equipe SNGuard\n\n"
       . "Nenhum dado do remetente foi compartilhado.";

try {
    enviarEmail(
        destinatario: $objeto['dono_email'],
        nome:         $objeto['dono_nome'],
        assunto:      "SNGuard — Nova mensagem sobre seu produto",
        corpo:        $corpo
    );
} catch (Throwable $e) {
    error_log('[contato/email] ' . $e->getMessage());
}

// ── Resposta ─────────────────────────────────────────────────────
jsonSuccess([
    'mensagem' => 'Mensagem enviada com sucesso. O proprietário foi notificado.'
]);