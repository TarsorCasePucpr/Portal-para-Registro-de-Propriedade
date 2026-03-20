<?php
declare(strict_types=1);

// Custo do bcrypt — 13 é o padrão recomendado para 2026
// Aumentar este valor torna o hash mais seguro, mas mais lento
const BCRYPT_COST = 13;

/**
 * Gera o hash bcrypt de uma senha.
 * NUNCA armazenar senha em texto plano — usar sempre esta função.
 */
function hashPassword(string $password): string {
    return password_hash($password, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);
}

/**
 * Verifica se a senha informada corresponde ao hash armazenado.
 * Usa comparação segura contra timing attacks.
 */
function verifyPassword(string $password, string $hash): bool {
    return password_verify($password, $hash);
}

/**
 * Verifica se o hash precisa ser atualizado (ex: custo foi aumentado).
 * Chamar após cada login bem-sucedido.
 */
function needsRehash(string $hash): bool {
    return password_needs_rehash($hash, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);
}
