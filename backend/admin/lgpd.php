<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../middleware/auth_guard.php';
require_once __DIR__ . '/../utils/response.php';
require_once __DIR__ . '/../utils/crypto.php';

requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') jsonError('Método não permitido.', 405);

$pdo = getAdminDb();

try {
    $rows = $pdo->query(
        "SELECT
            r.id,
            r.user_id,
            r.type,
            r.ip,
            r.requested_at,
            r.purge_after,
            r.purged_at,
            u.name        AS user_name,
            u.email       AS user_email,
            u.cpf         AS user_cpf,
            u.deleted_at  AS user_deleted_at
         FROM lgpd_deletion_requests r
         JOIN users u ON u.id = r.user_id
         ORDER BY r.requested_at DESC"
    )->fetchAll();

    $rows = array_map(function (array $r): array {
        $r['user_name']  = decryptField((string) $r['user_name']);
        $r['user_email'] = decryptField((string) $r['user_email']);
        $r['user_cpf']   = decryptField((string) $r['user_cpf']);
        return $r;
    }, $rows);

    jsonSuccess(['data' => ['solicitacoes' => $rows]]);

} catch (\PDOException $e) {
    error_log('[admin/lgpd] ' . $e->getMessage());
    jsonError('Erro interno.', 500);
}
