<?php
declare(strict_types=1);

// confirm.php — Confirmação de e-mail via token
//
// Fluxo esperado:
//   1. Ler o token da URL e sanitizá-lo
//   2. O token recebido nunca é o que está no banco — calcular o hash e comparar
//   3. A comparação deve ser resistente a timing attacks (não usar ==)
//   4. Verificar se o token ainda está dentro do prazo de validade
//   5. Tokens são de uso único — marcá-lo como usado após a ativação da conta
//   6. Ativar a conta e redirecionar o usuário para o login
