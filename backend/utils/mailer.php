<?php
declare(strict_types=1);

// mailer.php — Envio de e-mails
//
// Pontos importantes:
//   - Nunca usar a função mail() nativa do PHP — ela é vulnerável a injeção de cabeçalhos
//   - Usar uma biblioteca de envio como PHPMailer com autenticação SMTP
//   - As credenciais do servidor de e-mail não devem estar escritas neste arquivo
//   - E-mails só devem ser enviados para as finalidades declaradas ao usuário (LGPD)
//   - Nunca incluir dados pessoais de terceiros no corpo do e-mail sem necessidade
