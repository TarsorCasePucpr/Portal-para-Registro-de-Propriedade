<?php
declare(strict_types=1);

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
require_once __DIR__ . '/../middleware/auth_guard.php';
require_once __DIR__ . '/../utils/response.php';

requireAuth();
$userId = (int) $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonError('Método não permitido.', 405);
}

$pdo = getDb();

$pagina    = max(1, (int) ($_GET['pagina']    ?? 1));
$porPagina = min(50, max(1, (int) ($_GET['por_pagina'] ?? 20)));
$offset    = ($pagina - 1) * $porPagina;

$statusFiltro  = trim($_GET['status'] ?? '');
$busca         = trim($_GET['q']      ?? '');

$statusValidos = ['normal', 'roubado', 'perdido'];

$where  = 'user_id = :uid AND deleted_at IS NULL';
$params = ['uid' => $userId];

if ($statusFiltro !== '' && in_array($statusFiltro, $statusValidos, true)) {
    $where           .= ' AND status = :status';
    $params['status'] = $statusFiltro;
}

if ($busca !== '') {
    $buscaSanitizada = '%' . str_replace(['%', '_', '\\'], ['\\%', '\\_', '\\\\'], $busca) . '%';
    $where           .= ' AND (descricao LIKE :busca OR serial_number LIKE :busca2)';
    $params['busca']  = $buscaSanitizada;
    $params['busca2'] = $buscaSanitizada;
}

try {
    $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM objects WHERE {$where}");
    $stmtCount->execute($params);
    $total = (int) $stmtCount->fetchColumn();

} catch (PDOException $e) {
    error_log('[listar.php/count] ' . $e->getMessage());
    jsonError('Erro interno.', 500);
}

try {
    $stmt = $pdo->prepare(
        "SELECT id,
                descricao,
                serial_number,
                status,
                nfe_validada,
                score,
                DATE_FORMAT(data_compra, '%d/%m/%Y') AS data_compra,
                DATE_FORMAT(created_at,  '%d/%m/%Y') AS registrado_em
         FROM   objects
         WHERE  {$where}
         ORDER  BY
                FIELD(status, 'roubado', 'perdido', 'normal'),
                created_at DESC
         LIMIT  :limit
         OFFSET :offset"
    );

    foreach ($params as $k => $v) {
        $stmt->bindValue(':' . $k, $v);
    }
    $stmt->bindValue(':limit',  $porPagina, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset,    PDO::PARAM_INT);
    $stmt->execute();

    $produtos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($produtos as &$p) {
        $p['id']           = (int)  $p['id'];
        $p['nfe_validada'] = (bool) $p['nfe_validada'];
        $p['score']        = (int)  $p['score'];
    }
    unset($p);

} catch (PDOException $e) {
    error_log('[listar.php/select] ' . $e->getMessage());
    jsonError('Erro interno ao listar produtos.', 500);
}

jsonSuccess([
    'produtos'   => $produtos,
    'total'      => $total,
    'pagina'     => $pagina,
    'por_pagina' => $porPagina,
    'paginas'    => (int) ceil($total / $porPagina),
]);
