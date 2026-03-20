<?php
declare(strict_types=1);

ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Strict');
session_start();

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../middleware/csrf.php';
require_once __DIR__ . '/../utils/hash.php';
require_once __DIR__ . '/../utils/response.php';

// Aceita apenas POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('../../frontend/pages/cadastro-usuario.html');
}

// 1. Validar token CSRF
validateCsrfToken();

// 2. Sanitizar entradas
$nome  = trim(htmlspecialchars($_POST['nome']  ?? '', ENT_QUOTES, 'UTF-8'));
$email = trim(strtolower($_POST['email'] ?? ''));
$cpf   = trim($_POST['cpf']   ?? '');
$senha = $_POST['senha']      ?? '';
$lgpd  = $_POST['aceite_lgpd'] ?? '0';

// 3. Validar campos
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
    $_SESSION['cadastro_erros'] = $erros;
    redirect('../../frontend/pages/cadastro-usuario.html');
}

// 4. Operações no banco de dados
try {
    $pdo = getDb();

    // Verificar se e-mail ou CPF já estão cadastrados
    // Resposta genérica: nunca revelar qual campo já existe (OWASP A07)
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = :email OR cpf = :cpf");
    $stmt->execute(['email' => $email, 'cpf' => $cpf]);

    if ($stmt->fetch()) {
        $_SESSION['cadastro_erros'] = ['Não foi possível criar a conta. Verifique seus dados.'];
        redirect('../../frontend/pages/cadastro-usuario.html');
    }

    // 5. Hash da senha com bcrypt cost 13 — NUNCA texto plano
    $hashSenha = hashPassword($senha);

    // 6. Inserir usuário (inativo até confirmar e-mail)
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

    // 7. Gerar token de confirmação de e-mail
    $tokenRaw  = bin2hex(random_bytes(32));         // Enviado por e-mail
    $tokenHash = hash('sha256', $tokenRaw);          // Armazenado no banco
    $expira    = date('Y-m-d H:i:s', time() + 3600); // Válido por 1 hora

    $pdo->prepare(
        "INSERT INTO tokens (user_id, token_hash, type, expires_at)
         VALUES (:uid, :hash, 'confirm', :exp)"
    )->execute(['uid' => $userId, 'hash' => $tokenHash, 'exp' => $expira]);

    // 8. Registrar aceite LGPD (Art. 7, I) com timestamp e IP
    $pdo->prepare(
        "INSERT INTO lgpd_consent (user_id, ip, policy_version)
         VALUES (:uid, :ip, '1.0')"
    )->execute(['uid' => $userId, 'ip' => $_SERVER['REMOTE_ADDR']]);

    // 9. TODO: Enviar e-mail de confirmação com $tokenRaw (requer mailer.php configurado)
    // Por enquanto em desenvolvimento, redireciona diretamente
    $_SESSION['cadastro_sucesso'] = 'Conta criada! Verifique seu e-mail para confirmar.';
    redirect('../../frontend/pages/confirmacao.html');

} catch (PDOException $e) {
    error_log('register.php: ' . $e->getMessage());
    $_SESSION['cadastro_erros'] = ['Erro interno. Tente novamente mais tarde.'];
    redirect('../../frontend/pages/cadastro-usuario.html');
}
