<?php
declare(strict_types=1);

function validarEmail(string $v): bool
{
    return (bool) preg_match(
        '/^[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}$/',
        $v
    ) && mb_strlen($v) <= 255;
}

function validarSenhaForte(string $v): bool
{
    return (bool) preg_match(
        '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&]).{12,}$/',
        $v
    );
}

function validarCPF(string $v): bool
{
    return (bool) preg_match('/^\d{3}\.\d{3}\.\d{3}-\d{2}$/', $v);
}

function validarCEP(string $v): bool
{
    return (bool) preg_match('/^\d{5}-\d{3}$/', $v);
}

function validarTelefone(string $v): bool
{
    return (bool) preg_match(
        '/^(\(?\d{2}\)?[\s\-]?)?(\d{4,5}[\-\s]?\d{4})$/',
        trim($v)
    );
}

function validarData(string $v): bool
{
    return (bool) preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $v);
}

function validarPlacaVeiculo(string $v): bool
{
    return (bool) preg_match(
        '/^[A-Z]{3}[\-]?\d{3}[0-9A-Z]\d?$/',
        strtoupper(trim($v))
    );
}

function validarNome(string $v): bool
{
    $len = mb_strlen(trim($v));
    if ($len < 3 || $len > 100) return false;
    if (preg_match('/(.)\1{3,}/', $v)) return false;
    return true;
}
