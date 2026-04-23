SET FOREIGN_KEY_CHECKS = 0;
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS users (
    id            INT UNSIGNED        AUTO_INCREMENT PRIMARY KEY,
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

    INDEX idx_email   (email),
    INDEX idx_cpf     (cpf),
    INDEX idx_active  (is_active),
    INDEX idx_deleted (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tokens (
    id          INT UNSIGNED        AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED        NOT NULL,
    token_hash  VARCHAR(64)         NOT NULL UNIQUE,
    type        ENUM('confirm','recovery','mfa_email') NOT NULL,
    expires_at  DATETIME            NOT NULL,
    used_at     DATETIME            NULL,
    created_at  DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_token_hash (token_hash),
    INDEX idx_user_type  (user_id, type),
    INDEX idx_expires    (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rate_limits (
    id         INT UNSIGNED        AUTO_INCREMENT PRIMARY KEY,
    ip         VARCHAR(45)         NOT NULL,
    action     VARCHAR(50)         NOT NULL,
    created_at INT UNSIGNED        NOT NULL,

    INDEX idx_ip_action_time (ip, action, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

CREATE TABLE IF NOT EXISTS lgpd_deletion_requests (
    id           INT UNSIGNED        AUTO_INCREMENT PRIMARY KEY,
    user_id      INT UNSIGNED        NOT NULL,
    type         ENUM('partial','total') NOT NULL DEFAULT 'total',
    reason       TEXT                NULL,
    ip           VARCHAR(45)         NOT NULL,
    requested_at DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    purge_after  DATETIME            NOT NULL,
    purged_at    DATETIME            NULL,

    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_deletion   (user_id),
    INDEX idx_purge_scheduled (purge_after, purged_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS objects (
    id            INT UNSIGNED        AUTO_INCREMENT PRIMARY KEY,
    user_id       INT UNSIGNED        NOT NULL,
    descricao     VARCHAR(500)        NOT NULL,
    serial_number VARCHAR(100)        NOT NULL UNIQUE,
    status        ENUM('normal','roubado','perdido') NOT NULL DEFAULT 'normal',
    nfe_chave     VARCHAR(44)         NULL,
    nfe_validada  TINYINT(1)          NOT NULL DEFAULT 0,
    data_compra   DATE                NULL,
    score         TINYINT UNSIGNED    NOT NULL DEFAULT 0,
    created_at    DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP
                                                ON UPDATE CURRENT_TIMESTAMP,
    deleted_at    DATETIME            NULL,

    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_serial    (serial_number),
    INDEX idx_user      (user_id),
    INDEX idx_status    (status),
    INDEX idx_deleted   (deleted_at),
    INDEX idx_user_del  (user_id, deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS contact_messages (
    id           INT UNSIGNED        AUTO_INCREMENT PRIMARY KEY,
    object_id    INT UNSIGNED        NOT NULL,
    mensagem     TEXT                NOT NULL,
    ip_remetente VARCHAR(45)         NOT NULL,
    lida         TINYINT(1)          NOT NULL DEFAULT 0,
    created_at   DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (object_id) REFERENCES objects(id) ON DELETE CASCADE,
    INDEX idx_object  (object_id),
    INDEX idx_lida    (object_id, lida),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

CREATE OR REPLACE VIEW v_objects_public AS
    SELECT serial_number, status
    FROM   objects
    WHERE  deleted_at IS NULL;
