# SNGuard — Portal para Registro de Propriedade

**Disciplina:** Experiência Criativa: Implementando Sistemas com Criptografia
**Professores:** Altair Olivo Santin / Aramis Hornung Moraes
**Equipe:** Gerard Gonzalez, Gustavo Batista de Souza, Henrique Bernardo Maciel, Kauã Garcia Reschetti Rubbo, Wellington Breno Evangelista Costa

---

Sistema web para cadastro de proprietário de qualquer produto que contenha número de série único, com o objetivo de auxiliar o proprietário, a polícia e outros cidadãos a encontrar o dono de um objeto.

Dois objetivos principais:

1. **Recuperação de objetos perdidos** — qualquer pessoa que encontre um objeto pode consultar o número de série, identificar o dono e acionar a devolução via autoridades.
2. **Eliminação do mercado secundário de roubados** — compradores podem verificar se um objeto está marcado como roubado antes de adquiri-lo, desincentivando o roubo pela queda de demanda.

---

## Por que esta ideia faz sentido

Quando um objeto roubado é registrado no sistema:
- O número de série fica marcado como **roubado/reportado**
- Qualquer pessoa no mercado secundário pode consultar antes de comprar
- Se o número de identificação foi raspado ou apagado, isso já sinaliza suspeita
- A demanda por objetos roubados cai → o risco do roubo aumenta → o crime se desincentiva

Quando um objeto é encontrado:
- A pessoa que encontrou consulta o serial → identifica o dono → notifica a polícia
- A polícia entra em contato com o dono → devolução facilitada

---

## Casos de uso

**UC1 — Registro de objeto**
Usuário cadastra seu objeto com número de série, categoria e descrição. Recebe um registro vinculado à sua conta.

**UC2 — Objeto perdido / roubado**
Dono marca o objeto como roubado. O serial fica público no sistema como "reportado".

**UC3 — Consulta pública**
Qualquer pessoa digita um número de série e vê: dono registrado, status (normal / reportado como roubado), sem expor dados pessoais do dono.

**UC4 — Objeto encontrado**
Pessoa encontra objeto, consulta serial, vê que tem dono → aciona a delegacia mais próxima com o número do registro.

**UC5 — Verificação antes de compra**
Comprador consulta serial do objeto que vai adquirir. Se marcado como roubado → não compra. Se serial apagado → sinal de alerta.

---

## Workflow do Projeto (Kanban)

| Estado | Descrição |
|--------|-----------|
| **Planejamento** | Definição de requisitos e design inicial |
| **Em Execução** | Tarefas em desenvolvimento ativo |
| **Teste de Funcionalidade** | Verificação de que o código faz o que se espera |
| **Teste de Funcionalidade Finalizado** | Validação de funcionalidade completa |
| **Teste de Cibersegurança** | Revisão de vulnerabilidades |
| **Teste de Cibersegurança Finalizado** | Validação de segurança completa |
| **Concluído** | Tarefa terminada e verificada |

---

## Estado Atual do Projeto

### Páginas frontend

| # | Página | Arquivo | Status |
|---|--------|---------|--------|
| 1 | Home / Landing | `index.html` | ✅ Implementado |
| 2 | Busca pública por serial | `busca.html` | ✅ Implementado |
| 3 | Login | `login.html` | ✅ Implementado |
| 4 | Cadastro de usuário | `cadastro-usuario.html` | ✅ Implementado |
| 5 | Confirmação de cadastro (email) | `confirmacao-cadastro.html` | ✅ Implementado |
| 6 | Autenticação MFA | `mfa.html` | ✅ Implementado |
| 7 | Recuperação de senha (solicitar link) | `recuperacao-senha.html` | ⚠️ Parcial |
| 8 | Redefinição de senha (nova senha via link) | `redefinicao-senha.html` | ✅ Implementado |
| 9 | Cadastro de produto | `cadastro-produto.html` | ⚠️ Parcial |
| 10 | Dashboard do usuário | `dashboard.html` | 📋 Planejamento |
| 11 | Meus dados (perfil) | `meus-dados.html` | ⚠️ Parcial |
| 12 | Exclusão de conta (LGPD) | `exclusao-conta.html` | ✅ Implementado |

> **Legenda:** ✅ Implementado e integrado · ⚠️ Parcial (estrutura criada, falta funcionalidade ou tem bugs pendentes) · 📋 Planejamento
>
> A página 7 (recuperacao-senha) e a página 8 (redefinicao-senha) são distintas: a 7 é o formulário onde o usuário informa o email para receber o link; a 8 é o formulário para definir a nova senha após clicar no link.

