<?php
declare(strict_types=1);

/**
 * register.php — Cadastro de novo usuário
 *
 * POST /backend/auth/register.php
 * Campos: csrf, nome, email, cpf, senha, confirmar_senha, aceite_lgpd
 *
 * Fluxo:
 *   1. Validar método POST
 *   2. Validar token CSRF
 *   3. Sanitizar e validar todos os campos
 *   4. Verificar unicidade de e-mail e CPF (mensagem genérica — não revelar qual)
 *   5. Gerar hash bcrypt da senha
 *   6. Inserir usuário (is_active = 0 até confirmar e-mail)
 *   7. Gerar token de confirmação e inserir na tabela tokens
 *   8. Registrar consentimento LGPD
 *   9. Enviar e-mail de confirmação
 *  10. Redirecionar para página de aguardo
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

// ── Helpers locais ────────────────────────────────────────────────
function redirecionar(string $url): never
{
    header('Location: ' . $url);
    exit;
}

// ── Apenas POST ──────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirecionar('../../frontend/pages/cadastro-usuario.html');
}

// ── Rate limit: máx. 5 cadastros / hora por IP ───────────────────
$pdo = getDb();
$ip  = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

if (!checkRateLimit($pdo, $ip, 'registro', 5, 60)) {
    redirecionar(
        '../../frontend/pages/cadastro-usuario.html?erro=' .
        urlencode('Muitas tentativas. Aguarde alguns minutos.')
    );
}

// ── CSRF ─────────────────────────────────────────────────────────
if (!validateCsrfToken($_POST['csrf'] ?? '')) {
    redirecionar(
        '../../frontend/pages/cadastro-usuario.html?erro=' .
        urlencode('Token de segurança inválido. Recarregue a página e tente novamente.')
    );
}

// ── Coletar e sanitizar entradas ─────────────────────────────────
$nome      = trim(htmlspecialchars($_POST['nome']            ?? '', ENT_QUOTES, 'UTF-8'));
$email     = trim(strtolower($_POST['email']                 ?? ''));
$cpf       = trim($_POST['cpf']                              ?? '');
$senha     = $_POST['senha']                                 ?? '';
$confirmar = $_POST['confirmar_senha']                       ?? '';
$lgpd      = ($_POST['aceite_lgpd']                         ?? '0') === '1';
$userAgent = mb_substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500);

// ── Validação ────────────────────────────────────────────────────
$erros = [];

if ($nome === '' || mb_strlen($nome) < 3 || mb_strlen($nome) > 100) {
    $erros[] = 'Nome inválido (entre 3 e 100 caracteres).';
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 255) {
    $erros[] = 'E-mail inválido.';
}

if (!preg_match('/^\d{3}\.\d{3}\.\d{3}-\d{2}$/', $cpf)) {
    $erros[] = 'CPF inválido. Use o formato 000.000.000-00.';
}

$senhaOk =
    mb_strlen($senha) >= 12 &&
    preg_match('/[a-z]/', $senha) &&
    preg_match('/[A-Z]/', $senha) &&
    preg_match('/[0-9]/', $senha) &&
    preg_match('/[@$!%*?&]/', $senha);

if (!$senhaOk) {
    $erros[] = 'Senha fraca. Use mínimo 12 caracteres com maiúscula, minúscula, número e símbolo (@$!%*?&).';
}

if ($senha !== $confirmar) {
    $erros[] = 'As senhas não coincidem.';
}

if (!$lgpd) {
    $erros[] = 'Você precisa aceitar os termos da LGPD para criar sua conta.';
}

if (!empty($erros)) {
    redirecionar(
        '../../frontend/pages/cadastro-usuario.html?erro=' .
        urlencode(implode(' ', $erros))
    );
}

// ── Persistência ─────────────────────────────────────────────────
try {
    // 1. Verificar unicidade (e-mail OU cpf) — mensagem genérica por segurança
    $stmt = $pdo->prepare(
        'SELECT id FROM users WHERE (email = :email OR cpf = :cpf) AND deleted_at IS NULL'
    );
    $stmt->execute(['email' => $email, 'cpf' => $cpf]);

    if ($stmt->fetch()) {
        redirecionar(
            '../../frontend/pages/cadastro-usuario.html?erro=' .
            urlencode('Não foi possível criar a conta com os dados informados. Verifique e tente novamente.')
        );
    }

    // 2. Inserir usuário (inativo até confirmar e-mail)
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

    // 3. Token de confirmação de e-mail (expira em 24h)
    $tokenRaw  = bin2hex(random_bytes(32));        // 64 chars hex
    $tokenHash = hash('sha256', $tokenRaw);
    $expira    = date('Y-m-d H:i:s', time() + 86400);

    $pdo->prepare(
        'INSERT INTO tokens (user_id, token_hash, type, expires_at)
         VALUES (:uid, :hash, \'confirm\', :exp)'
    )->execute([
        'uid'  => $userId,
        'hash' => $tokenHash,
        'exp'  => $expira,
    ]);

    // 4. Consentimento LGPD
    $pdo->prepare(
        'INSERT INTO lgpd_consent (user_id, ip, policy_version, user_agent)
         VALUES (:uid, :ip, \'1.0\', :ua)'
    )->execute([
        'uid' => $userId,
        'ip'  => $ip,
        'ua'  => $userAgent,
    ]);

    // 5. Enviar e-mail de confirmação
    $linkConfirmacao = rtrim(BASE_URL ?? 'http://localhost', '/') .
        '/backend/auth/confirm.php?token=' . urlencode($tokenRaw);

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

    // 6. Guardar e-mail no lado cliente (mascarado na página de confirmação)
    //    Não usar sessão aqui pois usuário ainda não está autenticado.
    //    O frontend armazena em localStorage apenas para exibição visual.

} catch (PDOException $e) {
    error_log('[register.php] DB error: ' . $e->getMessage());
    redirecionar(
        '../../frontend/pages/cadastro-usuario.html?erro=' .
        urlencode('Erro interno. Tente novamente mais tarde.')
    );
} catch (Throwable $e) {
    error_log('[register.php] Error: ' . $e->getMessage());
    // Conta criada mas e-mail falhou — redirecionar para confirmação de qualquer forma
    // O usuário pode solicitar reenvio na página de confirmação
}

redirecionar('../../frontend/pages/confirmacao-cadastro.html');