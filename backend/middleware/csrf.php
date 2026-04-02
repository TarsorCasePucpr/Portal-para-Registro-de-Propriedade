<?php
declare(strict_types=1);

// startSessionSafe() é definida em auth_guard.php — incluir esse arquivo antes de csrf.php
// quando ambos forem necessários, para evitar redefinição.

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
