<?php
declare(strict_types=1);

// hash.php — Funções de hash de senha
//
// Pontos importantes:
//   - Senhas devem ser armazenadas apenas como hash bcrypt — nunca MD5, SHA1 ou texto plano
//   - O custo do bcrypt deve ser suficientemente alto para dificultar força bruta (mínimo 12)
//   - A verificação de senha deve usar a função própria do PHP para comparação de hashes
//   - A cada login bem-sucedido, verificar se o hash precisa ser atualizado
//     (o custo pode ter aumentado desde o cadastro do usuário)
