<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../middleware/rate_limiter.php';
require_once __DIR__ . '/../utils/response.php';

$pdo = getDb();
$ip  = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

if (!checkRateLimit($pdo, $ip, 'busca', 10, 1)) {
    jsonError('Muitas consultas. Aguarde 1 minuto e tente novamente.', 429);
}

$serial = trim(strip_tags($_GET['serial'] ?? ''));

if ($serial === '' || mb_strlen($serial) > 100) {
    jsonError('Número de série inválido.');
}

try {
    $stmt = $pdo->prepare(
        'SELECT status FROM objects
         WHERE serial_number = :serial AND deleted_at IS NULL
         LIMIT 1'
    );
    $stmt->execute(['serial' => $serial]);
    $objeto = $stmt->fetch();
} catch (PDOException $e) {
    error_log('[buscar] DB error: ' . $e->getMessage());
    jsonError('Erro interno. Tente novamente.', 500);
}

if (!$objeto) {
    jsonSuccess(['encontrado' => false, 'status' => 'nao_encontrado']);
}

jsonSuccess(['encontrado' => true, 'status' => $objeto['status']]);
