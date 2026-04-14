<?php
declare(strict_types=1);

/**
 * cadastrar.php — Registro, exclusão e consulta de produtos
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
        jsonError('Chave da NF-e inválida (44 dígitos).');
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
            'descricao'    => $resultado['descricao'] ?? '',
            'serial'       => $resultado['serial'] ?? '',
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

        $stmt->execute([
            'id'  => $id,
            'uid' => $userId
        ]);

        if ($stmt->rowCount() === 0) {
            jsonError('Produto não encontrado ou sem permissão.', 403);
        }

        jsonSuccess([
            'mensagem' => 'Produto removido. Exclusão permanente em até 30 dias.'
        ]);

    } catch (PDOException $e) {
        error_log('[cadastrar.php/excluir] ' . $e->getMessage());
        jsonError('Erro interno ao excluir.', 500);
    }
}

// ════════════════════════════════════════════════════════════════
//  AÇÃO: cadastrar
// ════════════════════════════════════════════════════════════════

if (!checkRateLimit($pdo, $ip, 'cadastro_produto', 10, 60)) {
    jsonError('Limite de cadastros atingido. Tente novamente em 1 hora.', 429);
}

$descricao  = trim(htmlspecialchars($_POST['descricao'] ?? '', ENT_QUOTES, 'UTF-8'));
$serial     = trim(strip_tags($_POST['serial_number'] ?? ''));
$nfeChave   = preg_replace('/\D/', '', $_POST['nfe_chave'] ?? '');
$dataCompra = trim($_POST['data_compra'] ?? '');
$aceite     = ($_POST['aceite_termos'] ?? '0') === '1';

// Sanitização extra
$serial = preg_replace('/[\x00-\x1F\x7F]/u', '', $serial);

$erros = [];

// Validações
if ($descricao === '' || mb_strlen($descricao) < 5 || mb_strlen($descricao) > 500) {
    $erros[] = 'Descrição inválida (5–500 caracteres).';
}

if ($serial === '' || mb_strlen($serial) < 3 || mb_strlen($serial) > 100) {
    $erros[] = 'Número de série inválido (3–100 caracteres).';
}

if ($dataCompra !== '') {
    $dt = DateTimeImmutable::createFromFormat('Y-m-d', $dataCompra);
    if (!$dt || $dt > new DateTimeImmutable('today')) {
        $erros[] = 'Data de compra inválida.';
        $dataCompra = null;
    }
} else {
    $dataCompra = null;
}

if ($nfeChave !== '' && strlen($nfeChave) !== 44) {
    $nfeChave = '';
}

if (!$aceite) {
    $erros[] = 'Aceite a declaração para continuar.';
}

if (!empty($erros)) {
    jsonError(implode(' ', $erros));
}

// Score de confiabilidade
$score = 0;
if ($nfeChave !== '')            $score += 40;
if ($dataCompra !== null)        $score += 30;
if (mb_strlen($descricao) > 30)  $score += 30;

try {
    $stmt = $pdo->prepare(
        "INSERT INTO objects
           (user_id, descricao, serial_number, status,
            nfe_chave, nfe_validada, data_compra, score)
         VALUES
           (:uid, :desc, :serial, 'normal',
            :nfe, :nfe_val, :data, :score)"
    );

    $stmt->execute([
        'uid'     => $userId,
        'desc'    => $descricao,
        'serial'  => $serial,
        'nfe'     => $nfeChave !== '' ? $nfeChave : null,
        'nfe_val' => $nfeChave !== '' ? 1 : 0,
        'data'    => $dataCompra,
        'score'   => $score,
    ]);

    jsonSuccess([
        'mensagem' => 'Produto registrado com sucesso.',
        'score'    => $score,
    ]);

} catch (PDOException $e) {
    if ($e->getCode() === '23000') {
        jsonError('Este número de série já está registrado.');
    }
    error_log('[cadastrar.php] DB error: ' . $e->getMessage());
    jsonError('Erro interno ao registrar.', 500);
}

// ── Stub NF-e (futuro integração real) ───────────────────────────
function consultarNFe(string $chave): ?array
{
    return null;
}