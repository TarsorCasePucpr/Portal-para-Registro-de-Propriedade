<?php
declare(strict_types=1);

function startSessionSafe(): void {
    if (session_status() !== PHP_SESSION_NONE) return;
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_secure', '1');
    ini_set('session.cookie_samesite', 'Strict');
    session_start();
}

function generateCsrfToken(): string {
    startSessionSafe();
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function validateCsrfToken(string $provided): bool {
    startSessionSafe();
    if (empty($_SESSION['csrf'])) return false;
    return hash_equals($_SESSION['csrf'], $provided);
}
