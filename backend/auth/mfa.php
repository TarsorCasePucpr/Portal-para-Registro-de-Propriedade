<?php
declare(strict_types=1);

// mfa.php — Verificação do segundo fator de autenticação
//
// Fluxo esperado:
//   1. Verificar que existe uma sessão parcial pendente de MFA antes de processar
//   2. Verificar token CSRF
//   3. Aplicar limite de tentativas — código incorreto repetido deve bloquear temporariamente
//   4. O método preferido é TOTP (aplicativo autenticador) — e-mail só como fallback
//   5. O segredo TOTP armazenado no banco deve estar criptografado, não em texto plano
//   6. Após validação bem-sucedida, regenerar o ID de sessão e completar o login
