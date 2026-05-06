<?php
declare(strict_types=1);

ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Strict');
session_start();

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../middleware/auth_guard.php';
require_once __DIR__ . '/../middleware/csrf.php';
require_once __DIR__ . '/../utils/response.php';
require_once __DIR__ . '/../utils/telegram.php';

requireAdmin();

$pdo    = getDb();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $token = $_ENV['TELEGRAM_BOT_TOKEN'] ?? getenv('TELEGRAM_BOT_TOKEN') ?: '';
    if ($token === '') jsonError('TELEGRAM_BOT_TOKEN não configurado.', 500);

    $url = "https://api.telegram.org/bot{$token}/getUpdates?limit=20&allowed_updates=%5B%22message%22%5D";
    $ch  = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10]);
    $response = curl_exec($ch);
    curl_close($ch);

    $data = json_decode((string) $response, true);
    if (!($data['ok'] ?? false)) jsonError('Erro ao consultar Telegram: ' . ($data['description'] ?? ''), 502);

    $contatos = [];
    foreach ($data['result'] as $upd) {
        $msg  = $upd['message'] ?? null;
        if (!$msg) continue;
        $from = $msg['from'] ?? [];
        $contatos[] = [
            'chat_id'    => (string) ($msg['chat']['id'] ?? ''),
            'username'   => $from['username']   ?? '',
            'first_name' => $from['first_name'] ?? '',
            'last_name'  => $from['last_name']  ?? '',
            'text'       => $msg['text'] ?? '',
        ];
    }

    jsonSuccess(['data' => ['contatos' => array_values(array_unique($contatos, SORT_REGULAR))]]);
}

if ($method === 'POST') {
    $body    = json_decode(file_get_contents('php://input'), true) ?? [];
    $csrf    = trim((string) ($body['csrf']    ?? ''));
    $userId  = (int) ($body['user_id']  ?? 0);
    $chatId  = trim((string) ($body['chat_id'] ?? ''));

    if (!validateCsrfToken($csrf)) jsonError('Token de segurança inválido.', 403);
    if ($userId <= 0 || $chatId === '') jsonError('user_id e chat_id são obrigatórios.');

    try {
        $affected = $pdo->prepare(
            'UPDATE admin_profiles SET telegram_chat_id = :cid WHERE user_id = :uid'
        )->execute(['cid' => $chatId, 'uid' => $userId]);

        if (!$pdo->prepare('SELECT id FROM admin_profiles WHERE user_id = ?')->execute([$userId])) {
            jsonError('Perfil admin não encontrado.', 404);
        }

        jsonSuccess(['data' => ['mensagem' => "chat_id {$chatId} vinculado ao admin #{$userId}."]]);

    } catch (\PDOException $e) {
        error_log('[admin-bot-setup] ' . $e->getMessage());
        jsonError('Erro ao salvar.', 500);
    }
}

jsonError('Método não permitido.', 405);
