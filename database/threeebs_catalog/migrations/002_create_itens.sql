USE threeebs_catalog;

CREATE TABLE IF NOT EXISTS itens (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    uuid CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    codigo VARCHAR(100) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    nome VARCHAR(180) NOT NULL,
    descricao TEXT DEFAULT NULL,
    tipo VARCHAR(50) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    categoria VARCHAR(80) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    unidade VARCHAR(50) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    status VARCHAR(30) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'ativo',
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6)
        ON UPDATE CURRENT_TIMESTAMP(6),
    PRIMARY KEY (id),
    UNIQUE KEY uq_itens_uuid (uuid),
    UNIQUE KEY uq_itens_codigo (codigo),
    KEY idx_itens_tipo_status (tipo, status),
    KEY idx_itens_categoria_status (categoria, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

INSERT IGNORE INTO schema_migrations (migration)
VALUES ('002_create_itens.sql');
