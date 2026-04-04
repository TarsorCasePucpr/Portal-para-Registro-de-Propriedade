# SNGuard — Portal para Registro de Propriedade

**Disciplina:** Experiência Criativa: Implementando Sistemas com Criptografia  
**Professores:** Altair Olivo Santin / Aramis Hornung Moraes  
**Equipe:** Gerard Gonzalez · Gustavo Batista de Souza · Kauã Garcia Reschetti Rubbo

---

Sistema web para cadastro e rastreamento de objetos por número de série. Permite que proprietários registrem seus bens e que qualquer pessoa verifique o status de um objeto antes de adquiri-lo, auxiliando na recuperação de itens perdidos e na redução do mercado secundário de produtos roubados.

---

## Stack Técnica

| Camada | Tecnologia |
|--------|-----------|
| Backend | PHP puro (sem framework) |
| Frontend | HTML + CSS + JS vanilla |
| Banco de dados | MySQL |
| Autenticação | Custom PHP + MFA (TOTP via RFC 6238) |
| Email | PHPMailer + Gmail SMTP SSL |
| Segurança | bcrypt cost 13 · CSRF · rate limiting · PDO prepared statements |

---

## Estrutura do Projeto

```
backend/
  auth/         login, register, confirm, mfa, recover, logout, me, meus_dados
  config/       db.php (PDO singleton)
  lgpd/         delete_account.php
  middleware/   auth_guard.php, csrf.php, rate_limiter.php
  produto/      buscar, cadastrar, contato, listar, status
  utils/        hash.php, mailer.php, response.php, validadores.php
frontend/
  pages/        16 páginas HTML
  css/          style.css
  js/           main.js, validacoes.js
database/
  schema.sql
```

---

## Páginas do Sistema

| # | Página | Arquivo | Status |
|---|--------|---------|--------|
| 1 | Home / Landing | `index.html` | ✅ |
| 2 | Login | `login.html` | ✅ |
| 3 | Cadastro de usuário | `cadastro-usuario.html` | ✅ |
| 4 | Confirmação de e-mail | `confirmacao-cadastro.html` | ✅ |
| 5 | Autenticação MFA (TOTP) | `mfa.html` | ✅ |
| 6 | Recuperação de senha | `recuperacao-senha.html` | ✅ |
| 7 | Redefinição de senha | `redefinicao-senha.html` | ✅ |
| 8 | Dashboard do usuário | `dashboard.html` | ✅ |
| 9 | Cadastro de produto | `cadastro-produto.html` | ✅ |
| 10 | Busca pública por serial | `busca.html` | ✅ |
| 11 | Meus dados (perfil) | `meus-dados.html` | ✅ |
| 12 | Exclusão de conta (LGPD) | `exclusao-conta.html` | ✅ |
| 13 | Política de Privacidade | `politica_privavidade.html` | ✅ |
| 14 | Termos de Uso | `termo_de_uso.html` | ✅ |

---

## Divisão de Responsabilidades

### Gerard — Autenticação & Segurança

**Páginas:**
- `login.html` — formulário de login com validação client-side
- `mfa.html` — verificação TOTP (6 dígitos, max 3 tentativas/10min)
- `recuperacao-senha.html` — solicitação de link + passo MFA opcional
- `redefinicao-senha.html` — nova senha via token de URL
- `exclusao-conta.html` — exclusão parcial/total com confirmação de senha
- `confirmacao-email.html` — confirmação de cadastro por token

**Backend:**
- `auth/login.php` — bcrypt verify + session hardening + rate limit
- `auth/mfa.php` — TOTP nativo RFC 6238 (HMAC-SHA1 + base32)
- `auth/recover.php` — token hasheado (SHA-256) + anti-enumeration
- `auth/confirm.php` — hash_equals, token único de uso
- `auth/logout.php` — session_unset + session_destroy + limpeza de cookie
- `auth/me.php` — endpoint de perfil autenticado
- `lgpd/delete_account.php` — anonimização parcial/total LGPD Art. 18
- `utils/mailer.php` — PHPMailer Gmail SMTP SSL porta 465
- `utils/hash.php` — hashPassword / verifyPassword bcrypt cost 13
- `middleware/auth_guard.php` — requireAuth() protege rotas autenticadas
- `middleware/csrf.php` — generateCsrfToken / validateCsrfToken
- `middleware/rate_limiter.php` — checkRateLimit por IP+action

---

### Gustavo — Produtos & Painel

**Páginas:**
- `dashboard.html` — painel com lista de produtos, stats, modal de status, busca rápida
- `cadastro-produto.html` — registro de produto com NF-e, score de confiabilidade
- `busca.html` — busca pública por serial + contato anônimo ao proprietário
- `politica_privavidade.html` — Política de Privacidade (LGPD)
- `termo_de_uso.html` — Termos de Uso

**Backend:**
- `produto/cadastrar.php` — INSERT + score NF-e/data/descrição + soft delete
- `produto/listar.php` — listagem paginada filtrada por user_id da sessão
- `produto/buscar.php` — consulta pública por serial (sem expor dados pessoais)
- `produto/status.php` — alteração normal/perdido/roubado + verificação de propriedade
- `produto/contato.php` — mensagens anônimas ao proprietário via email

