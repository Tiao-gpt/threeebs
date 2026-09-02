USE threeebs_control;

CREATE TABLE IF NOT EXISTS ambientes (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    uuid CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    projeto_id BIGINT UNSIGNED NOT NULL,
    servidor_id BIGINT UNSIGNED NOT NULL,
    tipo VARCHAR(50) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    nome VARCHAR(100) NOT NULL,
    slug VARCHAR(100) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    diretorio VARCHAR(255) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    status VARCHAR(30) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'ativo',
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    PRIMARY KEY (id),
    UNIQUE KEY uq_ambientes_uuid (uuid),
    UNIQUE KEY uq_ambientes_projeto_tipo (projeto_id, tipo),
    UNIQUE KEY uq_ambientes_projeto_slug (projeto_id, slug),
    UNIQUE KEY uq_ambientes_servidor_diretorio (servidor_id, diretorio),
    KEY idx_ambientes_servidor_status (servidor_id, status),
    CONSTRAINT fk_ambientes_projeto FOREIGN KEY (projeto_id) REFERENCES projetos (id) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_ambientes_servidor FOREIGN KEY (servidor_id) REFERENCES servidores (id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

INSERT IGNORE INTO schema_migrations (migration)
VALUES ('008_create_ambientes.sql');
