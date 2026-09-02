USE threeebs_identity;

CREATE TABLE IF NOT EXISTS sessoes (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    uuid CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    usuario_id BIGINT UNSIGNED NOT NULL,
    token_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    ip_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
    user_agent_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
    expira_em DATETIME(6) NOT NULL,
    ultimo_acesso_em DATETIME(6) DEFAULT NULL,
    revogada_em DATETIME(6) DEFAULT NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6)
        ON UPDATE CURRENT_TIMESTAMP(6),
    PRIMARY KEY (id),
    UNIQUE KEY uq_sessoes_uuid (uuid),
    UNIQUE KEY uq_sessoes_token_hash (token_hash),
    KEY idx_sessoes_usuario_expira (usuario_id, expira_em),
    KEY idx_sessoes_expira (expira_em),
    CONSTRAINT fk_sessoes_usuario
        FOREIGN KEY (usuario_id) REFERENCES usuarios (id)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

INSERT IGNORE INTO schema_migrations (migration)
VALUES ('004_create_sessoes.sql');
