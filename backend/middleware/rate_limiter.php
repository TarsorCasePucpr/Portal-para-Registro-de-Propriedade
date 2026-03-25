<?php
declare(strict_types=1);

function checkRateLimit(PDO $pdo, string $ip, string $action, int $max, int $windowMinutes): bool {
    $since = time() - ($windowMinutes * 60);

    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM rate_limits WHERE ip = ? AND action = ? AND created_at > ?"
    );
    $stmt->execute([$ip, $action, $since]);

    if ((int) $stmt->fetchColumn() >= $max) {
        return false;
    }

    $pdo->prepare(
        "INSERT INTO rate_limits (ip, action, created_at) VALUES (?, ?, ?)"
    )->execute([$ip, $action, time()]);

    return true;
}
