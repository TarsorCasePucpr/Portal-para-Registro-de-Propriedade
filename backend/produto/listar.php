<?php
declare(strict_types=1);

// listar.php — Listagem de produtos do usuário autenticado
//
// Fluxo esperado:
//   1. Verificar autenticação — incluir auth_guard.php
//   2. O filtro por usuário deve usar o ID da sessão, nunca um parâmetro recebido
//      (sem esse filtro, um usuário poderia ver os objetos de outro)
//   3. Excluir da listagem objetos marcados como deletados (soft delete)
//   4. Retornar apenas os campos necessários para exibir no dashboard
