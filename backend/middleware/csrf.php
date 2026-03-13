<?php
declare(strict_types=1);

// csrf.php — Proteção contra Cross-Site Request Forgery
//
// Pontos importantes:
//   - Gerar um token aleatório por sessão e incluí-lo em todos os formulários como campo oculto
//   - Validar o token em todo POST antes de processar qualquer dado
//   - A comparação do token deve ser resistente a timing attacks — não usar ==
//   - Se o token for inválido, rejeitar a requisição com status 403 imediatamente
