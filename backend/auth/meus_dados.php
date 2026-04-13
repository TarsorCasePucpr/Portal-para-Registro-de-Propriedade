<?php
declare(strict_types=1);

<<<<<<< HEAD
/**
 * meus_dados.php — Exibição e atualização dos dados do usuário autenticado
 *
 * GET  → retorna dados do perfil
 * POST → atualiza nome e/ou senha
 */

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

$pdo = getDb(); // ← definido aqui, disponível em todo o arquivo

// ════════════════════════════════════════════════════════════════
//  GET — retornar dados do perfil
// ════════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $stmt = $pdo->prepare(
            'SELECT name, email, cpf, mfa_enabled,
                    DATE_FORMAT(created_at, \'%d/%m/%Y\') AS membro_desde
             FROM   users
             WHERE  id = :id AND deleted_at IS NULL'
        );
        $stmt->execute(['id' => $userId]);
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$usuario) {
            jsonError('Usuário não encontrado.', 404);
        }

        // Mascarar CPF para exibição: 000.***.***-00
        $cpf = $usuario['cpf'];
        $usuario['cpf_mascarado'] = substr($cpf, 0, 4) . '***.***-' . substr($cpf, -2);
        unset($usuario['cpf']); // não expor CPF completo na resposta

        $usuario['mfa_enabled'] = (bool) $usuario['mfa_enabled'];

        jsonSuccess(['usuario' => $usuario]);

    } catch (PDOException $e) {
        error_log('[meus_dados.php/get] ' . $e->getMessage());
        jsonError('Erro interno.', 500);
    }
}

// ════════════════════════════════════════════════════════════════
//  POST — atualizar dados do perfil
// ════════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Método não permitido.', 405);
}

if (!validateCsrfToken($_POST['csrf'] ?? '')) {
    jsonError('Token de segurança inválido.', 403);
}

$acao = trim($_POST['acao'] ?? 'atualizar_nome');

// ── Atualizar nome ────────────────────────────────────────────────
if ($acao === 'atualizar_nome') {

    $novoNome = trim(htmlspecialchars($_POST['nome'] ?? '', ENT_QUOTES, 'UTF-8'));

    if ($novoNome === '' || mb_strlen($novoNome) < 3 || mb_strlen($novoNome) > 100) {
        jsonError('Nome inválido (entre 3 e 100 caracteres).');
    }

    try {
        $pdo->prepare(
            'UPDATE users SET name = :nome WHERE id = :id AND deleted_at IS NULL'
        )->execute(['nome' => $novoNome, 'id' => $userId]);

        jsonSuccess(['mensagem' => 'Nome atualizado com sucesso.']);

    } catch (PDOException $e) {
        error_log('[meus_dados.php/nome] ' . $e->getMessage());
        jsonError('Erro interno.', 500);
    }
}

// ── Atualizar senha ───────────────────────────────────────────────
if ($acao === 'atualizar_senha') {

    $senhaAtual = $_POST['senha_atual']    ?? '';
    $novaSenha  = $_POST['nova_senha']     ?? '';
    $confirmar  = $_POST['confirmar_senha'] ?? '';

    if ($senhaAtual === '') {
        jsonError('Informe sua senha atual.');
    }

    $senhaOk =
        mb_strlen($novaSenha) >= 12 &&
        preg_match('/[a-z]/', $novaSenha) &&
        preg_match('/[A-Z]/', $novaSenha) &&
        preg_match('/[0-9]/', $novaSenha) &&
        preg_match('/[@$!%*?&]/', $novaSenha);

    if (!$senhaOk) {
        jsonError('Nova senha fraca. Use mínimo 12 caracteres com maiúscula, minúscula, número e símbolo.');
    }

    if ($novaSenha !== $confirmar) {
        jsonError('As senhas não coincidem.');
    }

    try {
        // Buscar hash atual para verificar senha antiga
        $stmt = $pdo->prepare(
            'SELECT password_hash FROM users WHERE id = :id AND deleted_at IS NULL'
        );
        $stmt->execute(['id' => $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row || !password_verify($senhaAtual, $row['password_hash'])) {
            jsonError('Senha atual incorreta.');
        }

        $novoHash = hashPassword($novaSenha);

        $pdo->prepare(
            'UPDATE users SET password_hash = :hash WHERE id = :id'
        )->execute(['hash' => $novoHash, 'id' => $userId]);

        jsonSuccess(['mensagem' => 'Senha atualizada com sucesso.']);

    } catch (PDOException $e) {
        error_log('[meus_dados.php/senha] ' . $e->getMessage());
        jsonError('Erro interno.', 500);
    }
}

jsonError('Ação inválida.');
=======
session_start();
require_once "../config/db.php";

// verifica se está logado
if (!isset($_SESSION["user_id"])) {
    http_response_code(401);
    echo json_encode(["erro" => "Não autenticado"]);
    exit;
}

$userId = $_SESSION["user_id"];

$sql = "SELECT nome, email FROM users WHERE id = :id LIMIT 1";
$stmt = $pdo->prepare($sql);
$stmt->execute(["id" => $userId]);

$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    echo json_encode(["erro" => "Usuário não encontrado"]);
    exit;
}

header("Content-Type: application/json");
echo json_encode($user);
>>>>>>> origin/develop
