<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../middleware/auth_guard.php';
require_once __DIR__ . '/../middleware/csrf.php';
require_once __DIR__ . '/../utils/response.php';
require_once __DIR__ . '/../utils/logger.php';
require_once __DIR__ . '/../utils/crypto.php';
require_once __DIR__ . '/../utils/admin_search.php';

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

    if ($status === 'ativo')   { $where[] = 'is_active = 1'; }
    if ($status === 'inativo') { $where[] = 'is_active = 0'; }

    $sql = 'WHERE ' . implode(' AND ', $where);

    try {
        $stmtList = $pdo->prepare(
            "SELECT u.id, u.name, u.email, u.cpf, u.is_active, u.mfa_enabled, u.created_at,
                    u.is_admin, u.total_objetos,
                    COALESCE(c.objetos_normais, 0) AS objetos_normais,
                    COALESCE(c.objetos_roubados, 0) AS objetos_roubados,
                    COALESCE(c.objetos_perdidos, 0) AS objetos_perdidos
             FROM   v_admin_users u
             LEFT JOIN v_user_object_counts c ON c.user_id = u.id
             $sql
             ORDER  BY u.created_at DESC"
        );
        foreach ($params as $k => $v) $stmtList->bindValue($k, $v);
        $stmtList->execute();

        $usuarios = array_map(function (array $u): array {
            $u['email'] = decryptField((string) $u['email']);
            $u['cpf']   = decryptField((string) $u['cpf']);
            return $u;
        }, $stmtList->fetchAll());

        if ($busca !== '') {
            $usuarios = array_values(array_filter(
                $usuarios,
                fn(array $r) => rowMatchesTerm($r, $busca, ['name', 'email', 'cpf'])
            ));
        }

        $pag = paginateArray($usuarios, $page, $perPage);

        jsonSuccess(['data' => [
            'usuarios'  => $pag['items'],
            'total'     => $pag['total'],
            'page'      => $page,
            'last_page' => $pag['last_page'],
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
                )->execute([$id, decryptField($u['email'])]);
                $msg = 'Usuário promovido a administrador.';
                break;
            case 'rebaixar':
                $pdo->prepare('DELETE FROM admin_profiles WHERE user_id = ?')->execute([$id]);
                $msg = 'Administrador rebaixado.';
                break;
            case 'excluir':
                $now            = date('Y-m-d H:i:s');
                $anonEmailPlain = "removed_{$id}@deleted.local";
                $anonCpfPlain   = "REMOVED_{$id}";
                $pdo->prepare(
                    "UPDATE users SET deleted_at = :now, name = 'REMOVED',
                     email = :email, email_hash = :eh,
                     cpf = :cpf, cpf_hash = :ch
                     WHERE id = :id"
                )->execute([
                    'now'   => $now,
                    'email' => encryptField($anonEmailPlain),
                    'eh'    => hashField($anonEmailPlain),
                    'cpf'   => encryptField($anonCpfPlain),
                    'ch'    => hashField($anonCpfPlain),
                    'id'    => $id,
                ]);
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