---

### Checklists por página

**Home / index.html** ✅
- [x] Título da organização: SNGuard
- [x] Botão para a página de login
- [x] Botão para acessar a página de consulta por S/N
- [x] Botão para página de cadastro
- [x] Descrição das atividades e objetivos sociais

**Busca por S/N (busca.html)** ✅
- [x] Campo de busca por número de série
- [x] Exibir status do objeto (sem expor dados pessoais — LGPD)
- [x] Badges coloridos: verde (registrado), vermelho (alerta), cinza (não encontrado)
- [x] Formulário de contato anônimo ao proprietário (quando roubado/perdido)
- [x] Pré-preenchimento via `?serial=` na URL (integrado com index.html)

**Cadastro de usuário (cadastro-usuario.html)** ✅
- [x] Validação de email
- [x] Campo de senha oculto com opção de mostrar
- [x] Botões de login e registro
- [x] Conformidade com LGPD (consentimento)

**Confirmação de cadastro (confirmacao-cadastro.html)** ✅
- [x] Mensagem de sucesso ao usuário (via `?status=success`)
- [x] Mensagem de erro quando token inválido (via `?status=error`)
- [ ] Envio de email com link de confirmação (aguarda PHPMailer)

**Autenticação MFA (mfa.html)** ✅
- [x] Seleção de método: TOTP (recomendado) ou Email OTP
- [x] Campo para código de 6 dígitos
- [x] Envio de email OTP via `?action=send_email`
- [x] Validação do código e redirecionamento ao dashboard
- [ ] Envio de email real (aguarda PHPMailer)

**Exclusão de conta (exclusao-conta.html)** ✅
- [x] Confirmação de senha antes de excluir
- [x] Opção parcial (anonimizar dados) ou total (anonimizar + soft-delete objetos)
- [x] Conformidade LGPD Art. 18, VI

**Recuperação de senha (recuperacao-senha.html)** ⚠️
- [x] Input de email com validação
- [x] Lógica de envio de token/link (recover.php step 1 — backend implementado)
- [x] Anti-enumeration: sempre responde `?msg=ok` independente do email existir
- [ ] Feedback visual de "E-mail enviado" no frontend (HTML pendente)

**Redefinição de senha (redefinicao-senha.html)** ✅
- [x] Inputs de nova senha e confirmação com máscara
- [x] Verificação de igualdade entre os campos (validacoes.js)
- [x] Validação de requisitos (mín. 12 chars, maiúscula, número, especial)
- [x] Leitura do token via URL e submissão ao backend
- [x] Atualização no banco de dados via token válido (recover.php)

**Cadastro de produto (cadastro-produto.html)** ⚠️
- [x] Formulário: descrição, número de série (S/N), fotos, data de compra
- [x] Campo para chave NF-e (44 dígitos)
- [x] Declaração de responsabilidade legal
- [ ] Integração com backend/produto/cadastrar.php (pendente — Gustavo)
- [ ] Vinculação automática com o ID do usuário logado

**Dashboard (dashboard.html)** 📋
- [ ] Grid ou lista de produtos cadastrados
- [ ] Status de cada item ("Protegido" ou "Alerta")
- [ ] Botão de logout com CSRF
- [ ] Atalhos rápidos para novo cadastro e perfil

### Backend — Auth

| Arquivo | Função | Status |
|---------|--------|--------|
| `auth/register.php` | Registro de usuário + envio de confirmação | ✅ Implementado |
| `auth/confirm.php` | Confirmação de email via token | ✅ Implementado |
| `auth/login.php` | Login + sessão + redirecionamento MFA | ✅ Implementado |
| `auth/logout.php` | Logout seguro com CSRF | ✅ Implementado |
| `auth/mfa.php` | TOTP nativo RFC 6238 + Email OTP | ✅ Implementado |
| `auth/recover.php` | Recuperação de senha (step 1 + step 2 completos) | ✅ Implementado |

### Backend — Produto

| Arquivo | Função | Status |
|---------|--------|--------|
| `produto/cadastrar.php` | Registrar objeto com serial | 📋 Planejamento |
| `produto/listar.php` | Listar objetos do usuário | 📋 Planejamento |
| `produto/buscar.php` | Busca pública por serial | ✅ Implementado |
| `produto/status.php` | Alterar status (roubado/perdido) | 📋 Planejamento |
| `produto/contato.php` | Envio de mensagem anônima ao dono | 📋 Planejamento |

