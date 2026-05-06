<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../middleware/auth_guard.php';
require_once __DIR__ . '/../middleware/csrf.php';
require_once __DIR__ . '/../utils/response.php';
require_once __DIR__ . '/../utils/logger.php';

requireAdmin();

$pdo      = getDb();
$adminId  = (int) $_SESSION['user_id'];
$method   = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $page    = max(1, (int) ($_GET['page']     ?? 1));
    $perPage = min(50, max(1, (int) ($_GET['per_page'] ?? 20)));
    $busca   = trim($_GET['busca']  ?? '');
    $status  = trim($_GET['status'] ?? 'todos');
    $offset  = ($page - 1) * $perPage;

    $where  = ['deleted_at IS NULL'];
    $params = [];

    if ($busca !== '') {
        $where[]     = '(name LIKE :b OR email LIKE :b OR cpf LIKE :b)';
        $params['b'] = '%' . $busca . '%';
    }
    if ($status === 'ativo')   { $where[] = 'is_active = 1'; }
    if ($status === 'inativo') { $where[] = 'is_active = 0'; }

    $sql = 'WHERE ' . implode(' AND ', $where);

    try {
        $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM v_admin_users $sql");
        $stmtCount->execute($params);
        $total = (int) $stmtCount->fetchColumn();

        $stmtList = $pdo->prepare(
            "SELECT id, name, email, cpf, is_active, mfa_enabled, created_at,
                    is_admin, total_objetos
             FROM   v_admin_users
             $sql
             ORDER  BY created_at DESC
             LIMIT  :limit OFFSET :offset"
        );
        foreach ($params as $k => $v) $stmtList->bindValue($k, $v);
        $stmtList->bindValue(':limit',  $perPage, PDO::PARAM_INT);
        $stmtList->bindValue(':offset', $offset,  PDO::PARAM_INT);
        $stmtList->execute();
        $usuarios = $stmtList->fetchAll();

        jsonSuccess(['data' => [
            'usuarios'  => $usuarios,
            'total'     => $total,
            'page'      => $page,
            'last_page' => (int) ceil($total / $perPage),
        ]]);

    } catch (\PDOException $e) {
        error_log('[admin/usuarios GET] ' . $e->getMessage());
        jsonError('Erro interno.', 500);
    }
}

if ($method === 'PATCH') {
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $csrf = trim((string) ($body['csrf'] ?? ''));

    if (!validateCsrfToken($csrf)) jsonError('Token de segurança inválido.', 403);

    $id   = (int) ($body['id']   ?? 0);
    $acao = trim((string) ($body['acao'] ?? ''));

    if ($id <= 0 || !in_array($acao, ['ativar','desativar','promover','rebaixar','excluir'], true)) {
        jsonError('Parâmetros inválidos.');
    }
    if ($id === $adminId) jsonError('Não é possível modificar a própria conta.');

    try {
        switch ($acao) {
            case 'ativar':
                $pdo->prepare('UPDATE users SET is_active = 1 WHERE id = ?')->execute([$id]);
                $msg = 'Usuário ativado.';
                break;
            case 'desativar':
                $pdo->prepare('UPDATE users SET is_active = 0 WHERE id = ?')->execute([$id]);
                $msg = 'Usuário desativado.';
                break;
            case 'promover':
                $stmt = $pdo->prepare('SELECT email FROM users WHERE id = ?');
                $stmt->execute([$id]);
                $u = $stmt->fetch();
                if (!$u) jsonError('Usuário não encontrado.', 404);
                $pdo->prepare(
                    'INSERT IGNORE INTO admin_profiles (user_id, email) VALUES (?, ?)'
                )->execute([$id, $u['email']]);
                $msg = 'Usuário promovido a administrador.';
                break;
            case 'rebaixar':
                $pdo->prepare('DELETE FROM admin_profiles WHERE user_id = ?')->execute([$id]);
                $msg = 'Administrador rebaixado.';
                break;
            case 'excluir':
                $now = date('Y-m-d H:i:s');
                $pdo->prepare(
                    "UPDATE users SET deleted_at = :now, name = 'REMOVED', email = CONCAT('removed_',:id,'@deleted.local'), cpf = '000.000.000-00' WHERE id = :id2"
                )->execute(['now' => $now, 'id' => $id, 'id2' => $id]);
                $msg = 'Usuário excluído.';
                break;
        }

        logAction($pdo, $adminId, "admin_{$acao}_usuario", 'user', $id, [], 'admin');
        jsonSuccess(['data' => ['mensagem' => $msg]]);

    } catch (\PDOException $e) {
        error_log('[admin/usuarios PATCH] ' . $e->getMessage());
        jsonError('Erro ao executar ação.', 500);
    }
}

jsonError('Método não permitido.', 405);
