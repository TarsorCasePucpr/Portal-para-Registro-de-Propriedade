<?php
declare(strict_types=1);

// cadastrar.php — Registro de objeto com número de série
//
// Fluxo esperado:
//   1. Verificar autenticação — incluir auth_guard.php
//   2. Verificar token CSRF
//   3. Sanitizar todos os campos recebidos
//   4. O ID do usuário deve vir da sessão, nunca do formulário — impede que um usuário cadastre em nome de outro
//   5. Verificar se o número de série já está registrado no sistema
//   6. Se um arquivo for enviado, validar o tipo real do arquivo — não confiar na extensão
//   7. Arquivos de upload não devem ficar em pasta acessível diretamente pelo navegador
//
// LGPD:
//   - Informar ao usuário que o status do objeto será visível publicamente na busca
//   - Dados pessoais do dono nunca aparecem na busca pública — apenas o status
