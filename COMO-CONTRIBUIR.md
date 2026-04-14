# Como Contribuir — Portal para Registro de Propriedade

Guia completo para os integrantes do grupo trabalharem com Git e GitHub.

---

## Integrantes e responsabilidades

| Integrante | Branch | Responsabilidades |
|------------|--------|-------------------|
| Gerard | `feature/Gerard_Gonzalez` | Cadastro de usuário, backend hashing (bcrypt), Privacy by Design, Banco de dados |
| Gustavo | `feature/GustavoBatista` | (definir com o grupo) |
| Henrique | `feature/henrique` | (definir com o grupo) |
| Kauã | `feature/Kauã-Rubbo` | (definir com o grupo) |
| Wellington | — | (definir com o grupo) |

---

## Regra principal

```
main           → versão final / servidor. Merge só desde develop, com aprovação.
develop        → integração do grupo. PROTEGIDA — exige PR + aprovação + CI verde.
feature/SeuNome → sua branch pessoal. Aqui você pode fazer o que quiser.
```

**Nunca faça alterações diretamente em `develop` ou `main`.**

### Por que `develop` é protegida?

O GitHub bloqueia qualquer push direto para `develop`. Para integrar seu código, você **obrigatoriamente** precisa:

1. Abrir um **Pull Request** (`feature/SeuNome` → `develop`)
2. Os **6 checks automáticos de CI** passarem (ver abaixo)
3. **1 companheiro** do grupo aprovar o seu PR

Se qualquer um desses três falhar, o merge é bloqueado.

---

## Passo 1 — Configuração inicial (só uma vez)

### 1.1 Instalar Git
- Windows: https://git-scm.com/download/win
- Mac: `brew install git`
- Linux: `sudo apt install git`

### 1.2 Configurar seu nome no Git
```bash
git config --global user.name "Seu Nome"
git config --global user.email "seu@email.com"
```

### 1.3 Clonar o repositório
```bash
git clone https://github.com/TarsorCasePucpr/Portal-para-Registro-de-Propriedade.git
cd Portal-para-Registro-de-Propriedade
```

### 1.4 Pegar sua branch de trabalho (já criadas no repo)

As branches já foram criadas. Só precisa baixar a sua:

```bash
git fetch --all
git checkout -b feature/SeuNome origin/feature/SeuNome
```

Exemplo para cada integrante:
```bash
# Gerard
git checkout -b feature/Gerard_Gonzalez origin/feature/Gerard_Gonzalez

# Gustavo
git checkout -b feature/GustavoBatista origin/feature/GustavoBatista

# Henrique
git checkout -b feature/henrique origin/feature/henrique

# Kauã
git checkout -b "feature/Kauã-Rubbo" "origin/feature/Kauã-Rubbo"
```

---

## Passo 2 — Rotina diária de trabalho

### Antes de começar (sempre)
```bash
git checkout develop
git pull origin develop
git checkout feature/seunome
git merge develop
```
Isso garante que você está trabalhando com a versão mais recente.

### Fazer alterações e salvar
```bash
# Edite seus arquivos normalmente

git add frontend/pages/login.html
git commit -m "Add: formulário de login"
git push origin feature/SeuNome
```

---

## Passo 3 — Enviar seu trabalho para o grupo (Pull Request)

Quando terminar uma parte do trabalho:

1. Acesse o repositório no GitHub
2. Clique na aba **Pull requests**
3. Clique em **New pull request**
4. Selecione:
   - **base:** `develop`
   - **compare:** `feature/SeuNome`
5. Clique em **Create pull request**
6. Coloque um título descritivo: ex. `Add: login.html com validação de formulário`
7. Clique em **Create pull request**

---

## Passo 4 — O que acontece depois de abrir o PR

### 4.1 — CI automático (obrigatório)

O GitHub vai rodar automaticamente **6 verificações**. Todas precisam estar verdes para poder fazer merge:

| Check | O que verifica |
|-------|---------------|
| PHP Syntax | Erros de sintaxe nos arquivos PHP |
| PHP Strict Types | `declare(strict_types=1)` em todo PHP |
| Security Scan | Senhas em texto plano, SQL injection, etc. |
| HTML Lint | Erros estruturais no HTML |
| Schema Integrity | Integridade do `schema.sql` |
| File Structure | Arquivos nos diretórios corretos |

Se algum check falhar, aparece um ❌ no PR. Corrija e faça um novo push — o CI roda de novo automaticamente.

### 4.2 — Aprovação de um companheiro (obrigatório)

O `develop` exige **1 aprovação** de outro integrante do grupo antes de fazer merge.

**Sugestão de rotação — quem revisa quem:**

| Quem abriu o PR | Quem revisa |
|-----------------|-------------|
| Gerard | Kauã |
| Kauã | Gustavo |
| Gustavo | Henrique |
| Henrique | Gerard |

Para revisar: abra o PR, clique em **Files changed**, leia o código, e clique em **Review changes → Approve** (ou deixe comentários se tiver algo a corrigir).

### 4.3 — Fazer o merge

Depois que o CI está verde **e** tem 1 aprovação, o botão **Merge pull request** fica disponível. Quem abriu o PR pode clicar nele.

---

## Passo 5 — Merge de `develop` para `main`

O merge para `main` só acontece quando o grupo decidir que uma versão está pronta para entrega. O processo é igual: abrir um PR de `develop` → `main`.

---

## Mensagens de commit — como escrever

Use sempre o formato:

```
Add:      → quando adicionou algo novo
Fix:      → quando corrigiu um erro
Update:   → quando alterou algo que já existia
Remove:   → quando removeu algo
```

Exemplos:
```bash
git commit -m "Add: página de login com formulário"
git commit -m "Fix: erro no link do CSS"
git commit -m "Update: campos do cadastro de usuário"
```

---

## Relatório no Overleaf

O relatório do projeto está em `docs/relatorio.tex`.

Para editar no Overleaf:

1. Acesse https://www.overleaf.com
2. Crie conta ou faça login
3. Clique em **New Project → Upload Project**
4. Faça upload do arquivo `docs/relatorio.tex`
5. Se houver logo: faça upload de `docs/Logo.png` também
6. Edite o relatório diretamente no Overleaf
7. Quando terminar, baixe o `.tex` atualizado e substitua em `docs/relatorio.tex` no repositório

```bash
# Após substituir o arquivo:
git add docs/relatorio.tex
git commit -m "Update: relatório parcial"
git push origin feature/SeuNome
# → abrir Pull Request para develop
```

---

## Resumo visual do fluxo

```
Você edita seus arquivos
        ↓
git add + git commit + git push → feature/SeuNome
        ↓
Abrir Pull Request → develop
        ↓
CI automático roda os 6 checks (deve passar tudo ✅)
        ↓
1 companheiro revisa e aprova o PR ✅
        ↓
Merge em develop
        ↓
(quando o grupo decidir) PR develop → main
```

---

## Dúvidas frequentes

**Deu conflito ao fazer merge — o que faço?**
Avisa o Gerard. Conflito acontece quando duas pessoas editaram o mesmo trecho do mesmo arquivo. É raro se cada um mexe só nas suas páginas.

**Esqueci de fazer pull antes de começar — e agora?**
```bash
git stash          # guarda suas alterações temporariamente
git pull origin develop
git stash pop      # recupera suas alterações
```

**Como vejo o que mudou antes de commitar?**
```bash
git status         # arquivos alterados
git diff           # o que mudou linha por linha
```

**Como desfaço o último commit (antes de fazer push)?**
```bash
git reset --soft HEAD~1
```
