<?php
declare(strict_types=1);

<<<<<<< HEAD
/**
 * contato.php — Mensagens anônimas ao proprietário de objeto reportado
 *
 * POST /backend/produto/contato.php
 *   Campos: csrf, serial, mensagem
 *   → Envia e-mail anônimo ao dono e salva no banco
 *
 * GET /backend/produto/contato.php?acao=listar_pendentes
 *   → Retorna mensagens não lidas do usuário autenticado (dashboard)
 *
 * Segurança:
 *   - CSRF obrigatório no POST
 *   - Rate limit por IP: 3 mensagens / 10 min por serial
 *   - Objeto precisa ter status roubado/perdido para aceitar mensagem
 *   - E-mail do proprietário nunca é retornado ao remetente
 *   - Remetente é 100% anônimo — apenas IP é registrado para rate limit
 *
 * LGPD:
 *   - Nenhum dado pessoal do remetente é coletado ou armazenado além do IP
 *   - O IP é registrado apenas em rate_limits (TTL: 1h) e em contact_messages para auditoria
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
require_once __DIR__ . '/../utils/mailer.php';

$pdo    = getDb();
$ip     = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$metodo = $_SERVER['REQUEST_METHOD'];

// ════════════════════════════════════════════════════════════════
//  GET — listar mensagens pendentes (dashboard do dono)
//  Autenticação obrigatória apenas aqui — POST é anônimo por design
// ════════════════════════════════════════════════════════════════
if ($metodo === 'GET' && ($_GET['acao'] ?? '') === 'listar_pendentes') {

    requireAuth(); // ← guard só neste bloco, não no topo do arquivo
    $userId = (int) $_SESSION['user_id'];

    try {
        $stmt = $pdo->prepare(
            'SELECT cm.id,
                    o.serial_number  AS serial,
                    cm.mensagem,
                    cm.lida,
                    DATE_FORMAT(cm.created_at, \'%d/%m/%Y %H:%i\') AS recebida_em
             FROM   contact_messages cm
             JOIN   objects          o  ON o.id = cm.object_id
             WHERE  o.user_id    = :uid
               AND  o.deleted_at IS NULL
               AND  cm.lida      = 0
             ORDER  BY cm.created_at DESC
             LIMIT  50'
        );
        $stmt->execute(['uid' => $userId]);
        $mensagens = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Marcar como lidas após listar — IDs vêm do banco, mas força intval por padrão
        if (!empty($mensagens)) {
            $ids = implode(',', array_map('intval', array_column($mensagens, 'id')));
            $pdo->exec(
                "UPDATE contact_messages SET lida = 1
                 WHERE id IN ({$ids})"
            );
        }

        jsonSuccess(['mensagens' => $mensagens]);

    } catch (PDOException $e) {
        error_log('[contato.php/listar] ' . $e->getMessage());
        jsonError('Erro interno.', 500);
    }
}

// ════════════════════════════════════════════════════════════════
//  POST — enviar mensagem anônima
// ════════════════════════════════════════════════════════════════
if ($metodo !== 'POST') {
    jsonError('Método não permitido.', 405);
}

// ── CSRF ─────────────────────────────────────────────────────────
if (!validateCsrfToken($_POST['csrf'] ?? '')) {
    jsonError('Token de segurança inválido.', 403);
}

// ── Sanitizar entradas ────────────────────────────────────────────
$serial   = trim(strip_tags($_POST['serial']   ?? ''));
$mensagem = trim(htmlspecialchars($_POST['mensagem'] ?? '', ENT_QUOTES, 'UTF-8'));

// Remover caracteres de controle
$serial   = preg_replace('/[\x00-\x1F\x7F]/u', '', $serial);
$mensagem = preg_replace('/[\x00-\x08\x0B-\x1F\x7F]/u', '', $mensagem); // mantém \n e \r

// ── Validação ────────────────────────────────────────────────────
if ($serial === '' || mb_strlen($serial) > 100) {
    jsonError('Número de série inválido.');
}

if ($mensagem === '' || mb_strlen($mensagem) < 10) {
    jsonError('Mensagem muito curta. Descreva onde/como encontrou o objeto.');
}

if (mb_strlen($mensagem) > 500) {
    jsonError('Mensagem muito longa (máximo 500 caracteres).');
}

// ── Rate limit por IP + serial: 3 mensagens / 10 min ─────────────
$acaoRL = 'contato_' . hash('crc32', $serial); // ação única por serial
if (!checkRateLimit($pdo, $ip, $acaoRL, 3, 10)) {
    jsonError('Muitas mensagens para este serial. Aguarde alguns minutos.', 429);
}

// ── Buscar objeto e seu proprietário ─────────────────────────────
try {
    $stmt = $pdo->prepare(
        'SELECT o.id        AS object_id,
                o.status,
                o.descricao,
                u.email     AS dono_email,
                u.name      AS dono_nome
         FROM   objects o
         JOIN   users   u ON u.id = o.user_id
         WHERE  o.serial_number = :serial
           AND  o.deleted_at    IS NULL
           AND  u.deleted_at    IS NULL
           AND  u.is_active     = 1
         LIMIT  1'
    );
    $stmt->execute(['serial' => $serial]);
    $objeto = $stmt->fetch(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log('[contato.php/buscar] ' . $e->getMessage());
    jsonError('Erro interno. Tente novamente.', 500);
}

// ── Verificar se objeto existe e está com alerta ──────────────────
if (!$objeto) {
    jsonError('Número de série não encontrado.');
}

if (!in_array($objeto['status'], ['roubado', 'perdido'], true)) {
    jsonError('Este produto não possui alerta ativo. Mensagem não enviada.');
}

// ── Salvar mensagem no banco ──────────────────────────────────────
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
    error_log('[contato.php/insert] ' . $e->getMessage());
    jsonError('Erro interno ao salvar mensagem.', 500);
}

// ── Enviar e-mail ao proprietário (sem revelar remetente) ─────────
$statusFormatado = ucfirst($objeto['status']);
$descricaoObj    = htmlspecialchars($objeto['descricao'], ENT_QUOTES, 'UTF-8');
$msgFormatada    = nl2br(htmlspecialchars($mensagem, ENT_QUOTES, 'UTF-8'));

$corpo = "Olá, {$objeto['dono_nome']}!\n\n"
       . "Alguém encontrou um objeto seu registrado no SNGuard e enviou uma mensagem.\n\n"
       . "Produto: {$objeto['descricao']}\n"
       . "Status:  {$statusFormatado}\n\n"
       . "--- MENSAGEM (remetente anônimo) ---\n"
       . strip_tags($mensagem) . "\n"
       . "------------------------------------\n\n"
       . "Acesse seu painel para visualizar e responder se necessário:\n"
       . (BASE_URL ?? 'http://localhost') . "/frontend/pages/dashboard.html\n\n"
       . "— Equipe SNGuard\n\n"
       . "Este e-mail foi gerado automaticamente. Nenhum dado do remetente foi coletado.";

try {
    enviarEmail(
        destinatario: $objeto['dono_email'],
        nome:         $objeto['dono_nome'],
        assunto:      "SNGuard — Alguém encontrou seu produto ({$statusFormatado})",
        corpo:        $corpo
    );
} catch (Throwable $e) {
    // Falha no e-mail não impede sucesso — mensagem já está salva no banco
    error_log('[contato.php/email] ' . $e->getMessage());
}

jsonSuccess(['mensagem' => 'Mensagem enviada. O proprietário será notificado.']);
=======
// contato.php — Mensagem anônima ao proprietário de objeto encontrado
//
// Fluxo esperado:
//   1. Verificar token CSRF
//   2. Aplicar rate limiting — limitar mensagens por serial por IP para evitar spam
//   3. Sanitizar o número de série e o texto da mensagem
//   4. Só permitir envio se o objeto tiver status de roubado ou perdido
//   5. Buscar o e-mail do proprietário para enviar a notificação
//   6. O e-mail deve chegar ao dono sem revelar quem enviou — intermediação anônima
//   7. Nunca retornar ao remetente qualquer dado do proprietário
//
// LGPD:
//   - O remetente é anônimo por design — nenhum dado pessoal dele é coletado
//   - O IP é registrado apenas para rate limiting
>>>>>>> origin/develop
