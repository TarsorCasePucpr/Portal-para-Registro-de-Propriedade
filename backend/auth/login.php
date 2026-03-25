<?php
declare(strict_types=1);

ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Strict');
session_start();

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../middleware/csrf.php';
require_once __DIR__ . '/../middleware/rate_limiter.php';
require_once __DIR__ . '/../utils/hash.php';
require_once __DIR__ . '/../utils/response.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('../../frontend/pages/login.html');
}

if (!validateCsrfToken($_POST['csrf'] ?? '')) {
    $_SESSION['login_erro'] = 'Token de segurança inválido.';
    redirect('../../frontend/pages/login.html');
}

$pdo = getDb();
$ip  = $_SERVER['REMOTE_ADDR'];

if (!checkRateLimit($pdo, $ip, 'login', 5, 15)) {
    $_SESSION['login_erro'] = 'Muitas tentativas de login. Aguarde 15 minutos e tente novamente.';
    redirect('../../frontend/pages/login.html');
}

$email = trim(strtolower($_POST['email'] ?? ''));
$senha = $_POST['senha'] ?? '';

if (empty($email) || empty($senha)) {
    $_SESSION['login_erro'] = 'Preencha e-mail e senha.';
    redirect('../../frontend/pages/login.html');
}

try {
    $stmt = $pdo->prepare(
        "SELECT id, password_hash, is_active, mfa_enabled
         FROM users
         WHERE email = :email AND deleted_at IS NULL"
    );
    $stmt->execute(['email' => $email]);
    $usuario = $stmt->fetch();

    $hashVerificacao = $usuario
        ? $usuario['password_hash']
        : '$2y$13$invalidhashfortimingprotect0000000000000000000000000000000u';

    if (!$usuario || !verifyPassword($senha, $hashVerificacao)) {
        $_SESSION['login_erro'] = 'E-mail ou senha incorretos.';
        redirect('../../frontend/pages/login.html');
    }

    if (!(bool) $usuario['is_active']) {
        $_SESSION['login_erro'] = 'Conta não confirmada. Verifique seu e-mail.';
        redirect('../../frontend/pages/login.html');
    }

    if (needsRehash($usuario['password_hash'])) {
        $novoHash = hashPassword($senha);
        $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?")
            ->execute([$novoHash, $usuario['id']]);
    }

    session_regenerate_id(true);
    $_SESSION['user_agent']    = $_SERVER['HTTP_USER_AGENT'];
    $_SESSION['ip']            = $ip;
    $_SESSION['last_activity'] = time();

    if ((bool) $usuario['mfa_enabled']) {
        $_SESSION['mfa_pending_user_id'] = $usuario['id'];
        $_SESSION['mfa_pending_at']      = time();
        redirect('../../frontend/pages/mfa.html');
    }

    $_SESSION['user_id'] = $usuario['id'];
    redirect('../../frontend/pages/dashboard.html');

} catch (PDOException $e) {
    error_log('login.php: ' . $e->getMessage());
    $_SESSION['login_erro'] = 'Erro interno. Tente novamente mais tarde.';
    redirect('../../frontend/pages/login.html');
}
