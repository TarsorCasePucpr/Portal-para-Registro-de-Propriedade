<?php
declare(strict_types=1);

ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Strict');
session_start();

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../middleware/csrf.php';
require_once __DIR__ . '/../utils/response.php';

if (!validateCsrfToken($_GET['csrf'] ?? '')) {
    redirect('../../frontend/pages/dashboard.html');
}

session_unset();
session_destroy();
setcookie(session_name(), '', time() - 3600, '/', '', true, true);

redirect('../../frontend/pages/index.html');
