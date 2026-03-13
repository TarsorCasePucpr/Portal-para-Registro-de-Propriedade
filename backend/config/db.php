<?php
declare(strict_types=1);

// db.php — Conexão com o banco de dados
//
// Pontos importantes:
//   - As credenciais de acesso ao banco nunca devem estar escritas diretamente neste arquivo
//   - Usar variáveis de ambiente ou arquivo de configuração separado (fora do repositório)
//   - A conexão deve rejeitar prepared statements emulados — isso previne injeção de SQL
//   - Configurar o modo de erro para lançar exceções, nunca silenciar falhas
//   - Aplicar os headers de segurança HTTP aqui, pois este arquivo é carregado por todos os outros
