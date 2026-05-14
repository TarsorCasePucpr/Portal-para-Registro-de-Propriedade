<?php
declare(strict_types=1);

function getClientIp(): string
{
    $cf = trim((string) ($_SERVER['HTTP_CF_CONNECTING_IP'] ?? ''));
    if ($cf !== '' && filter_var($cf, FILTER_VALIDATE_IP)) return $cf;

    $xff = (string) ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? '');
    if ($xff !== '') {
        $first = trim(explode(',', $xff)[0]);
        if ($first !== '' && filter_var($first, FILTER_VALIDATE_IP)) return $first;
    }

    $remote = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    return $remote !== '' ? $remote : '0.0.0.0';
}