### Backend — LGPD

| Arquivo | Função | Status |
|---------|--------|--------|
| `lgpd/delete_account.php` | Exclusão/anonimização de conta (Art. 18, VI) | ✅ Implementado |

### Backend — Middleware

| Arquivo | Função | Status |
|---------|--------|--------|
| `middleware/auth_guard.php` | Proteção de rotas + expiração de sessão | ✅ Implementado |
| `middleware/csrf.php` | Geração e validação de token CSRF | ✅ Implementado |
| `middleware/rate_limiter.php` | Limite de tentativas por IP/ação | ✅ Implementado |

### Backend — Utils

| Arquivo | Função | Status |
|---------|--------|--------|
| `utils/hash.php` | `hashPassword()` + `verifyPassword()` bcrypt 13 | ✅ Implementado |
| `utils/mailer.php` | PHPMailer — envio de emails | 📋 Planejamento |
| `utils/secrets.php` | Carregamento de credenciais (gitignoreado) | ✅ Implementado |
| `utils/response.php` | `jsonSuccess()` + `jsonError()` padronizados | ✅ Implementado |

### Banco de dados

| Tabela | Descrição | Status |
|--------|-----------|--------|
| `users` | Usuários cadastrados (com CPF para NF-e) | ✅ Implementado |
| `tokens` | Tokens de confirmação, recuperação e MFA | ✅ Implementado |
| `rate_limits` | Controle de tentativas por IP/ação | ✅ Implementado |
| `lgpd_consent` | Registro de consentimento LGPD | ✅ Implementado |
| `lgpd_deletion_requests` | Solicitações de exclusão (purga em 30 dias) | ✅ Implementado |
| `objects` | Objetos registrados por serial | 📋 Planejamento |
| `contact_messages` | Mensagens anônimas de objetos encontrados | 📋 Planejamento |

> **Nota:** As tabelas `objects` e `contact_messages` precisam ser adicionadas ao `database/schema.sql` para que o cadastro e busca de produtos funcionem end-to-end.

---

## Estrutura do Projeto

```
Portal-para-Registro-de-Propriedade/
├── frontend/
│   ├── css/
│   │   └── style.css
│   ├── js/
│   │   ├── main.js
│   │   └── validacoes.js
│   └── pages/
│       ├── index.html               # ✅ Home / Landing
│       ├── busca.html               # ✅ Busca pública por serial
│       ├── login.html               # ✅ Login
│       ├── cadastro-usuario.html    # ✅ Cadastro de usuário
│       ├── confirmacao-cadastro.html # ✅ Confirmação de cadastro por email
│       ├── mfa.html                 # ✅ Autenticação 2 fatores
│       ├── exclusao-conta.html      # ✅ Exclusão de conta (LGPD)
│       ├── meus-dados.html          # ⚠️ Perfil do usuário (parcial)
│       ├── recuperacao-senha.html   # ⚠️ Solicitar link de recuperação (parcial)
│       ├── redefinicao-senha.html   # 📋 Definir nova senha via link (a implementar)
│       ├── cadastro-produto.html    # ⚠️ Registro de objeto com serial (parcial)
│       └── dashboard.html           # 📋 Painel do usuário (a implementar)
├── backend/
│   ├── config/
│   │   └── db.php                   # ✅ Conexão PDO (singleton)
│   ├── auth/
│   │   ├── login.php                # ✅
│   │   ├── logout.php               # ✅
│   │   ├── register.php             # ✅
│   │   ├── confirm.php              # ✅
│   │   ├── recover.php              # ⚠️ Step 2 implementado, step 1 pendente
│   │   └── mfa.php                  # ✅ TOTP nativo + Email OTP
│   ├── lgpd/
│   │   └── delete_account.php       # ✅ Exclusão LGPD Art. 18, VI
│   ├── middleware/
│   │   ├── auth_guard.php           # ✅
│   │   ├── csrf.php                 # ✅
│   │   └── rate_limiter.php         # ✅
│   ├── produto/
│   │   ├── cadastrar.php            # 📋 A implementar
│   │   ├── listar.php               # 📋 A implementar
│   │   ├── buscar.php               # ✅
│   │   ├── status.php               # 📋 A implementar
│   │   └── contato.php              # 📋 A implementar
│   └── utils/
│       ├── hash.php                 # ✅ bcrypt cost 13
│       ├── mailer.php               # 📋 PHPMailer — a implementar
│       ├── secrets.php              # ✅ Gitignoreado — não commitar
│       └── response.php             # ✅ jsonSuccess() + jsonError()
├── database/
│   └── schema.sql                   # ✅ users, tokens, rate_limits, lgpd_* | 📋 objects, contact_messages
├── docs/
│   ├── Logo.png                     # Logo do SNGuard
│   └── relatorio.tex                # Relatório da disciplina
├── .env.example                     # Template de variáveis de ambiente
├── .gitignore
└── CLAUDE.md                        # Regras e contexto para o assistente IA
```

