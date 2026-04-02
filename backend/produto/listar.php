<?php
declare(strict_types=1);

/**
 * listar.php — Listagem de produtos do usuário autenticado
 *
 * GET /backend/produto/listar.php
 * GET /backend/produto/listar.php?pagina=2&por_pagina=20&status=roubado&q=notebook
 *
 * Parâmetros opcionais:
 *   pagina     → página atual (padrão: 1)
 *   por_pagina → itens por página (padrão: 20, máx: 50)
 *   status     → filtrar por status: normal | roubado | perdido
 *   q          → busca por descrição ou serial (parcial)
 *
 * Retorna:
 *   {
 *     success: true,
 *     produtos: [...],
 *     total: 42,
 *     pagina: 1,
 *     por_pagina: 20
 *   }
 *
 * Segurança:
 *   - Autenticação obrigatória
 *   - user_id SEMPRE da sessão — jamais de parâmetro de URL
 *   - soft-deleted não aparecem (deleted_at IS NULL)
 *   - Campos retornados são um subconjunto seguro (sem dados de outros usuários)
 *
 * LGPD:
 *   - Apenas os dados necessários para o dashboard são retornados
 *   - Serial truncado não é feito aqui — o frontend decide como exibir
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
require_once __DIR__ . '/../middleware/auth_guard.php';
require_once __DIR__ . '/../utils/response.php';

// ── Autenticação — user_id vem exclusivamente da sessão ──────────
requireAuth();
$userId = (int) $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonError('Método não permitido.', 405);
}

$pdo = getDb();

// ── Parâmetros de paginação ───────────────────────────────────────
$pagina    = max(1, (int) ($_GET['pagina']    ?? 1));
$porPagina = min(50, max(1, (int) ($_GET['por_pagina'] ?? 20)));
$offset    = ($pagina - 1) * $porPagina;

// ── Filtros opcionais ────────────────────────────────────────────
$statusFiltro  = trim($_GET['status'] ?? '');
$busca         = trim($_GET['q']      ?? '');

$statusValidos = ['normal', 'roubado', 'perdido'];

// ── Construir WHERE dinamicamente ───────────────────────────────
$where  = 'user_id = :uid AND deleted_at IS NULL';
$params = ['uid' => $userId];

if ($statusFiltro !== '' && in_array($statusFiltro, $statusValidos, true)) {
    $where          .= ' AND status = :status';
    $params['status'] = $statusFiltro;
}

if ($busca !== '') {
    $buscaSanitizada = '%' . str_replace(['%', '_', '\\'], ['\\%', '\\_', '\\\\'], $busca) . '%';
    $where           .= ' AND (descricao LIKE :busca OR serial_number LIKE :busca2)';
    $params['busca']  = $buscaSanitizada;
    $params['busca2'] = $buscaSanitizada;
}

// ── Contar total (para paginação no frontend) ────────────────────
try {
    $stmtCount = $pdo->prepare(
        "SELECT COUNT(*) FROM objects WHERE {$where}"
    );
    $stmtCount->execute($params);
    $total = (int) $stmtCount->fetchColumn();

} catch (PDOException $e) {
    error_log('[listar.php/count] ' . $e->getMessage());
    jsonError('Erro interno.', 500);
}

// ── Buscar produtos ──────────────────────────────────────────────
try {
    // Campos retornados: apenas o necessário para o card no dashboard
    // serial_number é retornado completo — frontend decide truncar ou não
    $stmt = $pdo->prepare(
        "SELECT id,
                descricao,
                serial_number,
                status,
                nfe_validada,
                score,
                DATE_FORMAT(data_compra, '%d/%m/%Y')  AS data_compra,
                DATE_FORMAT(created_at,  '%d/%m/%Y')  AS registrado_em,
                -- foto_produto e foto_serial são caminhos internos; não retornar URL direta
                CASE WHEN foto_produto IS NOT NULL THEN 1 ELSE 0 END AS tem_foto_produto,
                CASE WHEN foto_serial  IS NOT NULL THEN 1 ELSE 0 END AS tem_foto_serial
         FROM   objects
         WHERE  {$where}
         ORDER  BY
                -- Alertas ativos primeiro, depois mais recentes
                FIELD(status, 'roubado', 'perdido', 'normal'),
                created_at DESC
         LIMIT  :limit
         OFFSET :offset"
    );

    // PDO não aceita parâmetros nomeados para LIMIT/OFFSET → bindValue
    foreach ($params as $k => $v) {
        $stmt->bindValue(':' . $k, $v);
    }
    $stmt->bindValue(':limit',  $porPagina, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset,    PDO::PARAM_INT);
    $stmt->execute();

    $produtos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Cast de inteiros para não retornar strings desnecessariamente
    foreach ($produtos as &$p) {
        $p['id']             = (int)  $p['id'];
        $p['nfe_validada']   = (bool) $p['nfe_validada'];
        $p['score']          = (int)  $p['score'];
        $p['tem_foto_produto'] = (bool) $p['tem_foto_produto'];
        $p['tem_foto_serial']  = (bool) $p['tem_foto_serial'];
    }
    unset($p);

} catch (PDOException $e) {
    error_log('[listar.php/select] ' . $e->getMessage());
    jsonError('Erro interno ao listar produtos.', 500);
}

// ── Resposta ─────────────────────────────────────────────────────
jsonSuccess([
    'produtos'   => $produtos,
    'total'      => $total,
    'pagina'     => $pagina,
    'por_pagina' => $porPagina,
    'paginas'    => (int) ceil($total / $porPagina),
]);