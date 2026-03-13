<?php
declare(strict_types=1);

// contato.php — Mensagem anônima ao proprietário de objeto encontrado
//
// Fluxo esperado:
//   1. Verificar token CSRF
//   2. Aplicar rate limiting — limitar mensagens por serial por IP para evitar spam
//   3. Sanitizar o número de série e o texto da mensagem
//   4. Só permitir envio se o objeto tiver status de roubado ou perdido
//   5. Buscar o e-mail do proprietário para enviar a notificação
//   6. O e-mail deve chegar ao dono sem revelar quem enviou — intermediação anônima
//   7. Nunca retornar ao remetente qualquer dado do proprietário
//
// LGPD:
//   - O remetente é anônimo por design — nenhum dado pessoal dele é coletado
//   - O IP é registrado apenas para rate limiting
