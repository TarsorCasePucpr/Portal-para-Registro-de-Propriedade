<?php
declare(strict_types=1);

// register.php — Cadastro de novo usuário
//
// Fluxo esperado:
//   1. Verificar token CSRF antes de processar qualquer dado
//   2. Validar e sanitizar todos os campos recebidos (nome, email, CPF, senha)
//   3. Verificar se o e-mail já está cadastrado — responder de forma genérica (nunca confirmar existência)
//   4. As senhas NUNCA devem ser salvas em texto plano — usar hash bcrypt antes de gravar no banco
//   5. Gerar token de confirmação de e-mail seguro e enviá-lo ao usuário
//   6. Registrar o aceite LGPD com timestamp e IP antes de criar a conta
//   7. Redirecionar para a página de confirmação após o cadastro
