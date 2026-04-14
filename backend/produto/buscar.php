<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Cache-Control: no-store');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método não permitido.']);
    exit;
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../middleware/rate_limiter.php';
require_once __DIR__ . '/../utils/response.php';

$pdo = getDb();
$ip  = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

if (!checkRateLimit($pdo, $ip, 'busca_serial', 10, 1)) {
    jsonError('Muitas consultas. Aguarde 1 minuto e tente novamente.', 429);
}

$serial = trim(strip_tags($_GET['serial'] ?? ''));

if ($serial === '') {
    jsonError('Informe o número de série.');
}

if (mb_strlen($serial) > 100) {
    jsonError('Número de série inválido.');
}

$serial = preg_replace('/[\x00-\x1F\x7F]/u', '', $serial);

try {
    $stmt = $pdo->prepare(
        'SELECT status
         FROM   objects
         WHERE  serial_number = :serial
           AND  deleted_at    IS NULL
         LIMIT  1'
    );
    $stmt->execute(['serial' => $serial]);
    $objeto = $stmt->fetch(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log('[buscar.php] DB error: ' . $e->getMessage());
    jsonError('Erro interno. Tente novamente mais tarde.', 500);
}

if (!$objeto) {
    jsonSuccess([
        'encontrado' => false,
        'status'     => null,
    ]);
}

$statusPermitidos = ['normal', 'roubado', 'perdido'];
$status = in_array($objeto['status'], $statusPermitidos, true)
    ? $objeto['status']
    : 'normal';

jsonSuccess([
    'encontrado' => true,
    'status'     => $status,
]);
