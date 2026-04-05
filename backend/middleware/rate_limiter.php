<?php
declare(strict_types=1);

function isRateLimited(PDO $pdo, string $ip, string $action, int $max, int $windowMinutes): bool {
    $since = time() - ($windowMinutes * 60);
    $stmt  = $pdo->prepare(
        "SELECT COUNT(*) FROM rate_limits WHERE ip = ? AND action = ? AND created_at > ?"
    );
    $stmt->execute([$ip, $action, $since]);
    return (int) $stmt->fetchColumn() >= $max;
}

function recordFailedAttempt(PDO $pdo, string $ip, string $action): void {
    $pdo->prepare(
        "INSERT INTO rate_limits (ip, action, created_at) VALUES (?, ?, ?)"
    )->execute([$ip, $action, time()]);
}

// Para endpoints não-auth (contato, busca, cadastro): conta toda tentativa.
function checkRateLimit(PDO $pdo, string $ip, string $action, int $max, int $windowMinutes): bool {
    if (isRateLimited($pdo, $ip, $action, $max, $windowMinutes)) {
        return false;
    }
    recordFailedAttempt($pdo, $ip, $action);
    return true;
}
