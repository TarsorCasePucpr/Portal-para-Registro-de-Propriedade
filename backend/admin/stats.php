<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../middleware/auth_guard.php';
require_once __DIR__ . '/../utils/response.php';

requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') jsonError('Método não permitido.', 405);

$pdo = getDb();

try {
    $totais = $pdo->query(
        "SELECT
            (SELECT COUNT(*) FROM users WHERE deleted_at IS NULL AND is_active = 1)                AS usuarios_ativos,
            (SELECT COUNT(*) FROM users WHERE deleted_at IS NULL)                                  AS total_usuarios,
            (SELECT COUNT(*) FROM objects WHERE deleted_at IS NULL)                                AS total_objetos,
            (SELECT COUNT(*) FROM objects WHERE status = 'roubado' AND deleted_at IS NULL)         AS objetos_roubados,
            (SELECT COUNT(*) FROM objects WHERE status = 'perdido'  AND deleted_at IS NULL)        AS objetos_perdidos,
            (SELECT COUNT(*) FROM contact_messages WHERE lida = 0)                                 AS mensagens_nao_lidas,
            (SELECT COALESCE(SUM(total_requests - total_purgadas), 0) FROM v_lgpd_deletion_summary) AS exclusoes_pendentes,
            (SELECT COUNT(*) FROM admin_profiles)                                                  AS total_admins"
    )->fetch();

    jsonSuccess(['data' => ['totais' => $totais]]);

} catch (\PDOException $e) {
    error_log('[admin/stats] ' . $e->getMessage());
    jsonError('Erro interno.', 500);
}
