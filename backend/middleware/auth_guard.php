<?php
declare(strict_types=1);

// auth_guard.php — Proteção de rotas autenticadas
//
// Incluir no início de qualquer arquivo PHP que exija login.
//
// Pontos importantes:
//   - Verificar se existe uma sessão válida com usuário identificado
//   - Sessões inativas por mais de 30 minutos devem ser encerradas automaticamente
//   - Verificar se o user agent da sessão corresponde ao atual — detecta tentativas de sequestro
//   - Atualizar o timestamp de atividade a cada requisição autenticada
//   - Responder com 401 se a sessão for inválida ou expirada
