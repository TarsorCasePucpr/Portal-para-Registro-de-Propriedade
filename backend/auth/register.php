<?php
declare(strict_types=1);

ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Strict');
session_start();

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../middleware/csrf.php';
require_once __DIR__ . '/../utils/hash.php';
require_once __DIR__ . '/../utils/response.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('../../frontend/pages/cadastro-usuario.html');
}

if (!validateCsrfToken($_POST['csrf'] ?? '')) {
    redirect('../../frontend/pages/cadastro-usuario.html?erro=' .
        urlencode('Token de segurança inválido.'));
}

$nome  = trim(htmlspecialchars($_POST['nome']  ?? '', ENT_QUOTES, 'UTF-8'));
$email = trim(strtolower($_POST['email'] ?? ''));
$cpf   = trim($_POST['cpf']   ?? '');
$senha = $_POST['senha']      ?? '';
$lgpd  = $_POST['aceite_lgpd'] ?? '0';

$erros = [];

if (empty($nome) || mb_strlen($nome) > 100) {
    $erros[] = 'Nome inválido.';
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 255) {
    $erros[] = 'E-mail inválido.';
}

if (!preg_match('/^\d{3}\.\d{3}\.\d{3}-\d{2}$/', $cpf)) {
    $erros[] = 'CPF inválido. Use o formato 000.000.000-00.';
}

if (
    mb_strlen($senha) < 12 ||
    !preg_match('/[a-z]/', $senha) ||
    !preg_match('/[A-Z]/', $senha) ||
    !preg_match('/[0-9]/', $senha) ||
    !preg_match('/[@$!%*?&]/', $senha)
) {
    $erros[] = 'Senha fraca. Use mínimo 12 caracteres com maiúscula, minúscula, número e símbolo.';
}

if ($lgpd !== '1') {
    $erros[] = 'Você precisa aceitar os termos da LGPD para criar sua conta.';
}

if (!empty($erros)) {
    redirect('../../frontend/pages/cadastro-usuario.html?erro=' .
        urlencode(implode(' ', $erros)));
}

try {
    $pdo = getDb();

    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = :email OR cpf = :cpf");
    $stmt->execute(['email' => $email, 'cpf' => $cpf]);

    if ($stmt->fetch()) {
        redirect('../../frontend/pages/cadastro-usuario.html?erro=' .
            urlencode('Não foi possível criar a conta. Verifique seus dados.'));
    }

    $hashSenha = hashPassword($senha);

    $stmt = $pdo->prepare(
        "INSERT INTO users (name, email, cpf, password_hash, is_active)
         VALUES (:nome, :email, :cpf, :hash, 0)"
    );
    $stmt->execute([
        'nome'  => $nome,
        'email' => $email,
        'cpf'   => $cpf,
        'hash'  => $hashSenha,
    ]);
    $userId = (int) $pdo->lastInsertId();

    $tokenRaw  = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $tokenRaw);
    $expira    = date('Y-m-d H:i:s', time() + 3600);

    $pdo->prepare(
        "INSERT INTO tokens (user_id, token_hash, type, expires_at)
         VALUES (:uid, :hash, 'confirm', :exp)"
    )->execute(['uid' => $userId, 'hash' => $tokenHash, 'exp' => $expira]);

    $pdo->prepare(
        "INSERT INTO lgpd_consent (user_id, ip, policy_version)
         VALUES (:uid, :ip, '1.0')"
    )->execute(['uid' => $userId, 'ip' => $_SERVER['REMOTE_ADDR']]);

    redirect('../../frontend/pages/confirmacao-cadastro.html');

} catch (PDOException $e) {
    error_log('register.php: ' . $e->getMessage());
    redirect('../../frontend/pages/cadastro-usuario.html?erro=' .
        urlencode('Erro interno. Tente novamente mais tarde.'));
}
