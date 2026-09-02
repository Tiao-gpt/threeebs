USE threeebs_identity;

CREATE TABLE IF NOT EXISTS credenciais (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    uuid CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    usuario_id BIGINT UNSIGNED NOT NULL,
    tipo VARCHAR(50) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'senha',
    identificador VARCHAR(190) NOT NULL DEFAULT '',
    segredo_hash VARCHAR(255) NOT NULL,
    ativa TINYINT(1) NOT NULL DEFAULT 1,
    expira_em DATETIME(6) DEFAULT NULL,
    ultimo_uso_em DATETIME(6) DEFAULT NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6)
        ON UPDATE CURRENT_TIMESTAMP(6),
    PRIMARY KEY (id),
    UNIQUE KEY uq_credenciais_uuid (uuid),
    UNIQUE KEY uq_credenciais_usuario_tipo_identificador
        (usuario_id, tipo, identificador),
    KEY idx_credenciais_tipo_ativa (tipo, ativa),
    CONSTRAINT fk_credenciais_usuario
        FOREIGN KEY (usuario_id) REFERENCES usuarios (id)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

INSERT IGNORE INTO schema_migrations (migration)
VALUES ('003_create_credenciais.sql');