---

### Kauã — Cadastro, Perfil & Infraestrutura

**Páginas:**
- `index.html` — landing page com busca rápida, funcionalidades e info LGPD
- `cadastro-usuario.html` — registro com CPF, senha forte, consentimento LGPD
- `confirmacao-cadastro.html` — estados: aguardando / sucesso / erro de token
- `meus-dados.html` — perfil completo: alterar nome, alterar senha, transparência LGPD
- `confirmacao.html` — página de confirmação genérica

**Backend:**
- `auth/register.php` — bcrypt cost 13 + token de confirmação SHA-256 + email
- `auth/meus_dados.php` — atualização de nome e senha autenticada
- `config/db.php` — PDO singleton com ATTR_EMULATE_PREPARES=false
- `utils/validadores.php` — 8 funções centralizadas, 1 preg_match cada
- `utils/response.php` — jsonSuccess / jsonError padronizados

**Infraestrutura:**
- `database/schema.sql` — schema completo (users, objects, tokens, rate_limits, lgpd_*)
- `frontend/css/style.css` — folha de estilos principal (706 linhas)
- `frontend/js/validacoes.js` — validações reutilizáveis (email, CPF, senha, força)
- `frontend/js/main.js` — comportamentos globais (CSRF, toggle senha, indicador de força)

---

## Segurança Implementada

| Requisito | Implementação |
|-----------|--------------|
| Senha | bcrypt cost 13, password_needs_rehash a cada login |
| Sessão | HttpOnly, SameSite=Strict, regenerate_id após login, timeout 2h |
| SQL | PDO prepared statements, ATTR_EMULATE_PREPARES=false |
| CSRF | Token por sessão, hash_equals, todo POST protegido |
| Rate limiting | IP+action em tabela, login (5/15min), MFA (3/10min), contato (3/60min) |
| Tokens | bin2hex(random_bytes(32)), armazenado como SHA-256, uso único |
| MFA | TOTP nativo RFC 6238 (sem lib externa), window ±1 intervalo de 30s |
| Email | PHPMailer (nunca mail() nativo), credenciais via .env |
| XSS | htmlspecialchars ENT_QUOTES em todo output |
| LGPD | Consentimento explícito, soft delete, anonimização, delete_account endpoint |
| Headers | CSP, X-Frame-Options, X-Content-Type-Options, HSTS, Referrer-Policy |

---

## Requisitos do Professor (Sprints S1.1–S1.15)

| Sprint | Requisito | Status |
|--------|-----------|--------|
| S1.1 | Cadastro de usuário com validação | ✅ |
| S1.2 | Confirmação de cadastro por email | ✅ PHPMailer Gmail |
| S1.3 | Login com sessão segura | ✅ |
| S1.4 | Recuperação de senha por email | ✅ PHPMailer Gmail |
| S1.5 | Logout com destruição de sessão | ✅ |
| S1.6 | CRUD de produtos | ✅ |
| S1.7 | Busca pública por serial | ✅ |
| S1.8 | Validações centralizadas (1 preg_match por campo) | ✅ validadores.php |
| S1.9 | Rate limiting em login e MFA | ✅ |
| S1.10 | CSRF em todos os formulários POST | ✅ |
| S1.11 | Conformidade LGPD (consentimento + exclusão) | ✅ |
| S1.12 | MFA exclusivamente TOTP (sem email OTP) | ✅ |
| S1.13 | Página meus-dados completa | ✅ |
| S1.14 | Contato anônimo ao proprietário | ✅ |
| S1.15 | Cadastro de produto sem upload de arquivo | ✅ texto apenas |

---

## Métricas de Código

| Camada | Linhas |
|--------|--------|
| Frontend HTML (16 páginas) | 3.049 |
| Frontend JS | 313 |
| Frontend CSS | 706 |
| Backend PHP | 1.751 |
| Database SQL | 150 |
| **Total** | **5.969** |

Após limpeza de comentários e código morto: **−756 linhas** (−11,2% em relação ao estado anterior).

---

## Configuração Local

**1. Variáveis de ambiente** — criar `backend/utils/secrets.php` (ver `secrets.example.php`):

```env
DB_HOST=localhost
DB_NAME=portal_propriedade
DB_USER=root
DB_PASS=
MAIL_USER=seu@gmail.com
MAIL_PASS=app_password_aqui
MAIL_FROM_NAME=SNGuard
```

**2. Banco de dados:**

```bash
mysql -u root -p < database/schema.sql
```

**3. PHPMailer** (sem Composer — clonar na raiz):

```bash
git clone https://github.com/PHPMailer/PHPMailer
```

**4. Servidor local:**

```bash
php -S localhost:8000
```

Acessar: `http://localhost:8000/frontend/pages/index.html`

---

## Branches

| Branch | Uso |
|--------|-----|
| `main` | Produção — merge somente via review |
| `develop` | Desenvolvimento ativo |
| `feature/<nome>` | Features isoladas |
