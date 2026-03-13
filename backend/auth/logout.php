<?php
declare(strict_types=1);

// logout.php — Encerramento de sessão
//
// Fluxo esperado:
//   1. Validar o token CSRF mesmo no logout — links de logout podem ser explorados por terceiros
//   2. Limpar todas as variáveis de sessão
//   3. Destruir a sessão no servidor
//   4. Remover o cookie de sessão do navegador do usuário
//   5. Redirecionar para a página inicial — nunca para uma URL recebida como parâmetro
