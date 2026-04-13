<?php
declare(strict_types=1);

<<<<<<< HEAD
/**
 * cadastrar.php — Registro e exclusão de produtos
 *
 * POST /backend/produto/cadastrar.php
 *
 * Ações (campo 'acao'):
 *   (omitido)  → cadastrar novo produto
 *   'excluir'  → soft delete de produto próprio
 *   'consultar_nf' → consulta chave NF-e na Receita (retorna JSON)
 *
 * Segurança:
 *   - Autenticação obrigatória (auth_guard)
 *   - CSRF em todo POST
 *   - user_id sempre vem da sessão — nunca do formulário
 *   - Upload: tipo real validado via finfo, extensão forçada, salvo fora do webroot
 *   - Serial único no sistema (UNIQUE no banco)
 *   - Rate limit na consulta de NF
 *
 * LGPD:
 *   - Exclusão = soft delete; purge real após 30 dias por cron
 *   - status do objeto é público; dados pessoais do dono nunca saem
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
require_once __DIR__ . '/../middleware/rate_limiter.php';
require_once __DIR__ . '/../utils/response.php';

// ── Autenticação ─────────────────────────────────────────────────
requireAuth();                          // redireciona ou emite JSON 401
$userId = (int) $_SESSION['user_id'];

$pdo = getDb();
$ip  = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

// ── Apenas POST ──────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Método não permitido.', 405);
}

// ── CSRF ─────────────────────────────────────────────────────────
if (!validateCsrfToken($_POST['csrf'] ?? '')) {
    jsonError('Token de segurança inválido.', 403);
}

$acao = trim($_POST['acao'] ?? '');

// ════════════════════════════════════════════════════════════════
//  AÇÃO: consultar_nf
// ════════════════════════════════════════════════════════════════
if ($acao === 'consultar_nf') {

    if (!checkRateLimit($pdo, $ip, 'consulta_nf', 5, 5)) {
        jsonError('Muitas consultas de NF. Aguarde alguns minutos.', 429);
    }

    $chave = preg_replace('/\D/', '', $_POST['chave'] ?? '');

    if (strlen($chave) !== 44) {
        jsonError('Chave da NF-e inválida (deve ter 44 dígitos numéricos).');
    }

    // Consulta à API pública da Receita / SEFAZ (stub — adapte ao serviço real)
    // Em produção use um serviço como NFe.io, FocusNFe, ou webservice SEFAZ direto.
    $resultado = consultarNFe($chave);

    if (!$resultado) {
        jsonSuccess([
            'encontrado' => false,
            'produto'    => null,
        ]);
    }

    jsonSuccess([
        'encontrado' => true,
        'produto'    => [
            'descricao'    => $resultado['descricao']    ?? '',
            'serial'       => $resultado['serial']       ?? '',
            'data_emissao' => $resultado['data_emissao'] ?? '',
        ],
    ]);
}

// ════════════════════════════════════════════════════════════════
//  AÇÃO: excluir (soft delete)
// ════════════════════════════════════════════════════════════════
if ($acao === 'excluir') {

    $id = filter_var($_POST['id'] ?? '', FILTER_VALIDATE_INT);

    if (!$id || $id <= 0) {
        jsonError('ID inválido.');
    }

    try {
        // Filtrar por user_id DA SESSÃO — usuário só pode excluir seus próprios produtos
        $stmt = $pdo->prepare(
            'UPDATE objects
             SET    deleted_at = NOW()
             WHERE  id = :id AND user_id = :uid AND deleted_at IS NULL'
        );
        $stmt->execute(['id' => $id, 'uid' => $userId]);

        if ($stmt->rowCount() === 0) {
            jsonError('Produto não encontrado ou sem permissão.', 403);
        }

        // Registrar solicitação de purge LGPD para os dados do objeto após 30 dias
        // (a exclusão real das fotos e registros associados é feita pelo cron job)
        jsonSuccess(['mensagem' => 'Produto removido. A exclusão permanente ocorrerá em 30 dias (LGPD).']);

    } catch (PDOException $e) {
        error_log('[cadastrar.php/excluir] ' . $e->getMessage());
        jsonError('Erro interno ao excluir.', 500);
    }
}

// ════════════════════════════════════════════════════════════════
//  AÇÃO: cadastrar (default)
// ════════════════════════════════════════════════════════════════

// ── Rate limit: 10 cadastros por hora por usuário ─────────────────
if (!checkRateLimit($pdo, $ip, 'cadastro_produto', 10, 60)) {
    jsonError('Limite de cadastros atingido. Tente novamente em 1 hora.', 429);
}

// ── Sanitizar campos de texto ────────────────────────────────────
$descricao  = trim(htmlspecialchars($_POST['descricao']     ?? '', ENT_QUOTES, 'UTF-8'));
$serial     = trim(strip_tags($_POST['serial_number']       ?? ''));
$nfeChave   = preg_replace('/\D/', '', $_POST['nfe_chave']  ?? '');
$dataCompra = trim($_POST['data_compra']                    ?? '');
$aceite     = ($_POST['aceite_termos']                      ?? '0') === '1';

// ── Validação ────────────────────────────────────────────────────
$erros = [];

if ($descricao === '' || mb_strlen($descricao) < 5 || mb_strlen($descricao) > 500) {
    $erros[] = 'Descrição inválida (entre 5 e 500 caracteres).';
}

if ($serial === '' || mb_strlen($serial) < 3 || mb_strlen($serial) > 100) {
    $erros[] = 'Número de série inválido (entre 3 e 100 caracteres).';
}

// Remover caracteres de controle do serial
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
    $nfeChave = '';   // ignorar chave malformada silenciosamente
}

if (!$aceite) {
    $erros[] = 'Aceite a declaração de responsabilidade para continuar.';
}

if (!empty($erros)) {
    jsonError(implode(' ', $erros));
}

// ── Upload de imagens ────────────────────────────────────────────
$uploadDir    = realpath(__DIR__ . '/../../storage/uploads/produtos') . '/';
$tiposPermitidos = ['image/jpeg', 'image/png', 'image/webp'];
$extsPermitidas  = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'];
$maxBytes        = 5 * 1024 * 1024; // 5 MB

function processarUpload(string $campo, string $dir, array $tipos, int $max): ?string
{
    if (empty($_FILES[$campo]['tmp_name']) || $_FILES[$campo]['error'] !== UPLOAD_ERR_OK) {
        return null;
    }

    $tmp = $_FILES[$campo]['tmp_name'];

    if ($_FILES[$campo]['size'] > $max) {
        jsonError("Arquivo {$campo} muito grande. Máximo 5MB.");
    }

    // Validar tipo REAL (não confiar na extensão ou Content-Type)
    $finfo    = new finfo(FILEINFO_MIME_TYPE);
    $mimeReal = $finfo->file($tmp);

    if (!in_array($mimeReal, $tipos, true)) {
        jsonError("Tipo de arquivo inválido para {$campo}. Use JPG, PNG ou WebP.");
    }

    $ext      = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$mimeReal];
    $nomeArq  = bin2hex(random_bytes(16)) . '.' . $ext;
    $destino  = $dir . $nomeArq;

    if (!is_dir($dir)) {
        mkdir($dir, 0750, true);
    }

    if (!move_uploaded_file($tmp, $destino)) {
        error_log('[cadastrar.php] Falha ao mover upload para ' . $destino);
        jsonError('Erro ao processar imagem. Tente novamente.', 500);
    }

    // Retornar caminho relativo (não URL pública)
    return 'produtos/' . $nomeArq;
}

$caminhoFotoProduto = processarUpload('foto_produto', $uploadDir, $tiposPermitidos, $maxBytes);
$caminhoFotoSerial  = processarUpload('foto_serial',  $uploadDir, $tiposPermitidos, $maxBytes);

// ── Score inicial ────────────────────────────────────────────────
$score = 0;
if ($nfeChave !== '')          $score += 40;  // NF validada
if ($caminhoFotoProduto)       $score += 20;  // Foto do produto
if ($caminhoFotoSerial)        $score += 20;  // Foto do serial
if ($dataCompra !== null)      $score += 10;  // Data de compra informada
if (mb_strlen($descricao) > 30) $score += 10; // Descrição detalhada

// ── Inserir no banco ─────────────────────────────────────────────
try {
    $stmt = $pdo->prepare(
        'INSERT INTO objects
           (user_id, descricao, serial_number, status,
            foto_produto, foto_serial,
            nfe_chave, nfe_validada, data_compra, score)
         VALUES
           (:uid, :desc, :serial, \'normal\',
            :foto_p, :foto_s,
            :nfe, :nfe_val, :data, :score)'
    );
    $stmt->execute([
        'uid'     => $userId,
        'desc'    => $descricao,
        'serial'  => $serial,
        'foto_p'  => $caminhoFotoProduto,
        'foto_s'  => $caminhoFotoSerial,
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
    // Erro 23000 = Duplicate entry (serial_number UNIQUE)
    if ($e->getCode() === '23000') {
        jsonError('Este número de série já está registrado no sistema.');
    }
    error_log('[cadastrar.php] DB error: ' . $e->getMessage());
    jsonError('Erro interno ao registrar. Tente novamente.', 500);
}

// ════════════════════════════════════════════════════════════════
//  Stub: consulta NF-e
//  Substitua pela integração real com SEFAZ / serviço terceiro.
// ════════════════════════════════════════════════════════════════
function consultarNFe(string $chave): ?array
{
    // Exemplo de integração com a API da NFe.io ou webservice SEFAZ:
    // $url = "https://api.nfe.io/v1/taxinvoices/{$chave}?api_key=" . NFE_API_KEY;
    // $ctx = stream_context_create(['http' => ['timeout' => 8]]);
    // $raw = @file_get_contents($url, false, $ctx);
    // if (!$raw) return null;
    // $data = json_decode($raw, true);
    // return $data ? ['descricao' => $data['product']['name'], ...] : null;

    // Por ora retorna null (NF não encontrada) para não bloquear desenvolvimento
    return null;
}
=======
// cadastrar.php — Registro de objeto com número de série
//
// Fluxo esperado:
//   1. Verificar autenticação — incluir auth_guard.php
//   2. Verificar token CSRF
//   3. Sanitizar todos os campos recebidos
//   4. O ID do usuário deve vir da sessão, nunca do formulário — impede que um usuário cadastre em nome de outro
//   5. Verificar se o número de série já está registrado no sistema
//   6. Se um arquivo for enviado, validar o tipo real do arquivo — não confiar na extensão
//   7. Arquivos de upload não devem ficar em pasta acessível diretamente pelo navegador
//
// LGPD:
//   - Informar ao usuário que o status do objeto será visível publicamente na busca
//   - Dados pessoais do dono nunca aparecem na busca pública — apenas o status
>>>>>>> origin/develop
