<?php
declare(strict_types=1);

/**
 * me.php — Retorna dados básicos do usuário autenticado
 *
 * GET /backend/auth/me.php
 *
 * Resposta JSON:
 *   { "success": true, "name": "...", "email": "...", "mfa_enabled": false }
 *   { "success": false, "error": "Não autenticado." }
 *
 * Usado pelo dashboard para carregar saudação e estado do 2FA.
 */

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
        'SELECT name, email, mfa_enabled
         FROM   users
         WHERE  id = :id AND deleted_at IS NULL AND is_active = 1'
    );
    $stmt->execute(['id' => $userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        jsonError('Usuário não encontrado.', 404);
    }

    jsonSuccess([
        'name'        => $user['name'],
        'email'       => $user['email'],
        'mfa_enabled' => (bool) $user['mfa_enabled'],
    ]);

} catch (PDOException $e) {
    error_log('[me.php] ' . $e->getMessage());
    jsonError('Erro interno.', 500);
}
