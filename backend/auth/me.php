<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../middleware/auth_guard.php';
require_once __DIR__ . '/../utils/response.php';

requireAuth();

$userId = (int) $_SESSION['user_id'];
$pdo    = getDb();

try {
    $stmt = $pdo->prepare(
        'SELECT u.name, u.email, u.mfa_enabled,
                COALESCE(via.is_admin, 0) AS is_admin
         FROM   users u
         LEFT JOIN v_user_is_admin via ON via.user_id = u.id
         WHERE  u.id = :id AND u.deleted_at IS NULL AND u.is_active = 1'
    );
    $stmt->execute(['id' => $userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        jsonError('Usuário não encontrado.', 404);
    }

    jsonSuccess(['data' => [
        'name'        => $user['name'],
        'email'       => $user['email'],
        'mfa_enabled' => (bool) $user['mfa_enabled'],
        'is_admin'    => (bool) $user['is_admin'],
    ]]);

} catch (PDOException $e) {
    error_log('[me.php] ' . $e->getMessage());
    jsonError('Erro interno.', 500);
}
