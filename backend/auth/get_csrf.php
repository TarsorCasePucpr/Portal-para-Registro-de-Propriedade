<?php
declare(strict_types=1);

ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Strict');
session_start();

require_once __DIR__ . '/../middleware/csrf.php';

$token = generateCsrfToken();
session_write_close();
header('Content-Type: application/json');
echo json_encode(['csrf' => $token]);
