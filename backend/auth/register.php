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
require_once __DIR__ . '/../utils/crypto.php';
require_once __DIR__ . '/../utils/turnstile.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('../../frontend/pages/cadastro-usuario.html');
}

$ctype  = strtolower(trim(explode(';', $_SERVER['CONTENT_TYPE'] ?? '')[0]));
$hybrid = ($ctype === 'application/json');
$fields = [];
$hostTag = hybridHostTag();

if ($hybrid) {
    $raw = file_get_contents('php://input') ?: '';
    $body = json_decode($raw, true);
    if (!is_array($body) || empty($body['encKey']) || empty($body['iv']) || empty($body['cipher'])) {
        error_log("{$hostTag}>register híbrido: payload inválido.");
        jsonError('Payload híbrido inválido.', 400);
    }
    try {
        $plain = hybridDecrypt($body['encKey'], $body['iv'], $body['cipher']);
    } catch (\Throwable $e) {
        error_log("{$hostTag}>register híbrido: falha ao descifrar: " . $e->getMessage());
        jsonError('Falha ao descifrar payload.', 400);
    }
    $fields = json_decode($plain, true);
    if (!is_array($fields)) {
        error_log("{$hostTag}>register híbrido: payload descifrado não é JSON.");
        jsonError('Payload descifrado inválido.', 400);
    }
    $preview = [
        'nome'        => $fields['nome']  ?? '',
        'email'       => $fields['email'] ?? '',
        'cpf'         => $fields['cpf']   ?? '',
        'senha'       => isset($fields['senha']) ? '[' . strlen($fields['senha']) . ' chars]' : '',
        'aceite_lgpd' => $fields['aceite_lgpd'] ?? '0',
    ];
    error_log("{$hostTag}>register híbrido descifrado: " . json_encode($preview, JSON_UNESCAPED_UNICODE));
} else {
    $fields = $_POST;
}

$pdo = getDb();
$ip  = getClientIp();

$failRedirect = function (string $msg, int $code = 400) use ($hybrid) {
    if ($hybrid) jsonError($msg, $code);
    redirect('../../frontend/pages/cadastro-usuario.html?erro=' . urlencode($msg));
};

if (!checkRateLimit($pdo, $ip, 'registro', 20, 10)) {
    $failRedirect('Muitas tentativas. Aguarde alguns minutos.', 429);
}

if (!validateCsrfToken($fields['csrf'] ?? '')) {
    $failRedirect('Token de segurança inválido. Recarregue a página e tente novamente.');
}

$turnstileToken = trim((string) ($fields['cf-turnstile-response'] ?? ''));
if (!verifyTurnstile($turnstileToken, $ip)) {
    $failRedirect('Verificação anti-bot falhou. Recarregue a página e tente novamente.');
}

$nome      = trim(htmlspecialchars($fields['nome']        ?? '', ENT_QUOTES, 'UTF-8'));
$email     = trim(strtolower($fields['email']             ?? ''));
$cpf       = trim($fields['cpf']                          ?? '');
$senha     = $fields['senha']                             ?? '';
$confirmar = $fields['confirmar_senha']                   ?? '';
$lgpd      = ($fields['aceite_lgpd']                     ?? '0') === '1';
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
    $failRedirect(implode(' ', $erros));
}

try {
    $emailHash = hashField($email);
    $cpfHash   = hashField($cpf);
    $emailEnc  = encryptField($email);
    $cpfEnc    = encryptField($cpf);

    $stmt = $pdo->prepare(
        'SELECT id FROM users WHERE (email_hash = :eh OR cpf_hash = :ch) AND deleted_at IS NULL'
    );
    $stmt->execute(['eh' => $emailHash, 'ch' => $cpfHash]);

    if ($stmt->fetch()) {
        $failRedirect('Não foi possível criar a conta com os dados informados. Verifique e tente novamente.');
    }

    $hashSenha = hashPassword($senha);
    $nomeEnc   = encryptField($nome);

    $stmt = $pdo->prepare(
        'INSERT INTO users (name, email, email_hash, cpf, cpf_hash, password_hash, is_active)
         VALUES (:nome, :email, :eh, :cpf, :ch, :hash, 0)'
    );
    $stmt->execute([
        'nome'  => $nomeEnc,
        'email' => $emailEnc,
        'eh'    => $emailHash,
        'cpf'   => $cpfEnc,
        'ch'    => $cpfHash,
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
    logAction($pdo, $userId, 'user_registered', 'user', $userId, ['ip' => $ip, 'hybrid' => $hybrid], 'user');

    try {
        $sel = $pdo->prepare('SELECT name, email, cpf FROM users WHERE id = :id');
        $sel->execute(['id' => $userId]);
        $row = $sel->fetch();
        if ($row) {
            error_log("{$hostTag}>cadastro recuperado do BD (id={$userId}) "
                . "nome=" . decryptField($row['name'])
                . " email=" . decryptField($row['email'])
                . " cpf=" . decryptField($row['cpf']));
        }
    } catch (\Throwable $e) {
        error_log("{$hostTag}>falha ao recuperar/descifrar cadastro: " . $e->getMessage());
    }

    $baseUrl = rtrim(
        getenv('APP_URL') ?:
        ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
        . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost')),
        '/'
    );
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
    $failRedirect('Erro interno. Tente novamente mais tarde.', 500);
}

if ($hybrid) {
    jsonSuccess(['redirect' => '../../frontend/pages/confirmacao-cadastro.html']);
}
redirect('../../frontend/pages/confirmacao-cadastro.html');
