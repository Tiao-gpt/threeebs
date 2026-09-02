USE threeebs_control;

CREATE TABLE IF NOT EXISTS rotas_web (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    uuid CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    ambiente_id BIGINT UNSIGNED NOT NULL,
    hostname VARCHAR(253) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    tipo VARCHAR(50) CHARACTER SET ascii COLLATE ascii_bin
        NOT NULL DEFAULT 'dominio',
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6)
        ON UPDATE CURRENT_TIMESTAMP(6),
    PRIMARY KEY (id),
    UNIQUE KEY uq_rotas_web_uuid (uuid),
    UNIQUE KEY uq_rotas_web_hostname (hostname),
    KEY idx_rotas_web_ambiente_ativo (ambiente_id, ativo),
    CONSTRAINT chk_rotas_web_hostname CHECK (
        hostname = LOWER(hostname)
        AND hostname NOT LIKE '%://%'
        AND hostname NOT LIKE '%/%'
        AND hostname NOT LIKE '% %'
    ),
    CONSTRAINT fk_rotas_web_ambiente
        FOREIGN KEY (ambiente_id) REFERENCES ambientes (id)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

INSERT IGNORE INTO schema_migrations (migration)
VALUES ('009_create_rotas_web.sql');
