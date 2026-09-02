USE threeebs_finance;

CREATE TABLE IF NOT EXISTS contrapartes (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    uuid CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    tipo VARCHAR(50) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    nome VARCHAR(180) NOT NULL,
    documento VARCHAR(50) DEFAULT NULL,
    email VARCHAR(190) DEFAULT NULL,
    status VARCHAR(30) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'ativa',
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6)
        ON UPDATE CURRENT_TIMESTAMP(6),
    PRIMARY KEY (id),
    UNIQUE KEY uq_contrapartes_uuid (uuid),
    KEY idx_contrapartes_tipo_status (tipo, status),
    KEY idx_contrapartes_documento (documento),
    KEY idx_contrapartes_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

INSERT IGNORE INTO schema_migrations (migration)
VALUES ('004_create_contrapartes.sql');
