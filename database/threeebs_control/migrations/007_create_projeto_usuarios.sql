USE threeebs_control;

CREATE TABLE IF NOT EXISTS projeto_usuarios (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    projeto_id BIGINT UNSIGNED NOT NULL,
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
    UNIQUE KEY uq_projeto_usuarios_vinculo (projeto_id, usuario_uuid),
    KEY idx_projeto_usuarios_usuario_ativo (usuario_uuid, ativo),
    KEY idx_projeto_usuarios_papel_ativo (papel, ativo),
    CONSTRAINT fk_projeto_usuarios_projeto
        FOREIGN KEY (projeto_id) REFERENCES projetos (id)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

INSERT IGNORE INTO schema_migrations (migration)
VALUES ('007_create_projeto_usuarios.sql');
