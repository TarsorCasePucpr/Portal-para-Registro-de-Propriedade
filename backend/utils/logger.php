<?php
declare(strict_types=1);

function logAction(
    PDO    $pdo,
    ?int   $userId,
    string $action,
    string $entity    = '',
    ?int   $entityId  = null,
    array  $details   = [],
    string $role      = 'user'
): void {
    try {
        $pdo->prepare(
            'INSERT INTO action_logs (user_id, role, action, entity, entity_id, ip, user_agent, details)
             VALUES (:uid, :role, :action, :entity, :eid, :ip, :ua, :details)'
        )->execute([
            'uid'     => $userId,
            'role'    => $role,
            'action'  => $action,
            'entity'  => $entity !== '' ? $entity : null,
            'eid'     => $entityId,
            'ip'      => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
            'ua'      => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500),
            'details' => !empty($details) ? json_encode($details) : null,
        ]);
    } catch (\PDOException $e) {
        error_log('[logger] ' . $e->getMessage());
    }
}
