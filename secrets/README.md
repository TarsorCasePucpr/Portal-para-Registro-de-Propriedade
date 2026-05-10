# secrets/

Este diretório contém os arquivos de segredos usados pelo Docker Compose.
**NUNCA commitar estes arquivos.** O diretório inteiro está no .gitignore.

## Criar os arquivos em produção

```bash
# Senha do banco de dados
echo "senha_forte_aqui" > secrets/db_pass.txt

# Senha root do MySQL (só para inicialização)
echo "senha_root_aqui" > secrets/db_root_pass.txt

# Senha do SMTP (Gmail App Password)
echo "aaaa bbbb cccc dddd" > secrets/mail_pass.txt

# Chave da aplicação (JWT/session)
openssl rand -hex 32 > secrets/app_secret.txt

# Chave de criptografia AES-256 para colunas sensíveis
openssl rand -hex 32 > secrets/app_encrypt_key.txt

# Chave HMAC para lookup de campos criptografados
openssl rand -hex 32 > secrets/app_hmac_key.txt

# Restringir permissões
chmod 600 secrets/*.txt
```
