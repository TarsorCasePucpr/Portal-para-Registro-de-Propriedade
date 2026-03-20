<?php
declare(strict_types=1);

/**
 * Resposta JSON de sucesso.
 */
function jsonSuccess(mixed $data = [], int $code = 200): never {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'data' => $data]);
    exit;
}

/**
 * Resposta JSON de erro.
 * A mensagem deve ser genérica — nunca expor detalhes internos ao cliente.
 */
function jsonError(string $message, int $code = 400): never {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => $message]);
    exit;
}

/**
 * Redireciona para outra URL e encerra a execução.
 */
function redirect(string $url): never {
    header("Location: $url");
    exit;
}
