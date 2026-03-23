# Como Contribuir — Portal para Registro de Propriedade

Guia completo para os integrantes do grupo trabalharem com Git e GitHub.

---

## Integrantes e páginas atribuídas

| Integrante | Páginas |
|------------|---------|
| Gerard | `index.html` + `busca.html` |
| Gustavo | `login.html` + `cadastro-usuario.html` |
| Henrique | `confirmacao.html` + `recuperacao-senha.html` |
| Kauã | `mfa.html` + `cadastro-produto.html` |
| Wellington | `dashboard.html` + backend |

---

## Regra principal

```
main    → versão de entrega. Só o responsável (Gerard) faz merge aqui.
develop → onde todo mundo trabalha e integra.
feature/seunome → sua branch pessoal de desenvolvimento.
```

**Nunca faça alterações diretamente no `main`.**

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

### 1.4 Criar sua branch de trabalho
```bash
git checkout develop
git pull origin develop
git checkout -b feature/seunome
git push origin feature/seunome
```

Substitua `seunome` pelo seu primeiro nome, tudo em minúsculas. Ex: `feature/gustavo`

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
git push origin feature/seunome
```

---

## Passo 3 — Enviar seu trabalho para o grupo (Pull Request)

Quando terminar uma página ou uma parte do trabalho:

1. Acesse o repositório no GitHub
2. Clique na aba **Pull requests**
3. Clique em **New pull request**
4. Selecione:
   - **base:** `develop`
   - **compare:** `feature/seunome`
5. Clique em **Create pull request**
6. Coloque um título descritivo: ex. `Add: login.html e cadastro-usuario.html`
7. Clique em **Create pull request**

**O que é um Pull Request?**
É um pedido para integrar o seu código ao `develop`. O Gerard vai revisar o que você enviou e, se estiver tudo certo, aprova e o código entra no grupo. Se tiver algo a corrigir, ele avisa nos comentários.

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
git push origin feature/seunome
# → abrir Pull Request para develop
```

---

## Resumo visual do fluxo

```
Você edita seus arquivos
        ↓
git add + git commit + git push → feature/seunome
        ↓
Abrir Pull Request → develop
        ↓
Gerard revisa e aprova → merge em develop
        ↓
(quando o grupo decidir) Gerard faz merge develop → main
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
