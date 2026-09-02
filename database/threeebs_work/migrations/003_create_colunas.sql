USE threeebs_work;

CREATE TABLE IF NOT EXISTS colunas (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    uuid CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    quadro_id BIGINT UNSIGNED NOT NULL,
    nome VARCHAR(120) NOT NULL,
    slug VARCHAR(100) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    ordem SMALLINT UNSIGNED NOT NULL,
    status VARCHAR(30) CHARACTER SET ascii COLLATE ascii_bin
        NOT NULL DEFAULT 'ativa',
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6)
        ON UPDATE CURRENT_TIMESTAMP(6),
    PRIMARY KEY (id),
    UNIQUE KEY uq_colunas_uuid (uuid),
    UNIQUE KEY uq_colunas_quadro_slug (quadro_id, slug),
    UNIQUE KEY uq_colunas_quadro_ordem (quadro_id, ordem),
    KEY idx_colunas_quadro_status (quadro_id, status, ordem),
    CONSTRAINT fk_colunas_quadro
        FOREIGN KEY (quadro_id) REFERENCES quadros (id)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

INSERT IGNORE INTO schema_migrations (migration)
VALUES ('003_create_colunas.sql');
