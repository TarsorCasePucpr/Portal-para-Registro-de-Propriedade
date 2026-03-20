<?php
declare(strict_types=1);

// Carrega variáveis do .env (arquivo na raiz do projeto)
$envFile = __DIR__ . '/../../.env';
if (file_exists($envFile)) {
    foreach (parse_ini_file($envFile) ?: [] as $key => $val) {
        $_ENV[$key] = $val;
    }
}

// Headers de segurança HTTP — aplicados em todas as respostas
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');

/**
 * Retorna a conexão PDO singleton.
 * Prepared statements emulados desativados para prevenir SQL injection.
 */
function getDb(): PDO {
    static $pdo = null;

    if ($pdo !== null) {
        return $pdo;
    }

    $host = $_ENV['DB_HOST'] ?? 'localhost';
    $port = $_ENV['DB_PORT'] ?? '3306';
    $name = $_ENV['DB_NAME'] ?? 'portal_propriedade';
    $user = $_ENV['DB_USER'] ?? 'root';
    $pass = $_ENV['DB_PASS'] ?? '';

    try {
        $pdo = new PDO(
            "mysql:host=$host;port=$port;dbname=$name;charset=utf8mb4",
            $user,
            $pass,
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_EMULATE_PREPARES   => false, // Crítico: true permite SQLi em PHP antigo
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]
        );
    } catch (PDOException $e) {
        error_log('DB connection failed: ' . $e->getMessage());
        http_response_code(500);
        die(json_encode(['error' => 'Erro de conexão com o banco de dados.']));
    }

    return $pdo;
}
