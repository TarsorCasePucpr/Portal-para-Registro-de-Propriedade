<?php
declare(strict_types=1);

require_once __DIR__ . '/../middleware/auth_guard.php';
require_once __DIR__ . '/../utils/response.php';

startSessionSafe();

$now = time();
$window = 300; // 5 minutos
$maxGen = 10;

if (!isset($_SESSION['captcha_gen_times'])) {
    $_SESSION['captcha_gen_times'] = [];
}

$_SESSION['captcha_gen_times'] = array_filter(
    $_SESSION['captcha_gen_times'],
    fn(int $t) => ($now - $t) < $window
);

if (count($_SESSION['captcha_gen_times']) >= $maxGen) {
    jsonError('Muitas tentativas. Aguarde alguns minutos.', 429);
}

$_SESSION['captcha_gen_times'][] = $now;

$op = random_int(0, 2); // 0 = soma, 1 = subtração, 2 = multiplicação

switch ($op) {
    case 0:
        $a = random_int(2, 20);
        $b = random_int(3, 20);
        $answer = $a + $b;
        $opStr = '+';
        break;

    case 1:
        $a = random_int(10, 30);
        $b = random_int(1, $a - 1);
        $answer = $a - $b;
        $opStr = '−';
        break;

    default:
        $a = random_int(2, 9);
        $b = random_int(2, 9);
        $answer = $a * $b;
        $opStr = '×';
        break;
}

$ttl = 120; // 2 minutos
$salt = bin2hex(random_bytes(16));
$sessionBind = substr(session_id(), 0, 8);

$_SESSION['captcha_hash'] = hash('sha256', (string)(int)$answer . $salt . $sessionBind);
$_SESSION['captcha_salt'] = $salt;
$_SESSION['captcha_at'] = $now;
$_SESSION['captcha_ttl'] = $ttl;
$_SESSION['captcha_tries'] = 0;
$_SESSION['captcha_max_tries'] = 5;

jsonSuccess(['question' => "Quanto é {$a} {$opStr} {$b}?"]);

