USE threeebs_control;

CREATE TABLE IF NOT EXISTS cliente_usuarios (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    cliente_id BIGINT UNSIGNED NOT NULL,
    usuario_uuid CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    papel VARCHAR(100) CHARACTER SET ascii COLLATE ascii_bin
        NOT NULL DEFAULT 'membro',
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    concedido_por_usuario_uuid
        CHAR(36) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6)
        ON UPDATE CURRENT_TIMESTAMP(6),
    PRIMARY KEY (id),
    UNIQUE KEY uq_cliente_usuarios_vinculo (cliente_id, usuario_uuid),
    KEY idx_cliente_usuarios_usuario_ativo (usuario_uuid, ativo),
    KEY idx_cliente_usuarios_papel_ativo (papel, ativo),
    CONSTRAINT fk_cliente_usuarios_cliente
        FOREIGN KEY (cliente_id) REFERENCES clientes (id)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

INSERT IGNORE INTO schema_migrations (migration)
VALUES ('005_create_cliente_usuarios.sql');
