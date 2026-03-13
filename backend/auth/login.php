<?php
declare(strict_types=1);

// login.php — Autenticação de usuário
//
// Fluxo esperado:
//   1. Verificar token CSRF
//   2. Aplicar limite de tentativas por IP antes de consultar o banco
//   3. Buscar o usuário pelo e-mail informado
//   4. Comparar a senha com o hash armazenado — nunca comparar texto plano
//   5. Mensagens de erro devem ser genéricas: nunca revelar se o e-mail existe ou não
//   6. Após login bem-sucedido, regenerar o ID de sessão para evitar fixação de sessão
//   7. Se o usuário tiver MFA ativo, redirecionar para a etapa de verificação antes de liberar o acesso
