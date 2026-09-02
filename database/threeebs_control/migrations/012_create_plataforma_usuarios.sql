USE threeebs_control;

CREATE TABLE IF NOT EXISTS plataforma_usuarios (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    uuid CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    usuario_uuid CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    papel VARCHAR(50) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    concedido_por_usuario_uuid
        CHAR(36) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6)
        ON UPDATE CURRENT_TIMESTAMP(6),
    PRIMARY KEY (id),
    UNIQUE KEY uq_plataforma_usuarios_uuid (uuid),
    UNIQUE KEY uq_plataforma_usuarios_usuario (usuario_uuid),
    KEY idx_plataforma_usuarios_papel_ativo (papel, ativo),
    KEY idx_plataforma_usuarios_concedido_por (concedido_por_usuario_uuid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

INSERT IGNORE INTO schema_migrations (migration)
VALUES ('012_create_plataforma_usuarios.sql');