---

## Workflow Git — Equipe de 5 pessoas

```
main       ← entrega final
└── develop ← integração contínua
    ├── feature/pessoa1   (index + busca)
    ├── feature/pessoa2   (login + cadastro-usuario)
    ├── feature/pessoa3   (confirmacao + recuperacao-senha)
    ├── feature/pessoa4   (mfa + cadastro-produto)
    └── feature/pessoa5   (dashboard + backend)
```

**Por pessoa:**
```bash
git checkout develop && git pull origin develop
git checkout -b feature/seunome
# trabalhar nas páginas atribuídas
git add . && git commit -m "Add: pagina-x e pagina-y"
git push origin feature/seunome
# → abrir Pull Request para develop
```

---

## Requisitos

- PHP 8.1+
- MySQL 8.0+
- Servidor web (Apache / Nginx)
- Composer

---

## Configuração

1. Clone o repositório:
   ```bash
   git clone https://github.com/TarsorCasePucpr/Portal-para-Registro-de-Propriedade.git
   cd Portal-para-Registro-de-Propriedade
   ```

2. Instale as dependências:
   ```bash
   composer install
   ```

3. Configure o ambiente:
   ```bash
   cp .env.example .env
   # editar .env com suas credenciais
   ```

4. Importe o banco de dados:
   ```bash
   mysql -u root -p portal_propriedade < database/schema.sql
   ```

5. Configure o servidor web apontando para a raiz do projeto.

---

## Deploy no servidor

O servidor puxa automaticamente as atualizações do `main` via cron job semanal:

```bash
# Clonar na primeira vez no servidor
git clone https://github.com/TarsorCasePucpr/Portal-para-Registro-de-Propriedade.git /var/www/portal

# Cron job — toda segunda-feira às 03:00
# crontab -e
0 3 * * 1 cd /var/www/portal && git pull origin main
```

**Fluxo de atualização:**
```
develop (trabalho da equipe)
    ↓  merge aprovado por Gerard
main (versão estável)
    ↓  cron job semanal
servidor (site ao vivo)
```

Isso garante que apenas código revisado e estável chega ao servidor.

---

## Branches

| Branch | Descrição |
|--------|-----------|
| `main` | Código estável — entrega final |
| `develop` | Desenvolvimento ativo |
| `feature/*` | Branch por integrante |

---

## Validação de Propriedade via NF-e

### Conceito

Ao cadastrar um objeto, o usuário informa a **chave de acesso** da Nota Fiscal Eletrônica (NF-e) de 44 dígitos. O sistema consulta a NF-e e valida que:

1. O CPF do **destinatário** na NF-e corresponde ao CPF cadastrado do usuário
2. O número de série do produto (se presente na NF-e) é extraído automaticamente
3. Se o número de série não constar na NF-e, o usuário o informa manualmente

```
Usuário cadastrado (CPF salvo em users.cpf)
    ↓  informa chave de acesso NF-e (44 dígitos)
backend/nfe/validar.php
    ↓  consulta API externa
NFe JSON → buyer.federalTaxNumber  (CPF do comprador)
NFe XML  → det/prod/infAdProd       (número de série, se informado)
         → det/prod/xProd           (descrição do produto)
    ↓  valida
CPF da NF-e == CPF do usuário logado  ✓
    ↓
Objeto registrado com serial vinculado ao usuário — propriedade validada
```

---

### Estrutura interna da NF-e (leiaute 4.0)

