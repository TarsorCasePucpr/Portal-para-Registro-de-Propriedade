<?php
declare(strict_types=1);

/**
 * register.php — Cadastro de novo usuário
 */

ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Strict');

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

// ── Helper ───────────────────────────────────────────────────────
function redirecionar(string $url): never {
    header('Location: ' . $url);
    exit;
}

// ── Apenas POST ──────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirecionar('../../frontend/pages/cadastro-usuario.html');
}

$pdo = getDb();
$ip  = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

// ── Rate limit ───────────────────────────────────────────────────
if (!checkRateLimit($pdo, $ip, 'registro', 5, 60)) {
    redirecionar('../../frontend/pages/cadastro-usuario.html?erro=' .
        urlencode('Muitas tentativas. Aguarde alguns minutos.'));
}

// ── CSRF ─────────────────────────────────────────────────────────
if (!validateCsrfToken($_POST['csrf'] ?? '')) {
    redirecionar('../../frontend/pages/cadastro-usuario.html?erro=' .
        urlencode('Token de segurança inválido.'));
}

// ── Inputs ───────────────────────────────────────────────────────
$nome      = trim(htmlspecialchars($_POST['nome'] ?? '', ENT_QUOTES, 'UTF-8'));
$email     = trim(strtolower($_POST['email'] ?? ''));
$cpf       = trim($_POST['cpf'] ?? '');
$senha     = $_POST['senha'] ?? '';
$confirmar = $_POST['confirmar_senha'] ?? '';
$lgpd      = ($_POST['aceite_lgpd'] ?? '0') === '1';
$userAgent = mb_substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500);

// ── Validação ────────────────────────────────────────────────────
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
    $erros[] = 'Você precisa aceitar os termos da LGPD.';
}

if (!empty($erros)) {
    redirecionar('../../frontend/pages/cadastro-usuario.html?erro=' .
        urlencode(implode(' ', $erros)));
}

// ── Persistência ─────────────────────────────────────────────────
try {
    // Verificar duplicidade
    $stmt = $pdo->prepare(
        'SELECT id FROM users WHERE (email = :email OR cpf = :cpf) AND deleted_at IS NULL'
    );
    $stmt->execute(['email' => $email, 'cpf' => $cpf]);

    if ($stmt->fetch()) {
        redirecionar('../../frontend/pages/cadastro-usuario.html?erro=' .
            urlencode('Não foi possível criar a conta. Verifique os dados.'));
    }

    // Inserir usuário
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

    // Token de confirmação
    $tokenRaw  = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $tokenRaw);
    $expira    = date('Y-m-d H:i:s', time() + 86400);

    $pdo->prepare(
        "INSERT INTO tokens (user_id, token_hash, type, expires_at)
         VALUES (:uid, :hash, 'confirm', :exp)"
    )->execute([
        'uid'  => $userId,
        'hash' => $tokenHash,
        'exp'  => $expira,
    ]);

    // LGPD
    $pdo->prepare(
        "INSERT INTO lgpd_consent (user_id, ip, policy_version, user_agent)
         VALUES (:uid, :ip, '1.0', :ua)"
    )->execute([
        'uid' => $userId,
        'ip'  => $ip,
        'ua'  => $userAgent,
    ]);

    // Gerar URL base
    $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
             . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');

    // Link de confirmação
    $linkConfirmacao = $baseUrl . '/backend/auth/confirm.php?token=' . urlencode($tokenRaw);

    // Enviar e-mail
    enviarEmail(
        destinatario: $email,
        nome:         $nome,
        assunto:      'SNGuard — Confirme seu e-mail',
        corpo:        "Olá, {$nome}!\n\n" .
                      "Clique no link abaixo para ativar sua conta (válido por 24 horas):\n\n" .
                      "{$linkConfirmacao}\n\n" .
                      "Se você não criou esta conta, ignore esta mensagem.\n\n" .
                      "— Equipe SNGuard"
    );

} catch (PDOException $e) {
    error_log('[register.php] DB error: ' . $e->getMessage());
    redirecionar('../../frontend/pages/cadastro-usuario.html?erro=' .
        urlencode('Erro interno. Tente novamente mais tarde.'));
} catch (Throwable $e) {
    error_log('[register.php] Error: ' . $e->getMessage());
    redirecionar('../../frontend/pages/cadastro-usuario.html?erro=' .
        urlencode('Erro inesperado.'));
}

// Sucesso
redirecionar('../../frontend/pages/confirmacao-cadastro.html');