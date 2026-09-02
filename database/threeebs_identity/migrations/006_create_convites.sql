USE threeebs_identity;

CREATE TABLE IF NOT EXISTS convites (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    uuid CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    email VARCHAR(190) NOT NULL,
    finalidade VARCHAR(50) CHARACTER SET ascii COLLATE ascii_bin
        NOT NULL DEFAULT 'acesso',
    token_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    convidado_por_usuario_id BIGINT UNSIGNED DEFAULT NULL,
    aceito_por_usuario_id BIGINT UNSIGNED DEFAULT NULL,
    expira_em DATETIME(6) NOT NULL,
    aceito_em DATETIME(6) DEFAULT NULL,
    revogado_em DATETIME(6) DEFAULT NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6)
        ON UPDATE CURRENT_TIMESTAMP(6),
    PRIMARY KEY (id),
    UNIQUE KEY uq_convites_uuid (uuid),
    UNIQUE KEY uq_convites_token_hash (token_hash),
    KEY idx_convites_email_data (email, created_at),
    KEY idx_convites_expira (expira_em),
    CONSTRAINT fk_convites_convidado_por
        FOREIGN KEY (convidado_por_usuario_id) REFERENCES usuarios (id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_convites_aceito_por
        FOREIGN KEY (aceito_por_usuario_id) REFERENCES usuarios (id)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

INSERT IGNORE INTO schema_migrations (migration)
VALUES ('006_create_convites.sql');
