USE threeebs_identity;

CREATE TABLE IF NOT EXISTS usuarios (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    uuid CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    nome VARCHAR(150) DEFAULT NULL,
    email VARCHAR(190) NOT NULL,
    status VARCHAR(30) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'ativo',
    bloqueado_ate DATETIME(6) DEFAULT NULL,
    bloqueio_motivo VARCHAR(50) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
    email_verificado_em DATETIME(6) DEFAULT NULL,
    ultimo_login_em DATETIME(6) DEFAULT NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6)
        ON UPDATE CURRENT_TIMESTAMP(6),
    PRIMARY KEY (id),
    UNIQUE KEY uq_usuarios_uuid (uuid),
    UNIQUE KEY uq_usuarios_email (email),
    KEY idx_usuarios_status (status),
    KEY idx_usuarios_bloqueado_ate (bloqueado_ate)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

INSERT IGNORE INTO schema_migrations (migration)
VALUES ('002_create_usuarios.sql');
