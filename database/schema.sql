-- ============================================================
--  SNGuard — schema.sql
--  Banco: MySQL 8+ / MariaDB 10.6+
--  Charset: utf8mb4 / utf8mb4_unicode_ci
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;
SET NAMES utf8mb4;

-- ── users ────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS users (
    id            INT UNSIGNED        AUTO_INCREMENT PRIMARY KEY,
    name          VARCHAR(100)        NOT NULL,
    email         VARCHAR(255)        NOT NULL UNIQUE,
    cpf           VARCHAR(14)         NOT NULL UNIQUE,   -- formato 000.000.000-00
    password_hash VARCHAR(255)        NOT NULL,
    is_active     TINYINT(1)          NOT NULL DEFAULT 0,
    mfa_enabled   TINYINT(1)          NOT NULL DEFAULT 0,
    mfa_secret    VARCHAR(64)         NULL,              -- TOTP secret (criptografado em produção)
    created_at    DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP
                                                ON UPDATE CURRENT_TIMESTAMP,
    deleted_at    DATETIME            NULL,              -- soft delete (LGPD)

    INDEX idx_email   (email),
    INDEX idx_cpf     (cpf),
    INDEX idx_active  (is_active),
    INDEX idx_deleted (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── tokens ───────────────────────────────────────────────────────
-- confirm      → ativação de conta por e-mail
-- recovery     → redefinição de senha
-- mfa_email    → código 2FA por e-mail (quando sem app TOTP)
CREATE TABLE IF NOT EXISTS tokens (
    id          INT UNSIGNED        AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED        NOT NULL,
    token_hash  VARCHAR(64)         NOT NULL UNIQUE,   -- SHA-256 do token bruto
    type        ENUM('confirm','recovery','mfa_email') NOT NULL,
    expires_at  DATETIME            NOT NULL,
    used_at     DATETIME            NULL,
    created_at  DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_token_hash (token_hash),
    INDEX idx_user_type  (user_id, type),
    INDEX idx_expires    (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── rate_limits ───────────────────────────────────────────────────
-- Armazena tentativas por IP + action para throttling.
-- Limpar registros antigos via cron: DELETE FROM rate_limits WHERE created_at < UNIX_TIMESTAMP() - 3600;
CREATE TABLE IF NOT EXISTS rate_limits (
    id         INT UNSIGNED        AUTO_INCREMENT PRIMARY KEY,
    ip         VARCHAR(45)         NOT NULL,    -- suporta IPv6
    action     VARCHAR(50)         NOT NULL,    -- ex: 'login', 'busca_serial', 'contato'
    created_at INT UNSIGNED        NOT NULL,    -- UNIX timestamp (para comparação rápida)

    INDEX idx_ip_action_time (ip, action, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── lgpd_consent ─────────────────────────────────────────────────
-- Registro do aceite dos termos LGPD no momento do cadastro.
CREATE TABLE IF NOT EXISTS lgpd_consent (
    id             INT UNSIGNED        AUTO_INCREMENT PRIMARY KEY,
    user_id        INT UNSIGNED        NOT NULL,
    policy_version VARCHAR(20)         NOT NULL DEFAULT '1.0',
    ip             VARCHAR(45)         NOT NULL,
    user_agent     VARCHAR(500)        NOT NULL DEFAULT '',
    consented_at   DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_consent (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── lgpd_deletion_requests ───────────────────────────────────────
-- Solicitações de exclusão de dados (Art. 18, VI LGPD).
-- purge_after = created + 30 dias; cron job executa a remoção real.
CREATE TABLE IF NOT EXISTS lgpd_deletion_requests (
    id           INT UNSIGNED        AUTO_INCREMENT PRIMARY KEY,
    user_id      INT UNSIGNED        NOT NULL,
    type         ENUM('partial','total') NOT NULL DEFAULT 'total',
    reason       TEXT                NULL,
    ip           VARCHAR(45)         NOT NULL,
    requested_at DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    purge_after  DATETIME            NOT NULL,           -- requested_at + INTERVAL 30 DAY
    purged_at    DATETIME            NULL,               -- preenchido quando cron executar

    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_deletion   (user_id),
    INDEX idx_purge_scheduled (purge_after, purged_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── objects ───────────────────────────────────────────────────────
-- Produtos registrados pelos usuários.
-- serial_number é único no sistema — impede cadastro duplo do mesmo S/N.
-- status público: normal | roubado | perdido (exposto na busca pública)
-- nfe_chave e score são opcionais (enriquecem o registro via NF)
CREATE TABLE IF NOT EXISTS objects (
    id            INT UNSIGNED        AUTO_INCREMENT PRIMARY KEY,
    user_id       INT UNSIGNED        NOT NULL,
    descricao     VARCHAR(500)        NOT NULL,
    serial_number VARCHAR(100)        NOT NULL UNIQUE,
    status        ENUM('normal','roubado','perdido') NOT NULL DEFAULT 'normal',

    -- Fotos (opcional)
    foto_produto  VARCHAR(512)        NULL,
    foto_serial   VARCHAR(512)        NULL,

    -- Dados opcionais da Nota Fiscal
    nfe_chave     VARCHAR(44)         NULL,    -- chave de acesso da NF-e (44 dígitos)
    nfe_validada  TINYINT(1)          NOT NULL DEFAULT 0,

    data_compra   DATE                NULL,
    score         TINYINT UNSIGNED    NOT NULL DEFAULT 0,  -- 0-100: confiabilidade do cadastro

    created_at    DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP
                                                ON UPDATE CURRENT_TIMESTAMP,
    deleted_at    DATETIME            NULL,    -- soft delete (LGPD Art.18)

    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_serial    (serial_number),
    INDEX idx_user      (user_id),
    INDEX idx_status    (status),
    INDEX idx_deleted   (deleted_at),
    INDEX idx_user_del  (user_id, deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── contact_messages ─────────────────────────────────────────────
-- Mensagens anônimas enviadas por quem encontrou um objeto roubado/perdido.
-- LGPD: remetente é anônimo por design — apenas IP é registrado para rate limiting.
-- O IP é descartável e não associado a nenhum usuário.
CREATE TABLE IF NOT EXISTS contact_messages (
    id           INT UNSIGNED        AUTO_INCREMENT PRIMARY KEY,
    object_id    INT UNSIGNED        NOT NULL,
    mensagem     TEXT                NOT NULL,
    ip_remetente VARCHAR(45)         NOT NULL,   -- somente para auditoria / rate limit
    lida         TINYINT(1)          NOT NULL DEFAULT 0,
    created_at   DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (object_id) REFERENCES objects(id) ON DELETE CASCADE,
    INDEX idx_object  (object_id),
    INDEX idx_lida    (object_id, lida),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- ── View auxiliar (opcional) ─────────────────────────────────────
-- Facilita listagem pública de objetos sem expor dados do dono.
CREATE OR REPLACE VIEW v_objects_public AS
    SELECT serial_number, status
    FROM   objects
    WHERE  deleted_at IS NULL;