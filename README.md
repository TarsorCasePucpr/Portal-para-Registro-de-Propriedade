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
| 1 | Home / Landing | `index.html` | 📋 Planejamento |
| 2 | Busca pública por serial | `busca.html` | 📋 Planejamento |
| 3 | Login | `login.html` | 📋 Planejamento |
| 4 | Cadastro de usuário | `cadastro-usuario.html` | 📋 Planejamento |
| 5 | Confirmação de cadastro (email) | `confirmacao.html` | 📋 Planejamento |
| 6 | Autenticação MFA | `mfa.html` | 📋 Planejamento |
| 7 | Recuperação de senha (solicitar link) | `recuperacao-senha.html` | 📋 Planejamento |
| 8 | Redefinição de senha (nova senha via link) | `redefinicao-senha.html` | 📋 Planejamento |
| 9 | Cadastro de produto | `cadastro-produto.html` | 📋 Planejamento |
| 10 | Dashboard do usuário | `dashboard.html` | 📋 Planejamento |

> **Nota:** Os arquivos das páginas 1–7 e 9–10 existem no repositório como estrutura inicial. A página 8 (`redefinicao-senha.html`) ainda precisa ser criada. Nenhuma página está implementada — todas estão em fase de planejamento.
>
> A página 7 (recuperacao-senha) e a página 8 (redefinicao-senha) são distintas: a 7 é o formulário onde o usuário informa o email para receber o link; a 8 é o formulário para definir a nova senha após clicar no link.

---

### Checklists por página

**Home / index.html**
- [ ] Título da organização: SNGuard
- [ ] Botão para a página de login
- [ ] Botão para acessar a página de consulta por S/N
- [ ] Botão para página de cadastro
- [ ] Descrição das atividades e objetivos sociais

**Busca por S/N (busca.html)**
- [ ] Campo de busca por número de série
- [ ] Exibir dono registrado e status do objeto (sem expor dados pessoais)
- [ ] Sistema de contato / agendamento em delegacia

**Cadastro de usuário (cadastro-usuario.html)**
- [ ] Validação de email
- [ ] Campo de senha oculto com opção de mostrar
- [ ] Botões de login e registro
- [ ] Conformidade com LGPD (consentimento)

**Confirmação de cadastro (confirmacao.html)**
- [ ] Envio de link por email
- [ ] Mensagem de sucesso ao usuário
- [ ] Redirecionamento para MFA após confirmação

**Autenticação MFA (mfa.html)**
- [ ] Sugerir método (TOTP preferido / email OTP como fallback)
- [ ] Apresentar campo para código
- [ ] Validar código e redirecionar ao dashboard

**Recuperação de senha (recuperacao-senha.html)**
- [ ] Input de email com validação
- [ ] Lógica de envio de token/link
- [ ] Feedback visual de "E-mail enviado"

**Redefinição de senha (redefinicao-senha.html) ← A CRIAR**
- [ ] Inputs de nova senha e confirmação com máscara
- [ ] Verificação de igualdade entre os campos
- [ ] Validação de requisitos (caracteres especiais, números, etc.)
- [ ] Atualização no banco de dados via token válido

**Cadastro de produto (cadastro-produto.html)**
- [ ] Formulário: nome do produto, marca/modelo, número de série (S/N)
- [ ] Opção de upload de imagem ou comprovante (Nota Fiscal)
- [ ] Vinculação automática com o ID do usuário logado
- [ ] Botão de salvar cadastro

**Dashboard (dashboard.html)**
- [ ] Grid ou lista de produtos cadastrados
- [ ] Status de cada item ("Protegido" ou "Alerta")
- [ ] Sidebar de navegação
- [ ] Resumo de notificações
- [ ] Atalhos rápidos para novo cadastro e perfil

### Backend — Auth

| Arquivo | Função | Status |
|---------|--------|--------|
| `auth/register.php` | Registro de usuário | 📋 Planejamento |
| `auth/confirm.php` | Confirmação de email | 📋 Planejamento |
| `auth/login.php` | Login + sessão | 📋 Planejamento |
| `auth/logout.php` | Logout seguro | 📋 Planejamento |
| `auth/mfa.php` | Verificação MFA | 📋 Planejamento |
| `auth/recover.php` | Recuperação de senha | 📋 Planejamento |

