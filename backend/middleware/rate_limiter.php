<?php
declare(strict_types=1);

// rate_limiter.php — Limite de tentativas por IP
//
// Pontos importantes:
//   - Registrar cada tentativa na tabela rate_limits com IP, ação e timestamp
//   - Contar tentativas dentro da janela de tempo antes de processar a requisição
//   - Se o limite for atingido, responder com 429 e indicar quando tentar novamente
//   - Limites por ação: login (5/15min), MFA (3/10min), recuperação de senha (3/60min), contato (3/60min)
//   - Registros antigos devem ser removidos periodicamente para não crescer indefinidamente
