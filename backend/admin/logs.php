<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../middleware/auth_guard.php';
require_once __DIR__ . '/../utils/response.php';

requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') jsonError('Método não permitido.', 405);

$pdo     = getDb();
$page    = max(1, (int) ($_GET['page']     ?? 1));
$perPage = min(100, max(1, (int) ($_GET['per_page'] ?? 50)));
$busca   = trim($_GET['busca']   ?? '');
$role    = trim($_GET['role']    ?? 'todos');
$offset  = ($page - 1) * $perPage;

$where  = ['1=1'];
$params = [];

if ($busca !== '') {
    $where[]     = '(l.action LIKE :b OR l.entity LIKE :b OR l.ip LIKE :b OR user_email LIKE :b)';
    $params['b'] = '%' . $busca . '%';
}
if (in_array($role, ['user','admin'], true)) {
    $where[]         = 'l.role = :role';
    $params['role']  = $role;
}

$sql = 'WHERE ' . implode(' AND ', $where);

try {
    $stmtCount = $pdo->prepare(
        "SELECT COUNT(*) FROM v_admin_action_logs l $sql"
    );
    $stmtCount->execute($params);
    $total = (int) $stmtCount->fetchColumn();

    $stmtList = $pdo->prepare(
        "SELECT id, role, action, entity, entity_id, ip, created_at, details,
                user_email, user_name
         FROM   v_admin_action_logs l
         $sql
         ORDER  BY created_at DESC
         LIMIT  :limit OFFSET :offset"
    );
    foreach ($params as $k => $v) $stmtList->bindValue($k, $v);
    $stmtList->bindValue(':limit',  $perPage, PDO::PARAM_INT);
    $stmtList->bindValue(':offset', $offset,  PDO::PARAM_INT);
    $stmtList->execute();
    $logs = $stmtList->fetchAll();

    jsonSuccess(['data' => [
        'logs'      => $logs,
        'total'     => $total,
        'page'      => $page,
        'last_page' => (int) ceil($total / $perPage),
    ]]);

} catch (\PDOException $e) {
    error_log('[admin/logs] ' . $e->getMessage());
    jsonError('Erro interno.', 500);
}
