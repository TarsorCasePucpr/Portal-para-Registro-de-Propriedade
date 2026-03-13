<?php
declare(strict_types=1);

// recover.php — Recuperação de senha (duas etapas)
//
// Etapa 1 — Solicitar link:
//   1. Verificar token CSRF e limite de requisições por IP
//   2. Buscar o usuário pelo e-mail — mas sempre responder de forma genérica, exista ou não
//   3. Gerar token seguro, armazenar apenas o hash no banco e enviar o valor original por e-mail
//   4. Definir prazo de expiração curto (1 hora)
//
// Etapa 2 — Redefinir senha:
//   1. Verificar token CSRF
//   2. Ler o token da URL, calcular o hash e comparar com o banco de forma segura
//   3. Verificar prazo de validade e se ainda não foi usado
//   4. A nova senha deve ser armazenada como hash bcrypt — nunca em texto plano
//   5. Marcar o token como usado após a alteração
