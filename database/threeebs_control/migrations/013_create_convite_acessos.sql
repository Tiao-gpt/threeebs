USE threeebs_control;

CREATE TABLE IF NOT EXISTS convite_acessos (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    uuid CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    convite_uuid CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    cliente_id BIGINT UNSIGNED NOT NULL,
    projeto_id BIGINT UNSIGNED DEFAULT NULL,
    papel_cliente VARCHAR(50) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    papel_projeto VARCHAR(50) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
    status VARCHAR(30) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'pendente',
    aplicado_em DATETIME(6) DEFAULT NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6)
        ON UPDATE CURRENT_TIMESTAMP(6),
    PRIMARY KEY (id),
    UNIQUE KEY uq_convite_acessos_uuid (uuid),
    UNIQUE KEY uq_convite_acessos_convite (convite_uuid),
    KEY idx_convite_acessos_cliente_status (cliente_id, status),
    KEY idx_convite_acessos_projeto_status (projeto_id, status),
    CONSTRAINT fk_convite_acessos_cliente
        FOREIGN KEY (cliente_id) REFERENCES clientes (id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_convite_acessos_projeto
        FOREIGN KEY (projeto_id) REFERENCES projetos (id)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

INSERT IGNORE INTO schema_migrations (migration)
VALUES ('013_create_convite_acessos.sql');
