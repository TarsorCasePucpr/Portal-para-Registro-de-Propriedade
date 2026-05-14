<?php
declare(strict_types=1);

function sendTelegramMessage(string $chatId, string $text): bool
{
    $token = $_ENV['TELEGRAM_BOT_TOKEN'] ?? getenv('TELEGRAM_BOT_TOKEN') ?: '';
    if ($token === '' || $chatId === '') return false;

    $url = "https://api.telegram.org/bot{$token}/sendMessage";
    $ch  = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => ['chat_id' => $chatId, 'text' => $text, 'parse_mode' => 'HTML'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $response = curl_exec($ch);
    $err      = curl_error($ch);
    curl_close($ch);

    if ($err !== '') {
        error_log('[telegram] curl error: ' . $err);
        return false;
    }

    $data = json_decode((string) $response, true);
    if (!($data['ok'] ?? false)) {
        error_log('[telegram] API error: ' . ($data['description'] ?? $response));
        return false;
    }
    return true;
}
