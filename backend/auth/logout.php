<?php
declare(strict_types=1);

ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Lax');
session_start();

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../middleware/csrf.php';
require_once __DIR__ . '/../utils/response.php';
require_once __DIR__ . '/../utils/logger.php';

if (!validateCsrfToken($_GET['csrf'] ?? '')) {
    redirect('../../frontend/pages/dashboard.html');
}

$logUserId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
$logPdo    = getDb();

session_unset();
session_destroy();
setcookie(session_name(), '', time() - 3600, '/', '', true, true);

if ($logUserId) {
    logAction($logPdo, $logUserId, 'logout', 'user', $logUserId, [], 'user');
}

redirect('../../frontend/pages/index.html');
