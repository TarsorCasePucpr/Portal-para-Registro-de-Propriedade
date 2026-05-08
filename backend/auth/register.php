<?php
declare(strict_types=1);

ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Lax');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../middleware/csrf.php';
require_once __DIR__ . '/../middleware/rate_limiter.php';
require_once __DIR__ . '/../utils/hash.php';
require_once __DIR__ . '/../utils/response.php';
require_once __DIR__ . '/../utils/mailer.php';
require_once __DIR__ . '/../utils/validadores.php';
require_once __DIR__ . '/../utils/logger.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('../../frontend/pages/cadastro-usuario.html');
}

$pdo = getDb();
$ip  = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

if (!checkRateLimit($pdo, $ip, 'registro', 20, 10)) {
    redirect(
        '../../frontend/pages/cadastro-usuario.html?erro=' .
        urlencode('Muitas tentativas. Aguarde alguns minutos.')
    );
}

if (!validateCsrfToken($_POST['csrf'] ?? '')) {
    redirect(
        '../../frontend/pages/cadastro-usuario.html?erro=' .
        urlencode('Token de segurança inválido. Recarregue a página e tente novamente.')
    );
}

$captchaAnswer = trim($_POST['captcha'] ?? '');
$captchaHash   = $_SESSION['captcha_hash'] ?? '';
$captchaSalt   = $_SESSION['captcha_salt'] ?? '';
$captchaAt     = (int) ($_SESSION['captcha_at'] ?? 0);
$captchaTtl    = (int) ($_SESSION['captcha_ttl'] ?? 120);
$captchaTries  = (int) ($_SESSION['captcha_tries'] ?? 0);
$captchaMax    = (int) ($_SESSION['captcha_max_tries'] ?? 3);
$now           = time();

$sessionBind = substr(session_id(), 0, 8);

if ($captchaHash === '' || ($now - $captchaAt) > $captchaTtl) {
    unset($_SESSION['captcha_hash'], $_SESSION['captcha_salt'], $_SESSION['captcha_at'],
          $_SESSION['captcha_ttl'], $_SESSION['captcha_tries'], $_SESSION['captcha_max_tries']);
    redirect('../../frontend/pages/cadastro-usuario.html?erro=' .
        urlencode('Captcha expirado. Recarregue e tente novamente.'));
}

if ($captchaTries >= $captchaMax) {
    unset($_SESSION['captcha_hash'], $_SESSION['captcha_salt'], $_SESSION['captcha_at'],
          $_SESSION['captcha_ttl'], $_SESSION['captcha_tries'], $_SESSION['captcha_max_tries']);
    redirect('../../frontend/pages/cadastro-usuario.html?erro=' .
        urlencode('Muitas tentativas incorretas. Recarregue o captcha.'));
}

$expectedHash = hash('sha256', (string)(int)$captchaAnswer . $captchaSalt . $sessionBind);
if (!hash_equals($captchaHash, $expectedHash)) {
    $_SESSION['captcha_tries'] = $captchaTries + 1;
    redirect('../../frontend/pages/cadastro-usuario.html?erro=' .
        urlencode('Resposta do desafio incorreta. Tente novamente.'));
}

unset($_SESSION['captcha_hash'], $_SESSION['captcha_salt'], $_SESSION['captcha_at'],
      $_SESSION['captcha_ttl'], $_SESSION['captcha_tries'], $_SESSION['captcha_max_tries']);

$nome      = trim(htmlspecialchars($_POST['nome']        ?? '', ENT_QUOTES, 'UTF-8'));
$email     = trim(strtolower($_POST['email']             ?? ''));
$cpf       = trim($_POST['cpf']                          ?? '');
$senha     = $_POST['senha']                             ?? '';
$confirmar = $_POST['confirmar_senha']                   ?? '';
$lgpd      = ($_POST['aceite_lgpd']                     ?? '0') === '1';
$userAgent = mb_substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500);

$erros = [];

