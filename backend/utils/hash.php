<?php
declare(strict_types=1);

const BCRYPT_COST = 13;

function hashPassword(string $password): string {
    return password_hash($password, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);
}

function verifyPassword(string $password, string $hash): bool {
    return password_verify($password, $hash);
}

function needsRehash(string $hash): bool {
    return password_needs_rehash($hash, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);
}
