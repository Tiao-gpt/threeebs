USE threeebs_control;

CREATE TABLE IF NOT EXISTS servicos_ambiente (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    uuid CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    ambiente_id BIGINT UNSIGNED NOT NULL,
    tipo VARCHAR(50) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    nome VARCHAR(100) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'default',
    status VARCHAR(30) CHARACTER SET ascii COLLATE ascii_bin
        NOT NULL DEFAULT 'ativo',
    configuracao JSON DEFAULT NULL,
    credential_ref VARCHAR(255) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6)
        ON UPDATE CURRENT_TIMESTAMP(6),
    PRIMARY KEY (id),
    UNIQUE KEY uq_servicos_ambiente_uuid (uuid),
    UNIQUE KEY uq_servicos_ambiente_tipo_nome (ambiente_id, tipo, nome),
    KEY idx_servicos_ambiente_status (ambiente_id, status),
    CONSTRAINT fk_servicos_ambiente_ambiente
        FOREIGN KEY (ambiente_id) REFERENCES ambientes (id)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

INSERT IGNORE INTO schema_migrations (migration)
VALUES ('010_create_servicos_ambiente.sql');
