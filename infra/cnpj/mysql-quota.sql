-- Newton CNPJ — Quota mensal de downloads
-- Executar no banco principal da Newtonia

-- 1. Campo nas tabelas de planos e tenants
ALTER TABLE plans
    ADD COLUMN IF NOT EXISTS limit_cnpj_monthly INT NOT NULL DEFAULT 1000
    COMMENT 'Máximo de leads CNPJ exportados por mês';

ALTER TABLE tenants
    ADD COLUMN IF NOT EXISTS limit_cnpj_monthly INT NOT NULL DEFAULT 1000
    COMMENT 'Máximo de leads CNPJ exportados por mês (pode sobrescrever o plano)';

-- 2. Log de downloads por tenant/mês
CREATE TABLE IF NOT EXISTS cnpj_download_log (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id   INT UNSIGNED    NOT NULL,
    year_month  CHAR(7)         NOT NULL COMMENT 'YYYY-MM',
    rows_count  INT UNSIGNED    NOT NULL DEFAULT 0,
    updated_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_tenant_month (tenant_id, year_month),
    INDEX idx_tenant (tenant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
