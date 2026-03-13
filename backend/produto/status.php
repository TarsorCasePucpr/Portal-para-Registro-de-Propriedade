<?php
declare(strict_types=1);

// status.php — Alteração de status de um objeto (normal / roubado / perdido)
//
// Fluxo esperado:
//   1. Verificar autenticação — incluir auth_guard.php
//   2. Verificar token CSRF
//   3. O status aceito deve ser validado contra uma lista fixa de valores permitidos
//   4. A query de atualização deve filtrar pelo ID do objeto E pelo ID do usuário da sessão
//      (sem o filtro por usuário, qualquer pessoa logada poderia alterar o status de qualquer objeto)
//   5. Se nenhuma linha for atualizada, o objeto não existe ou não pertence ao usuário — responder com 403
