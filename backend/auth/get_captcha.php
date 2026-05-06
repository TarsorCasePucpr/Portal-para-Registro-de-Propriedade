<?php
declare(strict_types=1);

require_once __DIR__ . '/../middleware/auth_guard.php';
require_once __DIR__ . '/../utils/response.php';

startSessionSafe();

$a    = random_int(2, 20);
$b    = random_int(1, $a);
$plus = (bool) random_int(0, 1);
$answer = $plus ? ($a + $b) : ($a - $b);
$opStr  = $plus ? '+' : '−';

$salt = bin2hex(random_bytes(8));
$_SESSION['captcha_hash'] = hash('sha256', (string) $answer . $salt);
$_SESSION['captcha_salt'] = $salt;
$_SESSION['captcha_at']   = time();

jsonSuccess(['question' => "Quanto é {$a} {$opStr} {$b}?"]);
