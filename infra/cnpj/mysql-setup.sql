-- ============================================================
-- NEWTONIA — Setup completo MySQL
-- Rodar no banco `newtonia`
-- ============================================================

-- Usuários
CREATE TABLE IF NOT EXISTS users (
    id              INT UNSIGNED    AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(100)    NOT NULL,
    email           VARCHAR(200)    NOT NULL UNIQUE,
    password_hash   VARCHAR(255)    NOT NULL,
    is_super_admin  TINYINT(1)      NOT NULL DEFAULT 0,
    last_login_at   DATETIME        DEFAULT NULL,
    created_at      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tenants
CREATE TABLE IF NOT EXISTS tenants (
    id              INT UNSIGNED    AUTO_INCREMENT PRIMARY KEY,
    slug            VARCHAR(100)    NOT NULL UNIQUE,
    name            VARCHAR(200)    NOT NULL,
    brand_name      VARCHAR(200)    DEFAULT NULL,
    brand_color     VARCHAR(7)      NOT NULL DEFAULT '#6366f1',
    status          ENUM('active','pending','suspended','cancelled') NOT NULL DEFAULT 'active',
    plan_id         INT UNSIGNED    DEFAULT NULL,
    cnpj_plan_id        INT UNSIGNED  DEFAULT NULL,
    cnpj_limit_override INT UNSIGNED  DEFAULT NULL,
    cnpj_addon_credits  INT UNSIGNED  NOT NULL DEFAULT 0,
    created_at      TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Relação usuário ↔ tenant
CREATE TABLE IF NOT EXISTS tenant_users (
    id          INT UNSIGNED    AUTO_INCREMENT PRIMARY KEY,
    tenant_id   INT UNSIGNED    NOT NULL,
    user_id     INT UNSIGNED    NOT NULL,
    role        VARCHAR(50)     NOT NULL DEFAULT 'member',
    created_at  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_tenant_user (tenant_id, user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Audit log
CREATE TABLE IF NOT EXISTS audit_log (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id   INT UNSIGNED    DEFAULT NULL,
    user_id     INT UNSIGNED    DEFAULT NULL,
    action      VARCHAR(100)    NOT NULL,
    target_type VARCHAR(50)     DEFAULT NULL,
    target_id   VARCHAR(50)     DEFAULT NULL,
    meta        JSON            DEFAULT NULL,
    ip          VARCHAR(45)     DEFAULT NULL,
    created_at  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- CNPJ Plans
CREATE TABLE IF NOT EXISTS cnpj_plans (
    id            INT UNSIGNED   AUTO_INCREMENT PRIMARY KEY,
    name          VARCHAR(50)    NOT NULL,
    monthly_limit INT UNSIGNED   NOT NULL,
    price_monthly DECIMAL(10,2)  NOT NULL,
    active        TINYINT(1)     NOT NULL DEFAULT 1,
    created_at    TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- CNPJ Addon Packs
CREATE TABLE IF NOT EXISTS cnpj_addon_packs (
    id         INT UNSIGNED   AUTO_INCREMENT PRIMARY KEY,
    quantity   INT UNSIGNED   NOT NULL,
    price      DECIMAL(10,2)  NOT NULL,
    active     TINYINT(1)     NOT NULL DEFAULT 1,
    created_at TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- CNPJ Download Log
CREATE TABLE IF NOT EXISTS cnpj_download_log (
    id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id     INT UNSIGNED    NOT NULL,
    downloaded_at TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    records_count INT UNSIGNED    NOT NULL DEFAULT 0,
    filters_json  TEXT            DEFAULT NULL,
    INDEX idx_tenant_month (tenant_id, downloaded_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- CNPJ Addon Purchases
CREATE TABLE IF NOT EXISTS cnpj_addon_purchases (
    id           INT UNSIGNED   AUTO_INCREMENT PRIMARY KEY,
    tenant_id    INT UNSIGNED   NOT NULL,
    pack_id      INT UNSIGNED   NOT NULL,
    quantity     INT UNSIGNED   NOT NULL,
    price_paid   DECIMAL(10,2)  NOT NULL,
    purchased_at TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_tenant (tenant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- CNPJ Lists
CREATE TABLE IF NOT EXISTS cnpj_lists (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id   INT UNSIGNED NOT NULL,
    name        VARCHAR(200) NOT NULL,
    description TEXT,
    filter_json JSON,
    item_count  INT UNSIGNED NOT NULL DEFAULT 0,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_tenant (tenant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- CNPJ List Items
CREATE TABLE IF NOT EXISTS cnpj_list_items (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    list_id       INT UNSIGNED    NOT NULL,
    tenant_id     INT UNSIGNED    NOT NULL,
    cnpj          CHAR(14)        NOT NULL,
    razao_social  VARCHAR(300)    NOT NULL DEFAULT '',
    nome_fantasia VARCHAR(200)    NOT NULL DEFAULT '',
    uf            CHAR(2)         NOT NULL DEFAULT '',
    municipio     VARCHAR(200)    NOT NULL DEFAULT '',
    cnae          CHAR(7)         NOT NULL DEFAULT '',
    cnae_desc     VARCHAR(300)    NOT NULL DEFAULT '',
    telefone      VARCHAR(20)     NOT NULL DEFAULT '',
    email         VARCHAR(115)    NOT NULL DEFAULT '',
    added_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_list_cnpj (list_id, cnpj),
    INDEX idx_list   (list_id),
    INDEX idx_tenant (tenant_id),
    CONSTRAINT fk_cnpj_list FOREIGN KEY (list_id)
        REFERENCES cnpj_lists(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Dados iniciais ────────────────────────────────────────────────────────────

INSERT IGNORE INTO cnpj_plans (name, monthly_limit, price_monthly) VALUES
    ('Starter', 100, 49.90), ('Basic', 200, 89.90),
    ('Professional', 300, 129.90), ('Business', 500, 199.90);

INSERT IGNORE INTO cnpj_addon_packs (quantity, price) VALUES
    (100, 19.90), (1000, 149.90);

-- Usuário admin: admin@newtonia.com / admin123
INSERT IGNORE INTO users (name, email, password_hash, is_super_admin)
VALUES ('Admin', 'admin@newtonia.com',
        '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1);

-- Tenant de teste
INSERT IGNORE INTO tenants (slug, name, brand_name, brand_color, status, cnpj_plan_id)
VALUES ('newtonia', 'Newtonia', 'Newtonia', '#6366f1', 'active', 1);

-- Liga admin ao tenant
INSERT IGNORE INTO tenant_users (tenant_id, user_id, role)
VALUES (1, 1, 'admin');
