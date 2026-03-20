-- Banco de dados: portal_propriedade
-- Criado: Março 2026
-- Motor: MySQL 8.0+
-- ⚠️ Nunca modificar manualmente — sempre atualizar este arquivo

SET FOREIGN_KEY_CHECKS = 0;
SET NAMES utf8mb4;

-- ─────────────────────────────────────────────
-- USERS
-- CPF é obrigatório: chave de vinculação com NF-e (destinatario/CPF)
-- ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS users (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name          VARCHAR(100)        NOT NULL,
    email         VARCHAR(255)        NOT NULL UNIQUE,
    cpf           VARCHAR(14)         NOT NULL UNIQUE,  -- "000.000.000-00" — chave NF-e
    password_hash VARCHAR(255)        NOT NULL,          -- bcrypt cost 13
    is_active     TINYINT(1)          NOT NULL DEFAULT 0,
    mfa_enabled   TINYINT(1)          NOT NULL DEFAULT 0,
    mfa_secret    VARCHAR(64)         NULL,              -- TOTP secret (encriptado)
    created_at    DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP
                                               ON UPDATE CURRENT_TIMESTAMP,
    deleted_at    DATETIME            NULL,              -- soft delete
    INDEX idx_email (email),
    INDEX idx_cpf   (cpf),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────
-- TOKENS (confirmação de email + recuperação de senha)
-- ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS tokens (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED        NOT NULL,
    token_hash  VARCHAR(64)         NOT NULL UNIQUE,  -- hash('sha256', $rawToken)
    type        ENUM('confirm','recovery','mfa_email') NOT NULL,
    expires_at  DATETIME            NOT NULL,
    used_at     DATETIME            NULL,             -- NULL = não usado ainda
    created_at  DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_token_hash (token_hash),
    INDEX idx_user_type  (user_id, type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────
-- RATE LIMITS
-- ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS rate_limits (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ip         VARCHAR(45)  NOT NULL,                 -- IPv4 e IPv6
    action     VARCHAR(50)  NOT NULL,                 -- 'login', 'mfa', 'contact', 'nfe_query'
    created_at INT UNSIGNED NOT NULL,                 -- Unix timestamp
    INDEX idx_ip_action_time (ip, action, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────
-- LGPD CONSENT
-- Registra o aceite dos termos (Art. 7, I LGPD)
-- ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS lgpd_consent (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id        INT UNSIGNED        NOT NULL,
    ip             VARCHAR(45)         NOT NULL,
    policy_version VARCHAR(10)         NOT NULL DEFAULT '1.0',
    consented_at   DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_consent (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