if ($nome === '' || mb_strlen($nome) < 3 || mb_strlen($nome) > 100) {
    $erros[] = 'Nome inválido (entre 3 e 100 caracteres).';
}

if (!validarEmail($email)) {
    $erros[] = 'E-mail inválido.';
}

if (!validarCPF($cpf)) {
    $erros[] = 'CPF inválido. Use o formato 000.000.000-00.';
}

if (!validarSenhaForte($senha)) {
    $erros[] = 'Senha fraca. Use mínimo 12 caracteres com maiúscula, minúscula, número e símbolo (@$!%*?&).';
}

if ($senha !== $confirmar) {
    $erros[] = 'As senhas não coincidem.';
}

if (!$lgpd) {
    $erros[] = 'Você precisa aceitar os termos da LGPD para criar sua conta.';
}

if (!empty($erros)) {
    redirect(
        '../../frontend/pages/cadastro-usuario.html?erro=' .
        urlencode(implode(' ', $erros))
    );
}

try {
    $stmt = $pdo->prepare(
        'SELECT id FROM users WHERE (email = :email OR cpf = :cpf) AND deleted_at IS NULL'
    );
    $stmt->execute(['email' => $email, 'cpf' => $cpf]);

    if ($stmt->fetch()) {
        redirect(
            '../../frontend/pages/cadastro-usuario.html?erro=' .
            urlencode('Não foi possível criar a conta com os dados informados. Verifique e tente novamente.')
        );
    }

    $hashSenha = hashPassword($senha);

    $stmt = $pdo->prepare(
        'INSERT INTO users (name, email, cpf, password_hash, is_active)
         VALUES (:nome, :email, :cpf, :hash, 0)'
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
    $expira    = date('Y-m-d H:i:s', time() + 86400);

    $codeChars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $shortCode = '';
    for ($i = 0; $i < 6; $i++) {
        $shortCode .= $codeChars[random_int(0, strlen($codeChars) - 1)];
    }

    $pdo->prepare(
        "INSERT INTO tokens (user_id, token_hash, type, short_code, expires_at)
         VALUES (:uid, :hash, 'confirm', :code, :exp)"
    )->execute([
        'uid'  => $userId,
        'hash' => $tokenHash,
        'code' => $shortCode,
        'exp'  => $expira,
    ]);

    $pdo->prepare(
        "INSERT INTO lgpd_consent (user_id, ip, policy_version, user_agent)
         VALUES (:uid, :ip, '1.0', :ua)"
    )->execute([
        'uid' => $userId,
        'ip'  => $ip,
        'ua'  => $userAgent,
    ]);
    logAction($pdo, $userId, 'user_registered', 'user', $userId, ['ip' => $ip], 'user');

    $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
             . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
    $linkConfirmacao = $baseUrl . '/backend/auth/confirm.php?token=' . urlencode($tokenRaw);

    try {
        enviarEmail(
            destinatario: $email,
            nome:         $nome,
            assunto:      'SNGuard — Confirme seu e-mail',
            corpo:        "Olá, {$nome}!\n\n" .
                          "Clique no link abaixo para ativar sua conta (válido por 24 horas):\n\n" .
                          "{$linkConfirmacao}\n\n" .
                          "Se o link acima não funcionar no seu e-mail, acesse a página de\n" .
                          "confirmação e informe seu e-mail junto com o código abaixo:\n\n" .
                          "Código de confirmação: {$shortCode}\n\n" .
                          "Se você não criou esta conta, ignore esta mensagem.\n\n" .
                          "— Equipe SNGuard"
        );
    } catch (Throwable $e) {
        error_log('[register.php] Falha ao enviar email para ' . $email . ': ' . $e->getMessage());
    }

} catch (PDOException $e) {
    error_log('[register.php] DB error: ' . $e->getMessage());
    redirect(
        '../../frontend/pages/cadastro-usuario.html?erro=' .
        urlencode('Erro interno. Tente novamente mais tarde.')
    );
}

redirect('../../frontend/pages/confirmacao-cadastro.html');
