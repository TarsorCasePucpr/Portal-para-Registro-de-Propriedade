SET FOREIGN_KEY_CHECKS = 0;
SET NAMES utf8mb4;
-- implementar melhores views de uso de cada usuário
CREATE TABLE IF NOT EXISTS users (
    id            INT UNSIGNED        AUTO_INCREMENT PRIMARY KEY,
    name          VARCHAR(100)        NOT NULL,
    email         VARCHAR(512)        NOT NULL,
    email_hash    VARCHAR(64)         NOT NULL DEFAULT '',
    cpf           VARCHAR(100)        NOT NULL,
    cpf_hash      VARCHAR(64)         NOT NULL DEFAULT '',
    password_hash VARCHAR(255)        NOT NULL,
    is_active     TINYINT(1)          NOT NULL DEFAULT 0,
    mfa_enabled   TINYINT(1)          NOT NULL DEFAULT 0,
    mfa_secret    VARCHAR(64)         NULL,
    created_at    DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP
                                                ON UPDATE CURRENT_TIMESTAMP,
    deleted_at    DATETIME            NULL,

    UNIQUE INDEX idx_email_hash (email_hash),
    UNIQUE INDEX idx_cpf_hash   (cpf_hash),
    INDEX idx_active  (is_active),
    INDEX idx_deleted (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
 

CREATE TABLE IF NOT EXISTS admin_profiles (
    id               INT UNSIGNED        AUTO_INCREMENT PRIMARY KEY,
    user_id          INT UNSIGNED        NOT NULL UNIQUE,    -- perfil único por usuário
    email            VARCHAR(255)        NOT NULL,           -- email do administrador
    telegram_chat_id VARCHAR(64)         NULL,               -- ID da conversa do Telegram (IMPORTANTE)
    created_at       DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP
                                                    ON UPDATE CURRENT_TIMESTAMP,
 
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_admin_user     (user_id),
    INDEX idx_admin_telegram (telegram_chat_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
 
CREATE TABLE IF NOT EXISTS tokens (
    id          INT UNSIGNED        AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED        NOT NULL,
    token_hash  VARCHAR(64)         NOT NULL UNIQUE,
    type        ENUM('confirm','recovery','mfa_email','admin_otp','admin_email') NOT NULL,
    short_code  VARCHAR(8)          NULL,
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
 
CREATE TABLE IF NOT EXISTS action_logs (
    id         INT UNSIGNED        AUTO_INCREMENT PRIMARY KEY,
    user_id    INT UNSIGNED        NULL,
    role       ENUM('user','admin') NOT NULL DEFAULT 'user',
    action     VARCHAR(80)         NOT NULL,
    entity     VARCHAR(50)         NULL,
    entity_id  INT UNSIGNED        NULL,
    ip         VARCHAR(45)         NOT NULL,
    user_agent VARCHAR(500)        NOT NULL DEFAULT '',
    details    JSON                NULL,
    created_at DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_log_user    (user_id),
    INDEX idx_log_action  (action),
    INDEX idx_log_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- ── Views ────────────────────────────────────────────────────────────────────

CREATE OR REPLACE VIEW v_objects_public AS
    SELECT serial_number, status
    FROM   objects
    WHERE  deleted_at IS NULL;

CREATE OR REPLACE VIEW v_admin_users AS
    SELECT u.id, u.name, u.email, u.cpf, u.is_active, u.mfa_enabled,
           u.created_at, u.deleted_at,
           (SELECT COUNT(*) FROM objects o WHERE o.user_id = u.id AND o.deleted_at IS NULL) AS total_objetos,
           IF(ap.id IS NOT NULL, 1, 0) AS is_admin
    FROM   users u
    LEFT JOIN admin_profiles ap ON ap.user_id = u.id;

CREATE OR REPLACE VIEW v_user_objects AS
    SELECT o.id, o.user_id, o.descricao, o.serial_number, o.status,
           o.nfe_chave, o.nfe_validada, o.data_compra, o.score,
           o.created_at, o.updated_at,
           u.name AS user_name, u.email AS user_email
    FROM   objects o
    JOIN   users u ON u.id = o.user_id
    WHERE  o.deleted_at IS NULL AND u.deleted_at IS NULL;

CREATE OR REPLACE VIEW v_user_object_counts AS
    SELECT u.id AS user_id,
           u.name,
           u.email,
           COUNT(o.id) AS total_objetos,
           SUM(o.status = 'normal') AS objetos_normais,
           SUM(o.status = 'roubado') AS objetos_roubados,
           SUM(o.status = 'perdido') AS objetos_perdidos
    FROM users u
    LEFT JOIN objects o ON o.user_id = u.id AND o.deleted_at IS NULL
    WHERE u.deleted_at IS NULL
    GROUP BY u.id, u.name, u.email;

CREATE OR REPLACE VIEW v_admin_action_logs AS
    SELECT l.id,
           l.created_at,
           l.role,
           l.action,
           l.entity,
           l.entity_id,
           l.ip,
           l.user_agent,
           l.details,
           COALESCE(u.email, '[removido]') AS user_email,
           COALESCE(u.name, '[removido]') AS user_name
    FROM action_logs l
    LEFT JOIN users u ON u.id = l.user_id;

CREATE OR REPLACE VIEW v_lgpd_deletion_summary AS
    SELECT u.id AS user_id,
           u.email,
           r.type,
           COUNT(*) AS total_requests,
           SUM(r.purged_at IS NOT NULL) AS total_purgadas,
           MAX(r.requested_at) AS ultima_solicitacao
    FROM lgpd_deletion_requests r
    JOIN users u ON u.id = r.user_id
    GROUP BY u.id, u.email, r.type;

CREATE OR REPLACE VIEW v_user_is_admin AS
    SELECT u.id AS user_id,
           IF(ap.id IS NOT NULL, 1, 0) AS is_admin
    FROM   users u
    LEFT JOIN admin_profiles ap ON ap.user_id = u.id
    WHERE  u.deleted_at IS NULL AND u.is_active = 1;

-- ── Seed: administrador inicial ──────────────────────────────────────────────
-- Cria o usuário admin e vincula o perfil com chat_id do Telegram.
-- password_hash é de uma senha placeholder — admin nunca usa senha (login é via Telegram OTP).
INSERT IGNORE INTO users (name, email, cpf, password_hash, is_active)
VALUES (
    'Gerard Gonzalez',
    'gerard.gonzalez@pucpr.edu.br',
    '000.000.000-00',
    '$2b$13$n6BnASHfPYZWgogftHVTrO45Ig96Ix1wlZo7N35akTCRVZYPpNeHm',
    1
);

INSERT IGNORE INTO admin_profiles (user_id, email, telegram_chat_id)
SELECT id, email, '8199427665'
FROM   users
WHERE  email = 'gerard.gonzalez@pucpr.edu.br';

CREATE TABLE IF NOT EXISTS admin_security_answers (
    id           INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
    user_id      INT UNSIGNED  NOT NULL UNIQUE,
    answer1_hash VARCHAR(255)  NOT NULL,
    answer2_hash VARCHAR(255)  NOT NULL,
    answer3_hash VARCHAR(255)  NOT NULL,
    answer4_hash VARCHAR(255)  NOT NULL,
    created_at   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP
                                        ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_admin_answers_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;