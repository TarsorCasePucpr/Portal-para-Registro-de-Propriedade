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
    $where[]     = '(l.action LIKE :b OR l.entity LIKE :b OR l.ip LIKE :b OR u.email LIKE :b)';
    $params['b'] = '%' . $busca . '%';
}
if (in_array($role, ['user','admin'], true)) {
    $where[]         = 'l.role = :role';
    $params['role']  = $role;
}

$sql = 'WHERE ' . implode(' AND ', $where);

try {
    $stmtCount = $pdo->prepare(
        "SELECT COUNT(*) FROM action_logs l LEFT JOIN users u ON u.id = l.user_id $sql"
    );
    $stmtCount->execute($params);
    $total = (int) $stmtCount->fetchColumn();

    $stmtList = $pdo->prepare(
        "SELECT l.id, l.role, l.action, l.entity, l.entity_id, l.ip, l.created_at,
                l.details,
                COALESCE(u.email, '[removido]') AS user_email,
                COALESCE(u.name,  '[removido]') AS user_name
         FROM   action_logs l
         LEFT JOIN users u ON u.id = l.user_id
         $sql
         ORDER  BY l.created_at DESC
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
