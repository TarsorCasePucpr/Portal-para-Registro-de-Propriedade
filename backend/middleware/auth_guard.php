<?php
declare(strict_types=1);

require_once __DIR__ . '/../utils/response.php';

if (!function_exists('startSessionSafe')) {
    function startSessionSafe(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            ini_set('session.cookie_httponly', '1');
            ini_set('session.cookie_samesite', 'Strict');
            session_start();
        }
    }
}

function requireAuth(): void
{
    startSessionSafe();

    if (empty($_SESSION['user_id'])) {
        jsonError('Não autenticado. Faça login para continuar.', 401);
    }

    $timeout = 7200;
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $timeout) {
        session_unset();
        session_destroy();
        jsonError('Sessão expirada. Faça login novamente.', 401);
    }

    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    if (isset($_SESSION['user_agent']) && $_SESSION['user_agent'] !== $ua) {
        session_unset();
        session_destroy();
        jsonError('Sessão inválida. Faça login novamente.', 401);
    }

    if (!isset($_SESSION['user_agent'])) {
        $_SESSION['user_agent'] = $ua;
    }

    $_SESSION['last_activity'] = time();
}
