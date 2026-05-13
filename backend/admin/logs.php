<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../middleware/auth_guard.php';
require_once __DIR__ . '/../utils/response.php';
require_once __DIR__ . '/../utils/crypto.php';
require_once __DIR__ . '/../utils/admin_search.php';

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

if (in_array($role, ['user','admin'], true)) {
    $where[]         = 'l.role = :role';
    $params['role']  = $role;
}

$sql = 'WHERE ' . implode(' AND ', $where);

try {
    $stmtList = $pdo->prepare(
        "SELECT id, role, action, entity, entity_id, ip, created_at, details,
                user_email, user_name
         FROM   v_admin_action_logs l
         $sql
         ORDER  BY created_at DESC
         LIMIT 2000"
    );
    foreach ($params as $k => $v) $stmtList->bindValue($k, $v);
    $stmtList->execute();

    $logs = array_map(function (array $r): array {
        if (isset($r['user_email']) && $r['user_email'] !== '[removido]') {
            $r['user_email'] = decryptField((string) $r['user_email']);
        }
        return $r;
    }, $stmtList->fetchAll());

    if ($busca !== '') {
        $logs = array_values(array_filter(
            $logs,
            fn(array $r) => rowMatchesTerm($r, $busca, ['action', 'entity', 'ip', 'user_email', 'user_name', 'details'])
        ));
    }

    $pag = paginateArray($logs, $page, $perPage);

    jsonSuccess(['data' => [
        'logs'      => $pag['items'],
        'total'     => $pag['total'],
        'page'      => $page,
        'last_page' => $pag['last_page'],
    ]]);

} catch (\PDOException $e) {
    error_log('[admin/logs] ' . $e->getMessage());
    jsonError('Erro interno.', 500);
}
