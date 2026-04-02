<?php
declare(strict_types=1);

/**
 * auth_guard.php — Proteção de rotas autenticadas
 *
 * Uso: require_once __DIR__ . '/../middleware/auth_guard.php';
 *      requireAuth();
 */

function startSessionSafe(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        ini_set('session.cookie_httponly', '1');
        ini_set('session.cookie_samesite', 'Strict');
        session_start();
    }
}

function requireAuth(): void
{
    startSessionSafe();

    // Sem sessão ativa
    if (empty($_SESSION['user_id'])) {
        jsonError('Não autenticado. Faça login para continuar.', 401);
    }

    // Timeout de inatividade: 2 horas
    $timeout = 7200;
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $timeout) {
        session_unset();
        session_destroy();
        jsonError('Sessão expirada. Faça login novamente.', 401);
    }

    // Verificação de user-agent (detecta sequestro de sessão básico)
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    if (isset($_SESSION['user_agent']) && $_SESSION['user_agent'] !== $ua) {
        session_unset();
        session_destroy();
        jsonError('Sessão inválida. Faça login novamente.', 401);
    }

    // Registrar user-agent na primeira chamada
    if (!isset($_SESSION['user_agent'])) {
        $_SESSION['user_agent'] = $ua;
    }

    $_SESSION['last_activity'] = time();
}