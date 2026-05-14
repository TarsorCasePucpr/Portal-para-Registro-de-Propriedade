<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../middleware/auth_guard.php';
require_once __DIR__ . '/../middleware/csrf.php';
require_once __DIR__ . '/../utils/response.php';
require_once __DIR__ . '/../utils/logger.php';
require_once __DIR__ . '/../utils/crypto.php';
require_once __DIR__ . '/../utils/admin_search.php';

requireAdmin();

$pdo     = getAdminDb();
$adminId = (int) $_SESSION['user_id'];
$method  = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $page    = max(1, (int) ($_GET['page']     ?? 1));
    $perPage = min(50, max(1, (int) ($_GET['per_page'] ?? 20)));
    $busca   = trim($_GET['busca']  ?? '');
    $status  = trim($_GET['status'] ?? 'todos');
    $offset  = ($page - 1) * $perPage;

    $where  = [];
    $params = [];

    if (in_array($status, ['normal','roubado','perdido'], true)) {
        $where[]          = 'status = :status';
        $params['status'] = $status;
    }

    $sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    try {
        $stmtList = $pdo->prepare(
            "SELECT id, descricao, serial_number, status, nfe_chave, nfe_validada,
                    score, created_at, user_name, user_email
             FROM   v_user_objects
             $sql
             ORDER  BY created_at DESC"
        );
        foreach ($params as $k => $v) $stmtList->bindValue($k, $v);
        $stmtList->execute();

        $objetos = array_map(function (array $o): array {
            $o['user_email'] = decryptField((string) $o['user_email']);
            return $o;
        }, $stmtList->fetchAll());

        if ($busca !== '') {
            $objetos = array_values(array_filter(
                $objetos,
                fn(array $r) => rowMatchesTerm($r, $busca, ['descricao', 'serial_number', 'nfe_chave', 'user_name', 'user_email'])
            ));
        }

        $pag = paginateArray($objetos, $page, $perPage);

        jsonSuccess(['data' => [
            'objetos'   => $pag['items'],
            'total'     => $pag['total'],
            'page'      => $page,
            'last_page' => $pag['last_page'],
        ]]);

    } catch (\PDOException $e) {
        error_log('[admin/objetos GET] ' . $e->getMessage());
        jsonError('Erro interno.', 500);
    }
}

if ($method === 'PATCH') {
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $csrf = trim((string) ($body['csrf'] ?? ''));

    if (!validateCsrfToken($csrf)) jsonError('Token de segurança inválido.', 403);

    $id   = (int) ($body['id']   ?? 0);
    $acao = trim((string) ($body['acao'] ?? ''));

    if ($id <= 0) jsonError('ID inválido.');

    try {
        switch ($acao) {
            case 'alterar_status':
                $novoStatus = trim((string) ($body['status'] ?? ''));
                if (!in_array($novoStatus, ['normal','roubado','perdido'], true)) jsonError('Status inválido.');
                $pdo->prepare('UPDATE objects SET status = ? WHERE id = ?')->execute([$novoStatus, $id]);
                logAction($pdo, $adminId, 'admin_alterar_status_objeto', 'object', $id, ['status' => $novoStatus], 'admin');
                jsonSuccess(['data' => ['mensagem' => "Status alterado para {$novoStatus}."]]);
                break;
            case 'excluir':
                $pdo->prepare('UPDATE objects SET deleted_at = NOW() WHERE id = ?')->execute([$id]);
                logAction($pdo, $adminId, 'admin_excluir_objeto', 'object', $id, [], 'admin');
                jsonSuccess(['data' => ['mensagem' => 'Objeto removido.']]);
                break;
            default:
                jsonError('Ação inválida.');
        }
    } catch (\PDOException $e) {
        error_log('[admin/objetos PATCH] ' . $e->getMessage());
        jsonError('Erro ao executar ação.', 500);
    }
}

jsonError('Método não permitido.', 405);
