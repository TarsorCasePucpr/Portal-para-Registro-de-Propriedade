<?php
declare(strict_types=1);

require_once __DIR__ . '/store.php';

function verifyTurnstile(string $token, string $remoteIp = ''): bool
{
    if ($token === '') return false;

    $secret = secretGet('turnstile_secret');
    if ($secret === '') {
        error_log('[turnstile] secret no configurado en pool');
        return false;
    }

    $payload = ['secret' => $secret, 'response' => $token];
    if ($remoteIp !== '') $payload['remoteip'] = $remoteIp;

    $ch = curl_init('https://challenges.cloudflare.com/turnstile/v0/siteverify');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query($payload),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 5,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
    ]);
    $body = curl_exec($ch);
    $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($err !== '') {
        error_log('[turnstile] curl error: ' . $err);
        return false;
    }
    if ($http !== 200 || !is_string($body)) {
        error_log('[turnstile] HTTP ' . $http);
        return false;
    }

    $data = json_decode($body, true);
    if (!is_array($data)) return false;

    if (($data['success'] ?? false) !== true) {
        error_log('[turnstile] failed: ' . json_encode($data['error-codes'] ?? []));
        return false;
    }
    return true;
}

function turnstileSitekey(): string
{
    return secretGet('turnstile_sitekey');
}