| Dado | Caminho XML | Observação |
|------|-------------|------------|
| CPF do comprador | `NFe/infNFe/dest/CPF` | Sempre presente em NF-e modelo 55; pode faltar em NFC-e de baixo valor |
| Descrição do produto | `NFe/infNFe/det/prod/xProd` | Até 120 chars — às vezes inclui o serial |
| Número de série | `NFe/infNFe/det/prod/infAdProd` | Campo de texto livre, até 500 chars — local mais comum para serial |
| Série do documento | `NFe/infNFe/ide/serie` | Série da NF-e (1, 2, 3…) — **não** é o serial do produto |

> **Importante:** O número de série do produto **não é campo padronizado** no leiaute da NF-e. O emitente pode incluí-lo em `infAdProd` ou no `xProd` como texto livre. Nem sempre está presente — nesse caso, o usuário informa manualmente.

---

### APIs disponíveis para consulta

> **Realidade:** Não existe API oficial da Receita Federal/SEFAZ gratuita e sem contrato que retorne dados completos via REST. O portal público `nfe.fazenda.gov.br` usa reCAPTCHA e não pode ser automatizado. Para o protótipo, a estratégia é usar **duas APIs combinadas** — uma para testes com dados mock (sem custo) e outra para dados reais (créditos gratuitos ao criar conta).

---

#### API 1 — SERPRO Trial (testes com dados mock, sem contrato)

Ambiente de homologação do SERPRO com credenciais **publicadas na documentação oficial** — qualquer um pode usar sem assinar contrato:

- **Endpoint:** `GET https://gateway.apiserpro.serpro.gov.br/consulta-nfe-df-trial/api/v1/nfe/{chaveDeAcesso}`
- **Autenticação:** OAuth2 — obter token em `https://gateway.apiserpro.serpro.gov.br/token`
- **Retorna JSON com `dest.CPF`** (confirmado na documentação oficial)
- **Limitação crítica:** Só responde a chaves fictícias pré-definidas na documentação — **não consulta NF-e reais**. Serve apenas para validar a integração do código.

Exemplo de chave fictícia para testes: `35170608530528000184550000000154301000771561`

---

#### API 2 — Infosimples (dados reais, CPF no JSON, créditos gratuitos)

- **Endpoint:** `POST https://api.infosimples.com/api/v2/consultas/sefaz/nfe`
- **Retorna `destinatario.cpf`** como campo nomeado diretamente no JSON
- Retorna também: `itens[].descricao`, `itens[].valor`, status da NF-e, emitente
- **Custo:** R$ 100,00 de créditos ao criar conta → ~500 consultas reais gratuitas

---

#### Abordagem combinada para o protótipo

```
Fase 1 — Desenvolvimento (sem custo):
  SERPRO trial → testar OAuth2, parsing do JSON, lógica de comparação CPF
  Chaves fictícias pré-definidas na doc → validar o código sem gastar crédito

Fase 2 — Demonstração com dados reais:
  Infosimples trial → R$ 100 créditos ao criar conta = ~500 consultas reais
  Retorna destinatario.cpf diretamente no JSON → comparar com users.cpf
  Parsear itens[].descricao para extrair número de série (regex em texto livre)
```

---

### Fluxo de implementação (fase futura)

```
1. backend/nfe/validar.php
   - Recebe chave de acesso (44 dígitos) + user_id da sessão
   - Valida formato da chave (dígito verificador módulo 11)
   - Consulta API escolhida → obtém CPF do destinatário
   - Compara com users.cpf do usuário logado
   - Se válido: extrai xProd e tenta regex em infAdProd para serial
   - Retorna: { cpf_ok: true, serial: "ABC123" | null, descricao: "..." }

2. backend/produto/cadastrar.php
   - Se serial veio da NF-e → nfe_validated = 1
   - Se serial foi informado manualmente → nfe_validated = 0
   - Salva em objects com nfe_chave para auditoria

3. Rate limiting: máx. 5 consultas NF-e / 10 min por IP (evitar abuso da API externa)
```

---

## Escopo — Objetos cobertos pelo sistema

Categorias e objetos prioritários (mais roubados e revendidos em mercado secundário):

### Eletrônicos
| # | Objeto |
|---|--------|
| 1 | Smartphone (iPhone, Samsung, etc.) |
| 2 | Notebook / Laptop |
| 3 | Tablet (iPad, etc.) |
| 4 | Smartwatch (Apple Watch, etc.) |
| 5 | Fones sem fio (AirPods, etc.) |
| 6 | Câmera fotográfica (DSLR / mirrorless) |
| 7 | Console de videogame (PlayStation, Xbox) |
| 8 | Nintendo Switch / portátil |
| 9 | Controle de videogame |
| 10 | Drone |
| 11 | Smart speaker (Echo, Google Nest) |
| 12 | Projetor portátil |
| 13 | E-reader (Kindle, etc.) |

