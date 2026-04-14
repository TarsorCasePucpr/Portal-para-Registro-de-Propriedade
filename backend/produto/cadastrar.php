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
require_once __DIR__ . '/../middleware/rate_limiter.php';
require_once __DIR__ . '/../utils/response.php';

requireAuth();
$userId = (int) $_SESSION['user_id'];

$pdo = getDb();
$ip  = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Método não permitido.', 405);
}

if (!validateCsrfToken($_POST['csrf'] ?? '')) {
    jsonError('Token de segurança inválido.', 403);
}

$acao = trim($_POST['acao'] ?? '');

if ($acao === 'excluir') {

    $id = filter_var($_POST['id'] ?? '', FILTER_VALIDATE_INT);

    if (!$id || $id <= 0) {
        jsonError('ID inválido.');
    }

    try {
        $stmt = $pdo->prepare(
            'UPDATE objects
             SET    deleted_at = NOW()
             WHERE  id = :id AND user_id = :uid AND deleted_at IS NULL'
        );
        $stmt->execute(['id' => $id, 'uid' => $userId]);

        if ($stmt->rowCount() === 0) {
            jsonError('Produto não encontrado ou sem permissão.', 403);
        }

        jsonSuccess(['mensagem' => 'Produto removido. A exclusão permanente ocorrerá em 30 dias (LGPD).']);

    } catch (PDOException $e) {
        error_log('[cadastrar.php/excluir] ' . $e->getMessage());
        jsonError('Erro interno ao excluir.', 500);
    }
}

if (!checkRateLimit($pdo, $ip, 'cadastro_produto', 10, 60)) {
    jsonError('Limite de cadastros atingido. Tente novamente em 1 hora.', 429);
}

$descricao  = trim(htmlspecialchars($_POST['descricao']    ?? '', ENT_QUOTES, 'UTF-8'));
$serial     = trim(strip_tags($_POST['serial_number']      ?? ''));
$nfeChave   = preg_replace('/\D/', '', $_POST['nfe_chave'] ?? '');
$dataCompra = trim($_POST['data_compra']                   ?? '');
$aceite     = ($_POST['aceite_termos']                     ?? '0') === '1';

$erros = [];

if ($descricao === '' || mb_strlen($descricao) < 5 || mb_strlen($descricao) > 500) {
    $erros[] = 'Descrição inválida (entre 5 e 500 caracteres).';
}

if ($serial === '' || mb_strlen($serial) < 3 || mb_strlen($serial) > 100) {
    $erros[] = 'Número de série inválido (entre 3 e 100 caracteres).';
}

$serial = preg_replace('/[\x00-\x1F\x7F]/u', '', $serial);

if ($dataCompra !== '') {
    $dt = DateTimeImmutable::createFromFormat('Y-m-d', $dataCompra);
    if (!$dt || $dt > new DateTimeImmutable('today')) {
        $erros[] = 'Data de compra inválida.';
        $dataCompra = null;
    }
} else {
    $dataCompra = null;
}

if ($nfeChave !== '' && strlen($nfeChave) !== 44) {
    $nfeChave = '';
}

if (!$aceite) {
    $erros[] = 'Aceite a declaração de responsabilidade para continuar.';
}

if (!empty($erros)) {
    jsonError(implode(' ', $erros));
}

$score = 0;
if ($nfeChave !== '')            $score += 40;
if ($dataCompra !== null)        $score += 30;
if (mb_strlen($descricao) > 30) $score += 30;

try {
    $stmt = $pdo->prepare(
        "INSERT INTO objects
           (user_id, descricao, serial_number, status,
            nfe_chave, nfe_validada, data_compra, score)
         VALUES
           (:uid, :desc, :serial, 'normal',
            :nfe, :nfe_val, :data, :score)"
    );
    $stmt->execute([
        'uid'     => $userId,
        'desc'    => $descricao,
        'serial'  => $serial,
        'nfe'     => $nfeChave !== '' ? $nfeChave : null,
        'nfe_val' => $nfeChave !== '' ? 1 : 0,
        'data'    => $dataCompra,
        'score'   => $score,
    ]);

    jsonSuccess([
        'mensagem' => 'Produto registrado com sucesso.',
        'score'    => $score,
    ]);

} catch (PDOException $e) {
    if ($e->getCode() === '23000') {
        jsonError('Este número de série já está registrado no sistema.');
    }
    error_log('[cadastrar.php] DB error: ' . $e->getMessage());
    jsonError('Erro interno ao registrar. Tente novamente.', 500);
}
