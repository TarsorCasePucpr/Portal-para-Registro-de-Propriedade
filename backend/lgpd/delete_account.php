<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../utils/hash.php';
require_once __DIR__ . '/../../utils/response.php';
require_once __DIR__ . '/../../middleware/csrf.php';

startSessionSafe();

if (empty($_SESSION['user_id'])) {
    jsonError('Sessão inválida. Faça login novamente.', 401);
}

if (isset($_SESSION['last_activity']) && time() - $_SESSION['last_activity'] > 1800) {
    session_unset();
    session_destroy();
    jsonError('Sessão expirada. Faça login novamente.', 401);
}

$_SESSION['last_activity'] = time();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Método não permitido.', 405);
}

$csrfProvided = trim($_POST['csrf'] ?? '');
if (!validateCsrfToken($csrfProvided)) {
    jsonError('Token de segurança inválido.', 403);
}

$type     = trim($_POST['type'] ?? '');
$password = $_POST['password'] ?? '';

if (!in_array($type, ['partial', 'total'], true)) {
    jsonError('Tipo de exclusão inválido.');
}

if ($password === '') {
    jsonError('Senha obrigatória para confirmar a exclusão.');
}

$userId = (int) $_SESSION['user_id'];
$ip     = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

try {
    $pdo  = getDb();
    $stmt = $pdo->prepare(
        'SELECT id, password_hash, deleted_at FROM users WHERE id = :id'
    );
    $stmt->execute(['id' => $userId]);
    $user = $stmt->fetch();
} catch (\PDOException $e) {
    error_log('[delete_account] DB error fetch: ' . $e->getMessage());
    jsonError('Erro interno. Tente novamente.', 500);
}

if (!$user || $user['deleted_at'] !== null) {
    session_unset();
    session_destroy();
    jsonError('Conta não encontrada.', 404);
}

if (!verifyPassword($password, $user['password_hash'])) {
    jsonError('Senha incorreta. Verifique e tente novamente.');
}

try {
    $pdo->beginTransaction();

    $now       = date('Y-m-d H:i:s');
    $purgeDate = date('Y-m-d H:i:s', strtotime('+30 days'));
    $anonEmail = "removed_{$userId}@deleted.local";
    $anonHash  = hashPassword(bin2hex(random_bytes(32)));

    $pdo->prepare(
        'UPDATE users
         SET name = :name, email = :email, cpf = :cpf,
             password_hash = :hash, mfa_secret = NULL,
             updated_at = :now
         WHERE id = :id'
    )->execute([
        'name'  => 'REMOVED',
        'email' => $anonEmail,
        'cpf'   => '000.000.000-00',
        'hash'  => $anonHash,
        'now'   => $now,
        'id'    => $userId,
    ]);

    $pdo->prepare(
        'UPDATE users SET deleted_at = :now WHERE id = :id'
    )->execute(['now' => $now, 'id' => $userId]);

    $pdo->prepare(
        'DELETE FROM tokens WHERE user_id = :id'
    )->execute(['id' => $userId]);

    if ($type === 'total') {
        $tables = $pdo->query("SHOW TABLES LIKE 'objects'")->fetchAll();
        if (!empty($tables)) {
            $pdo->prepare(
                'UPDATE objects SET deleted_at = :now
                 WHERE user_id = :id AND deleted_at IS NULL'
            )->execute(['now' => $now, 'id' => $userId]);
        }
    }

    $pdo->prepare(
        'INSERT INTO lgpd_deletion_requests (user_id, type, ip, purge_after)
         VALUES (:uid, :type, :ip, :purge)'
    )->execute([
        'uid'   => $userId,
        'type'  => $type,
        'ip'    => $ip,
        'purge' => $purgeDate,
    ]);

    $pdo->commit();
} catch (\PDOException $e) {
    $pdo->rollBack();
    error_log('[delete_account] DB error delete: ' . $e->getMessage());
    jsonError('Erro ao processar exclusão. Tente novamente.', 500);
}

session_unset();
session_destroy();
setcookie(session_name(), '', time() - 3600, '/', '', true, true);

jsonSuccess(['message' => 'Conta excluída com sucesso.']);
