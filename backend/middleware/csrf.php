<?php
declare(strict_types=1);

/**
 * Gera (ou recupera) o token CSRF da sessão.
 * Um token por sessão — regenerado apenas no logout.
 */
function generateCsrfToken(): string {
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

/**
 * Valida o token CSRF de um POST.
 * Usa hash_equals para prevenir timing attacks.
 * Encerra a execução com 403 se inválido.
 */
function validateCsrfToken(): void {
    $sessionToken = $_SESSION['csrf'] ?? '';
    $postToken    = $_POST['csrf']    ?? '';

    if (!hash_equals($sessionToken, $postToken)) {
        http_response_code(403);
        die(json_encode(['error' => 'Token CSRF inválido. Recarregue a página e tente novamente.']));
    }
}
