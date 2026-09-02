USE threeebs_identity;

CREATE TABLE IF NOT EXISTS eventos_autenticacao (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    usuario_id BIGINT UNSIGNED DEFAULT NULL,
    tipo VARCHAR(50) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    sucesso TINYINT(1) NOT NULL DEFAULT 0,
    identificador_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
    ip_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
    user_agent_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
    detalhes JSON DEFAULT NULL,
    ocorrido_em DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    PRIMARY KEY (id),
    KEY idx_eventos_auth_usuario_data (usuario_id, ocorrido_em),
    KEY idx_eventos_auth_tipo_data (tipo, ocorrido_em),
    KEY idx_eventos_auth_identificador_data (identificador_hash, ocorrido_em),
    CONSTRAINT fk_eventos_auth_usuario
        FOREIGN KEY (usuario_id) REFERENCES usuarios (id)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

INSERT IGNORE INTO schema_migrations (migration)
VALUES ('007_create_eventos_autenticacao.sql');
