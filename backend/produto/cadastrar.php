<?php
declare(strict_types=1);

/**
 * cadastrar.php — Registro e exclusão de produtos
 *
 * POST /backend/produto/cadastrar.php
 *
 * Ações (campo 'acao'):
 *   (omitido)  → cadastrar novo produto
 *   'excluir'  → soft delete de produto próprio
 *   'consultar_nf' → consulta chave NF-e na Receita (retorna JSON)
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

$pdo = getDb();
$ip  = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

// ── Apenas POST ──────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Método não permitido.', 405);
}

// ── CSRF ─────────────────────────────────────────────────────────
if (!validateCsrfToken($_POST['csrf'] ?? '')) {
    jsonError('Token de segurança inválido.', 403);
}

$acao = trim($_POST['acao'] ?? '');

// ════════════════════════════════════════════════════════════════
//  AÇÃO: consultar_nf
// ════════════════════════════════════════════════════════════════
if ($acao === 'consultar_nf') {

    if (!checkRateLimit($pdo, $ip, 'consulta_nf', 5, 5)) {
        jsonError('Muitas consultas de NF. Aguarde alguns minutos.', 429);
    }

    $chave = preg_replace('/\D/', '', $_POST['chave'] ?? '');

    if (strlen($chave) !== 44) {
        jsonError('Chave da NF-e inválida (deve ter 44 dígitos numéricos).');
    }

    $resultado = consultarNFe($chave);

    if (!$resultado) {
        jsonSuccess([
            'encontrado' => false,
            'produto'    => null,
        ]);
    }

    jsonSuccess([
        'encontrado' => true,
        'produto'    => [
            'descricao'    => $resultado['descricao']    ?? '',
            'serial'       => $resultado['serial']       ?? '',
            'data_emissao' => $resultado['data_emissao'] ?? '',
        ],
    ]);
}

// ════════════════════════════════════════════════════════════════
//  AÇÃO: excluir
// ════════════════════════════════════════════════════════════════
if ($acao === 'excluir') {

    $id = filter_var($_POST['id'] ?? '', FILTER_VALIDATE_INT);

    if (!$id || $id <= 0) {
        jsonError('ID inválido.');
    }

    try {
        $stmt = $pdo->prepare(
            'UPDATE objects
             SET deleted_at = NOW()
             WHERE id = :id AND user_id = :uid AND deleted_at IS NULL'
        );
        $stmt->execute(['id' => $id, 'uid' => $userId]);

        if ($stmt->rowCount() === 0) {
            jsonError('Produto não encontrado ou sem permissão.', 403);
        }

        jsonSuccess(['mensagem' => 'Produto removido.']);

    } catch (PDOException $e) {
        error_log('[cadastrar.php/excluir] ' . $e->getMessage());
        jsonError('Erro interno ao excluir.', 500);
    }
}

// ════════════════════════════════════════════════════════════════
//  AÇÃO: cadastrar
// ════════════════════════════════════════════════════════════════

if (!checkRateLimit($pdo, $ip, 'cadastro_produto', 10, 60)) {
    jsonError('Limite de cadastros atingido.', 429);
}

$descricao  = trim(htmlspecialchars($_POST['descricao'] ?? '', ENT_QUOTES, 'UTF-8'));
$serial     = trim(strip_tags($_POST['serial_number'] ?? ''));
$nfeChave   = preg_replace('/\D/', '', $_POST['nfe_chave'] ?? '');
$dataCompra = trim($_POST['data_compra'] ?? '');
$aceite     = ($_POST['aceite_termos'] ?? '0') === '1';

$erros = [];

if ($descricao === '' || mb_strlen($descricao) < 5 || mb_strlen($descricao) > 500) {
    $erros[] = 'Descrição inválida.';
}

if ($serial === '' || mb_strlen($serial) < 3 || mb_strlen($serial) > 100) {
    $erros[] = 'Número de série inválido.';
}

if (!$aceite) {
    $erros[] = 'Aceite obrigatório.';
}

if (!empty($erros)) {
    jsonError(implode(' ', $erros));
}

try {
    $stmt = $pdo->prepare(
        'INSERT INTO objects (user_id, descricao, serial_number, status)
         VALUES (:uid, :desc, :serial, \'normal\')'
    );
    $stmt->execute([
        'uid'    => $userId,
        'desc'   => $descricao,
        'serial' => $serial,
    ]);

    jsonSuccess(['mensagem' => 'Produto registrado com sucesso.']);

} catch (PDOException $e) {
    if ($e->getCode() === '23000') {
        jsonError('Serial já registrado.');
    }
    error_log('[cadastrar.php] ' . $e->getMessage());
    jsonError('Erro interno.', 500);
}

// Stub
function consultarNFe(string $chave): ?array
{
    return null;
}