### Transporte
| # | Objeto |
|---|--------|
| 14 | Bicicleta (convencional) |
| 15 | Bicicleta elétrica (e-bike) |
| 16 | Patinete elétrico |
| 17 | Moto (documentação vinculada ao serial do chassi) |
| 18 | GPS automotivo |
| 19 | Rádio / som automotivo |
| 20 | Rodas e pneus de carro |

### Ferramentas e equipamentos
| # | Objeto |
|---|--------|
| 21 | Furadeira / parafusadeira elétrica |
| 22 | Serra circular |
| 23 | Esmerilhadeira |
| 24 | Gerador de energia |
| 25 | Compressor de ar |
| 26 | Lavadora de alta pressão |
| 27 | Equipamento de solda |
| 28 | Motosserra |

### Instrumentos musicais
| # | Objeto |
|---|--------|
| 29 | Guitarra elétrica |
| 30 | Guitarra acústica / violão |
| 31 | Baixo elétrico |
| 32 | Teclado / sintetizador |
| 33 | Bateria eletrônica |
| 34 | Amplificador |
| 35 | Microfone profissional |
| 36 | Mesa de som / mixer |
| 37 | Controlador DJ |

### Equipamentos de imagem e som
| # | Objeto |
|---|--------|
| 38 | Televisão |
| 39 | Caixa de som portátil (JBL, etc.) |
| 40 | Sistema de home theater |
| 41 | Câmera de segurança |
| 42 | Filmadora |
| 43 | Estabilizador de câmera (gimbal) |

### Esportes e lazer
| # | Objeto |
|---|--------|
| 44 | Bicicleta de montanha (MTB) |
| 45 | Prancha de surf |
| 46 | Skate / longboard |
| 47 | Tacos de golfe (jogo completo) |
| 48 | Equipamento de mergulho |
| 49 | Kayak / caiaque |
| 50 | Patins inline |
| 51 | Equipamento de crossfit / musculação portátil |

### Joias e relógios
| # | Objeto |
|---|--------|
| 52 | Relógio de pulso (analógico de valor) |
| 53 | Anel |
| 54 | Colar / corrente |
| 55 | Pulseira |
| 56 | Brincos de valor |

### Malas e bolsas
| # | Objeto |
|---|--------|
| 57 | Mochila (especialmente com eletrônicos) |
| 58 | Bolsa de couro / grife |
| 59 | Mala de viagem |

### Outros
| # | Objeto |
|---|--------|
| 60 | Cadeira de rodas / scooter de mobilidade |
| 61 | Equipamento médico portátil |
| 62 | Máquina de cartão (POS) |
| 63 | Impressora portátil |
| 64 | Roteador / equipamento de rede |
| 65 | Extintor de incêndio (veicular) |

---

## Mapa do Site (Sitemap)

```
SNGuard — Portal para Registro de Propriedade
│
├── / (index.html)                  ← Landing page + busca rápida pública
│   ├── /busca.html                 ← Consulta pública por S/N (sem login)
│   ├── /login.html                 ← Autenticação
│   ├── /cadastro-usuario.html      ← Registro + consentimento LGPD
│   ├── /confirmacao.html           ← Aguardando confirmação de e-mail
│   ├── /mfa.html                   ← Verificação TOTP (2º fator)
│   ├── /recuperacao-senha.html     ← Solicitar link de recuperação
│   └── /redefinicao-senha.html     ← Definir nova senha via link
│
└── [AUTENTICADO]
    ├── /dashboard.html             ← Painel com lista de produtos
    └── /cadastro-produto.html      ← Registrar novo objeto com S/N
```

**Rotas de backend por módulo:**

| Módulo | Rota | Método |
|--------|------|--------|
| Auth | `backend/auth/register.php` | POST |
| Auth | `backend/auth/confirm.php` | GET |
| Auth | `backend/auth/login.php` | POST |
| Auth | `backend/auth/mfa.php` | POST |
| Auth | `backend/auth/recover.php` | POST |
| Auth | `backend/auth/logout.php` | GET |
| Produto | `backend/produto/buscar.php` | GET (público) |
| Produto | `backend/produto/cadastrar.php` | POST (autenticado) |
| Produto | `backend/produto/listar.php` | GET (autenticado) |
| Produto | `backend/produto/status.php` | POST (autenticado) |
| Produto | `backend/produto/contato.php` | POST (público) |

