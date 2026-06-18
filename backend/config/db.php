<?php
declare(strict_types=1);

require_once __DIR__ . '/../utils/store.php';

function loadEnv(string $path): void {
    if (!file_exists($path)) return;
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) continue;
        [$key, $value] = explode('=', $line, 2);
        $key   = trim($key);
        $value = trim($value);
        if (!array_key_exists($key, $_ENV)) {
            $_ENV[$key] = $value;
            putenv("{$key}={$value}");
        }
    }
}

function applySecurityHeaders(): void {
    if (headers_sent()) return;
    header(
        "Content-Security-Policy: " .
        "default-src 'self'; " .
        "script-src 'self' https://challenges.cloudflare.com; " .
        "style-src 'self' 'unsafe-inline'; " .
        "img-src 'self' data:; " .
        "frame-src https://challenges.cloudflare.com; " .
        "connect-src 'self' https://challenges.cloudflare.com; " .
        "base-uri 'self'; " .
        "form-action 'self'"
    );
    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
}

function getDb(): PDO {
    static $pdo = null;
    if ($pdo !== null) return $pdo;

    $base = dirname(__DIR__, 2);
    loadEnv($base . '/.env');

    $host = $_ENV['DB_HOST'] ?? getenv('DB_HOST') ?: 'localhost';
    $port = $_ENV['DB_PORT'] ?? getenv('DB_PORT') ?: '3306';
    $name = $_ENV['DB_NAME'] ?? getenv('DB_NAME') ?: 'portal_propriedade';
    $user = $_ENV['DB_USER'] ?? getenv('DB_USER') ?: 'snguard';
    $pass = secretGet('db_pass');

    $pdo = new PDO(
        "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4",
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::MYSQL_ATTR_FOUND_ROWS   => true,
        ]
    );
    return $pdo;
}

function getAdminDb(): PDO {
    static $pdo = null;
    if ($pdo !== null) return $pdo;

    $base = dirname(__DIR__, 2);
    loadEnv($base . '/.env');

    $host = $_ENV['DB_HOST'] ?? getenv('DB_HOST') ?: 'localhost';
    $port = $_ENV['DB_PORT'] ?? getenv('DB_PORT') ?: '3306';
    $name = $_ENV['DB_NAME'] ?? getenv('DB_NAME') ?: 'portal_propriedade';
    $user = $_ENV['DB_ADMIN_USER'] ?? getenv('DB_ADMIN_USER') ?: 'snguard_admin';
    $pass = secretGet('db_admin_pass');

    $pdo = new PDO(
        "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4",
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::MYSQL_ATTR_FOUND_ROWS   => true,
        ]
    );
    return $pdo;
}

applySecurityHeaders();