### Backend — Produto

| Arquivo | Função | Status |
|---------|--------|--------|
| `produto/cadastrar.php` | Registrar objeto com serial | 📋 Planejamento |
| `produto/listar.php` | Listar objetos do usuário | 📋 Planejamento |
| `produto/buscar.php` | Busca pública por serial | 📋 Planejamento |
| `produto/status.php` | Alterar status (roubado/perdido) | 📋 Planejamento |
| `produto/contato.php` | Envio de mensagem ao dono via delegacia | 📋 Planejamento |

### Backend — Middleware

| Arquivo | Função | Status |
|---------|--------|--------|
| `middleware/auth_guard.php` | Proteger rotas autenticadas | 📋 Planejamento |
| `middleware/csrf.php` | Geração e validação de token CSRF | 📋 Planejamento |
| `middleware/rate_limiter.php` | Limite de tentativas por IP | 📋 Planejamento |

### Backend — Utils

| Arquivo | Função | Status |
|---------|--------|--------|
| `utils/hash.php` | `hashPassword()` + `verifyPassword()` bcrypt 13 | 📋 Planejamento |
| `utils/mailer.php` | PHPMailer — envio de emails | 📋 Planejamento |
| `utils/secrets.php` | Carregamento de credenciais (gitignoreado) | 📋 Planejamento |
| `utils/response.php` | `jsonSuccess()` + `jsonError()` padronizados | 📋 Planejamento |

### Banco de dados

| Tabela | Descrição | Status |
|--------|-----------|--------|
| `users` | Usuários cadastrados (com CPF para NF-e) | 📋 Planejamento |
| `tokens` | Tokens de confirmação e recuperação | 📋 Planejamento |
| `rate_limits` | Controle de tentativas por IP | 📋 Planejamento |
| `objects` | Objetos registrados por serial | 📋 Planejamento |
| `contact_messages` | Mensagens de objetos encontrados | 📋 Planejamento |

> **Nota:** O schema das tabelas `users`, `tokens` e `rate_limits` já está definido em `database/schema.sql`. As tabelas `objects` e `contact_messages` estão documentadas como TODO no schema — ainda a modelar.

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
│       ├── index.html               # Home / Landing
│       ├── busca.html               # Busca pública por serial
│       ├── login.html               # Login
│       ├── cadastro-usuario.html    # Cadastro de usuário
│       ├── confirmacao.html         # Confirmação de cadastro por email
│       ├── recuperacao-senha.html   # (7) Solicitar link de recuperação
│       ├── redefinicao-senha.html   # (8) Definir nova senha via link ⏳ A criar
│       ├── mfa.html                 # (6) Autenticação 2 fatores
│       ├── cadastro-produto.html    # (9) Registro de objeto com serial
│       └── dashboard.html           # (10) Painel do usuário
├── backend/
│   ├── config/
│   │   └── db.php                   # Conexão PDO (singleton)
│   ├── auth/
│   │   ├── login.php
│   │   ├── logout.php
│   │   ├── register.php
│   │   ├── confirm.php
│   │   ├── recover.php
│   │   └── mfa.php
│   ├── middleware/                  # ⏳ A criar
│   │   ├── auth_guard.php
│   │   ├── csrf.php
│   │   └── rate_limiter.php
│   ├── produto/
│   │   ├── cadastrar.php
│   │   ├── listar.php
│   │   ├── buscar.php
│   │   ├── status.php
│   │   └── contato.php
│   └── utils/
│       ├── hash.php
│       ├── mailer.php
│       ├── secrets.php              # Gitignoreado — não commitar
│       └── response.php             # ⏳ A criar
├── database/
│   └── schema.sql                   # users, tokens, rate_limits ✅ | objects, contact_messages ⏳
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

## Licença

Uso interno — Projeto acadêmico PUCPR. Todos os direitos reservados.
