USE threeebs_control;

CREATE TABLE IF NOT EXISTS interessados (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    uuid CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    nome VARCHAR(150) NOT NULL,
    email VARCHAR(254) NOT NULL,
    empresa VARCHAR(180) NULL,
    tipo_projeto VARCHAR(50) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    momento VARCHAR(50) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    mensagem TEXT NOT NULL,
    consentimento TINYINT(1) NOT NULL DEFAULT 0,
    origem VARCHAR(50) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'portal',
    status VARCHAR(30) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'novo',
    ip_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    PRIMARY KEY (id),
    UNIQUE KEY uq_interessados_uuid (uuid),
    KEY idx_interessados_email (email),
    KEY idx_interessados_status_created (status, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

INSERT INTO schema_migrations (migration, checksum)
VALUES ('014_create_interessados.sql', SHA2('014_create_interessados.sql', 256))
ON DUPLICATE KEY UPDATE migration = VALUES(migration);
