<?php
declare(strict_types=1);

// buscar.php — Busca pública por número de série
//
// Fluxo esperado:
//   1. Não requer autenticação — qualquer pessoa pode consultar
//   2. Aplicar rate limiting por IP para evitar varredura em massa
//   3. Sanitizar o número de série recebido
//   4. A resposta deve conter APENAS o status do objeto (registrado / roubado / não encontrado)
//   5. Nunca incluir na resposta: nome do dono, e-mail, CPF ou qualquer dado pessoal
//
// LGPD:
//   - Esta rota é o ponto central de minimização de dados do sistema
//   - O IP é registrado apenas para rate limiting e eliminado após 30 dias
