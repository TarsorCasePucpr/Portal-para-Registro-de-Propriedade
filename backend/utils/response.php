<?php
declare(strict_types=1);

function jsonSuccess(array $data = [], int $code = 200): never {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => true, ...$data]);
    exit;
}

function jsonError(string $message, int $code = 400): never {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'error' => $message]);
    exit;
}

function redirect(string $url): never {
    header("Location: {$url}");
    exit;
}
