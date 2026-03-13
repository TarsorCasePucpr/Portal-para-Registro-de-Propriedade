<?php
declare(strict_types=1);

// response.php — Respostas JSON padronizadas
//
// Pontos importantes:
//   - Todas as respostas da API devem ter o mesmo formato (sucesso ou erro)
//   - Mensagens de erro para o usuário devem ser genéricas — nunca expor detalhes internos
//   - Erros internos reais devem ser registrados no log do servidor, não enviados ao cliente
//   - O código HTTP da resposta deve corresponder ao tipo de situação (401, 403, 429, 500...)
