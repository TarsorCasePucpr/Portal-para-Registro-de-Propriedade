<?php
declare(strict_types=1);

ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Strict');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Cache-Control: no-store');

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../middleware/csrf.php';
require_once __DIR__ . '/../middleware/auth_guard.php';
require_once __DIR__ . '/../utils/hash.php';
require_once __DIR__ . '/../utils/response.php';

requireAuth();

$userId = (int) $_SESSION['user_id'];
$pdo = getDb();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $stmt = $pdo->prepare(
            'SELECT name, email, cpf, mfa_enabled,
                    DATE_FORMAT(created_at, "%d/%m/%Y") AS membro_desde
             FROM users
             WHERE id = :id AND deleted_at IS NULL'
        );

        $stmt->execute(['id' => $userId]);
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$usuario) {
            jsonError('Usuário não encontrado.', 404);
        }

        $cpf = $usuario['cpf'];
        $usuario['cpf_mascarado'] = substr($cpf, 0, 4) . '***.***-' . substr($cpf, -2);
        unset($usuario['cpf']);

        $usuario['mfa_enabled'] = (bool) $usuario['mfa_enabled'];

        jsonSuccess(['usuario' => $usuario]);

    } catch (PDOException $e) {
        error_log('[meus_dados.php/get] ' . $e->getMessage());
        jsonError('Erro interno.', 500);
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Método não permitido.', 405);
}

if (!validateCsrfToken($_POST['csrf'] ?? '')) {
    jsonError('Token inválido.', 403);
}

$acao = $_POST['acao'] ?? 'atualizar_nome';

if ($acao === 'atualizar_nome') {

    $novoNome = trim(htmlspecialchars($_POST['nome'] ?? '', ENT_QUOTES, 'UTF-8'));

    if ($novoNome === '' || mb_strlen($novoNome) < 3) {
        jsonError('Nome inválido.');
    }

    $pdo->prepare('UPDATE users SET name = :n WHERE id = :id')
        ->execute(['n' => $novoNome, 'id' => $userId]);

    jsonSuccess(['mensagem' => 'Nome atualizado.']);
}

if ($acao === 'atualizar_senha') {

    $senhaAtual = $_POST['senha_atual'] ?? '';
    $novaSenha  = $_POST['nova_senha'] ?? '';
    $confirmar  = $_POST['confirmar_senha'] ?? '';

    if ($novaSenha !== $confirmar) {
        jsonError('Senhas não coincidem.');
    }

    $stmt = $pdo->prepare('SELECT password_hash FROM users WHERE id = :id');
    $stmt->execute(['id' => $userId]);
    $row = $stmt->fetch();

    if (!$row || !password_verify($senhaAtual, $row['password_hash'])) {
        jsonError('Senha atual incorreta.');
    }

    $pdo->prepare('UPDATE users SET password_hash = :h WHERE id = :id')
        ->execute(['h' => hashPassword($novaSenha), 'id' => $userId]);

    jsonSuccess(['mensagem' => 'Senha atualizada.']);
}

jsonError('Ação inválida.');