---

## Conformidade LGPD — Lei nº 13.709/2018

O SNGuard foi projetado com **Privacidade por Design** (Privacy by Design) como princípio arquitetural. A seguir, o mapeamento das obrigações legais para os componentes do sistema.

### Bases Legais Utilizadas (Art. 7)

| Base Legal | Onde se Aplica |
|------------|---------------|
| **Art. 7, I** — Consentimento | Cadastro de usuário (`cadastro-usuario.html`): checkbox obrigatório antes de submeter. Log de consentimento gravado com timestamp + IP + versão da política. |
| **Art. 7, IX** — Legítimo interesse | Rate limiting: registro de IP para segurança do sistema. Logs eliminados após 30 dias. |

### Controle de Consentimento (Art. 7, I)

- **Onde:** `frontend/pages/cadastro-usuario.html` — campo `aceite_lgpd`
- **Como:** Checkbox obrigatório antes de submeter formulário; botão de cadastro desabilitado via JS até aceite.
- **Backend:** `backend/auth/register.php` verifica `$_POST['aceite_lgpd'] === '1'`; grava log na tabela `lgpd_consent` (user_id, ip, timestamp, versão da política).
- **Revogação:** Usuário pode excluir a conta via dashboard (Art. 18, VI).

### Transparência de Informação (Art. 9)

- **Onde:** `frontend/pages/index.html` (seção `#lgpd-info`), `cadastro-usuario.html`, `busca.html`
- **Conteúdo obrigatório:**
  - Quais dados são coletados: nome, e-mail, CPF (vinculação NF-e)
  - Finalidade específica de cada dado
  - Tempo de retenção
  - Como exercer direitos do Art. 18
- **Busca pública:** `backend/produto/buscar.php` retorna **apenas** `status` — nunca dados pessoais do titular.
- **Contato anônimo:** `backend/produto/contato.php` — remetente anônimo por design; proprietário notificado sem exposição de dados de quem encontrou.

### Exclusão de Dados (Art. 18, VI e Art. 16)

- **Onde:** `frontend/pages/dashboard.html` (seção `#exclusao-conta`)
- **Fluxo de exclusão de produto:** Botão "Excluir" → soft delete (`objects.deleted_at = NOW()`) → exclusão permanente por cron job após 30 dias.
- **Fluxo de exclusão de conta:** Soft delete em `users` + todos os `objects` → e-mail de confirmação → logout → exclusão permanente após 30 dias.
- **Por que soft delete:** LGPD Art. 16 permite retenção para fins de auditoria e cumprimento de obrigação legal.

### Minimização de Dados (Art. 6, III)

| Endpoint | Dados coletados | Justificativa |
|----------|-----------------|---------------|
| `buscar.php` | Nenhum (GET sem auth) | Busca pública não requer identificação |
| `contato.php` | Texto da mensagem + serial | Mínimo para intermediação anônima |
| `rate_limiter.php` | IP + ação + timestamp | Segurança do sistema (Art. 7, IX) |
| `register.php` | Nome, e-mail, CPF, senha (hash) | Necessários para identificação e vinculação NF-e |

### Medidas de Segurança Técnica (Art. 46)

| Medida | Implementação |
|--------|--------------|
| Hash de senhas | `backend/utils/hash.php` — bcrypt cost 13 |
| Tokens seguros | `random_bytes(32)` → SHA-256 em repouso |
| Sessão hardened | HttpOnly + Secure + SameSite=Strict |
| MFA | TOTP (phishing-resistant) via `backend/auth/mfa.php` |
| CSRF | Token por sessão via `backend/middleware/csrf.php` |
| Rate limiting | Por IP + ação via `backend/middleware/rate_limiter.php` |
| SQL injection | PDO prepared statements, ATTR_EMULATE_PREPARES=false |
| XSS | `htmlspecialchars()` em todos os outputs |
| Headers HTTP | CSP, X-Frame-Options, HSTS, etc. em `backend/config/db.php` |
| Secrets | Isolados em `backend/utils/secrets.php` (gitignoreado) |

---

## Planejamento de Sprints (Entrega: 16/04/2026)

### Sprint 1 — Fundação de Segurança e Cadastro
**Período:** 19/03/2026 (qui) → 25/03/2026 (qua)

