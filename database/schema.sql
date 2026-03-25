SET FOREIGN_KEY_CHECKS = 0;
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS users (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name          VARCHAR(100)        NOT NULL,
    email         VARCHAR(255)        NOT NULL UNIQUE,
    cpf           VARCHAR(14)         NOT NULL UNIQUE,
    password_hash VARCHAR(255)        NOT NULL,
    is_active     TINYINT(1)          NOT NULL DEFAULT 0,
    mfa_enabled   TINYINT(1)          NOT NULL DEFAULT 0,
    mfa_secret    VARCHAR(64)         NULL,
    created_at    DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP
                                               ON UPDATE CURRENT_TIMESTAMP,
    deleted_at    DATETIME            NULL,
    INDEX idx_email (email),
    INDEX idx_cpf   (cpf),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tokens (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED        NOT NULL,
    token_hash  VARCHAR(64)         NOT NULL UNIQUE,
    type        ENUM('confirm','recovery','mfa_email') NOT NULL,
    expires_at  DATETIME            NOT NULL,
    used_at     DATETIME            NULL,
    created_at  DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_token_hash (token_hash),
    INDEX idx_user_type  (user_id, type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rate_limits (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ip         VARCHAR(45)  NOT NULL,
    action     VARCHAR(50)  NOT NULL,
    created_at INT UNSIGNED NOT NULL,
    INDEX idx_ip_action_time (ip, action, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS lgpd_consent (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id        INT UNSIGNED        NOT NULL,
    ip             VARCHAR(45)         NOT NULL,
    policy_version VARCHAR(10)         NOT NULL DEFAULT '1.0',
    consented_at   DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_consent (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS lgpd_consent (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id        INT UNSIGNED        NOT NULL,
    policy_version VARCHAR(20)         NOT NULL DEFAULT '1.0',
    ip             VARCHAR(45)         NOT NULL,
    user_agent     VARCHAR(500)        NOT NULL DEFAULT '',
    consented_at   DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_consent (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS lgpd_deletion_requests (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id      INT UNSIGNED        NOT NULL,
    type         ENUM('partial','total') NOT NULL,
    ip           VARCHAR(45)         NOT NULL,
    requested_at DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    purge_after  DATETIME            NOT NULL,
    purged_at    DATETIME            NULL,
    INDEX idx_user_deletion   (user_id),
    INDEX idx_purge_scheduled (purge_after, purged_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
