<?php
declare(strict_types=1);

/**
 * listar.php — Listagem de produtos do usuário autenticado
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
require_once __DIR__ . '/../middleware/auth_guard.php';
require_once __DIR__ . '/../utils/response.php';

// ── Autenticação ─────────────────────────────────────────────────
requireAuth();
$userId = (int) $_SESSION['user_id'];

// ── Apenas GET ───────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonError('Método não permitido.', 405);
}

$pdo = getDb();

// ── Paginação ────────────────────────────────────────────────────
$pagina    = max(1, (int) ($_GET['pagina'] ?? 1));
$porPagina = min(50, max(1, (int) ($_GET['por_pagina'] ?? 20)));
$offset    = ($pagina - 1) * $porPagina;

// ── Filtros ──────────────────────────────────────────────────────
$statusFiltro = trim($_GET['status'] ?? '');
$busca        = trim($_GET['q'] ?? '');

$statusValidos = ['normal', 'roubado', 'perdido'];

// ── WHERE dinâmico ───────────────────────────────────────────────
$where  = 'user_id = :uid AND deleted_at IS NULL';
$params = ['uid' => $userId];

if ($statusFiltro !== '' && in_array($statusFiltro, $statusValidos, true)) {
    $where .= ' AND status = :status';
    $params['status'] = $statusFiltro;
}

if ($busca !== '') {
    $buscaSanitizada = '%' . str_replace(['%', '_', '\\'], ['\\%', '\\_', '\\\\'], $busca) . '%';
    $where .= ' AND (descricao LIKE :busca OR serial_number LIKE :busca2)';
    $params['busca']  = $buscaSanitizada;
    $params['busca2'] = $buscaSanitizada;
}

// ── Total ────────────────────────────────────────────────────────
try {
    $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM objects WHERE {$where}");
    $stmtCount->execute($params);
    $total = (int) $stmtCount->fetchColumn();

} catch (PDOException $e) {
    error_log('[listar/count] ' . $e->getMessage());
    jsonError('Erro interno.', 500);
}

// ── Consulta principal ───────────────────────────────────────────
try {
    $stmt = $pdo->prepare(
        "SELECT id,
                descricao,
                serial_number,
                status,
                nfe_validada,
                score,
                DATE_FORMAT(data_compra, '%d/%m/%Y') AS data_compra,
                DATE_FORMAT(created_at, '%d/%m/%Y') AS registrado_em,
                CASE WHEN foto_produto IS NOT NULL THEN 1 ELSE 0 END AS tem_foto_produto,
                CASE WHEN foto_serial IS NOT NULL THEN 1 ELSE 0 END AS tem_foto_serial
         FROM objects
         WHERE {$where}
         ORDER BY
                FIELD(status, 'roubado', 'perdido', 'normal'),
                created_at DESC
         LIMIT :limit OFFSET :offset"
    );

    foreach ($params as $k => $v) {
        $stmt->bindValue(':' . $k, $v);
    }

    $stmt->bindValue(':limit', $porPagina, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

    $stmt->execute();
    $produtos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Tipagem correta
    foreach ($produtos as &$p) {
        $p['id'] = (int) $p['id'];
        $p['nfe_validada'] = (bool) $p['nfe_validada'];
        $p['score'] = (int) $p['score'];
        $p['tem_foto_produto'] = (bool) $p['tem_foto_produto'];
        $p['tem_foto_serial'] = (bool) $p['tem_foto_serial'];
    }
    unset($p);

} catch (PDOException $e) {
    error_log('[listar/select] ' . $e->getMessage());
    jsonError('Erro ao listar produtos.', 500);
}

// ── Resposta ─────────────────────────────────────────────────────
jsonSuccess([
    'produtos'   => $produtos,
    'total'      => $total,
    'pagina'     => $pagina,
    'por_pagina' => $porPagina,
    'paginas'    => (int) ceil($total / $porPagina),
]);