| # | Atividade | Data | Status |
|---|-----------|------|--------|
| 1 | Arquitetura: Privacy by Design — revisar que a consulta pública de S/N não exponha dados pessoais | 19–20/03 | 🟡 Em andamento |
| 2 | LGPD: Controle de Consentimento — aceite de termos no cadastro, log na DB | 19–20/03 | 🟡 Em andamento |
| 3 | Segurança: Backend Hashing — configurar Argon2id/Bcrypt, proibir texto plano | 20–21/03 | 🟡 Em andamento |
| 4 | Página de Cadastro de Usuário — e-mail, senha, validação, aceite LGPD | 21–22/03 | 🟡 Em andamento |
| 5 | Exigência de Senha Forte — regex: mín. 8 chars, maiúscula, número, especial | 22–23/03 | 🟡 Em andamento |
| 6 | Validação de Campos por Expressão Regular — todos os inputs do sistema | 23–25/03 | 🟡 Em andamento |

> **Entregável:** Usuário cadastrado com senha segura (hash), consentimento LGPD registrado na DB.

---

### Sprint 2 — Autenticação Completa e Recuperação de Acesso
**Período:** 26/03/2026 (qui) → 01/04/2026 (qua)

| # | Atividade | Data | Status |
|---|-----------|------|--------|
| 7 | Página de Autenticação de Usuário (Login) | 26–27/03 | 📋 Pendente |
| 8 | Página de Confirmação de Cadastro por E-mail | 27–28/03 | 📋 Pendente |
| 9 | MFA obrigatório — TOTP/Google Authenticator (não por e-mail) | 28–30/03 | 📋 Pendente |
| 10 | Recuperação de Senha por E-mail — link seguro, página de redefinição | 30–31/03 | 📋 Pendente |
| 11 | Página de Cadastro de Nova Senha — senha forte, hash, redirect ao login | 31/03–01/04 | 📋 Pendente |

> **Entregável:** Fluxo completo cadastro → confirmação → login → MFA → dashboard. Recuperação de senha funcional.

---

### Sprint 3 — Cadastro de Recursos e Dashboard
**Período:** 02/04/2026 (qui) → 08/04/2026 (qua)

| # | Atividade | Data | Status |
|---|-----------|------|--------|
| 12 | Cadastro de Recursos (Produto) — S/N, foto, NF-e, declaração de primeiro dono | 02–04/04 | 📋 Pendente |
| 13 | Dashboard do Usuário — lista de produtos, alertas de roubo/perda | 04–06/04 | 📋 Pendente |
| 14 | Página de Consulta Pública de S/N — busca sem expor dados pessoais, contato indireto | 06–08/04 | 📋 Pendente |

> **Entregável:** Usuário autenticado cadastra produto e visualiza painel. Consulta pública de S/N funcional.

---

### Sprint 4 — LGPD Completa e Entrega Final
**Período:** 09/04/2026 (qui) → 16/04/2026 (qui)

| # | Atividade | Data | Status |
|---|-----------|------|--------|
| 15 | LGPD: Transparência — vista "Meus Dados" com todas as informações do usuário | 09–11/04 | 📋 Pendente |
| 16 | LGPD: Direito ao Esquecimento — exclusão total/parcial, borrado seguro na DB | 11–13/04 | 📋 Pendente |
| 17 | Testes de integração e correção de bugs | 13–15/04 | 📋 Pendente |
| 18 | Entrega Final — deploy, documentação, apresentação | 16/04 | 📋 Pendente |

> **Entregável:** Sistema completo com todos os 14 requisitos do professor, LGPD de ponta a ponta.

---

### Visão Consolidada

| Sprint | Início | Fim | Foco |
|--------|--------|-----|------|
| Sprint 1 | 19/03/2026 | 25/03/2026 | Privacy by Design, hashing, cadastro de usuário, LGPD consentimento |
| Sprint 2 | 26/03/2026 | 01/04/2026 | Login, confirmação por e-mail, MFA (TOTP), recuperação de senha |
| Sprint 3 | 02/04/2026 | 08/04/2026 | Cadastro de produtos (NF-e), dashboard, consulta pública S/N |
| Sprint 4 | 09/04/2026 | 16/04/2026 | LGPD: transparência e direito ao esquecimento, testes, entrega final |

---

## Licença

Uso interno — Projeto acadêmico PUCPR. Todos os direitos reservados.
