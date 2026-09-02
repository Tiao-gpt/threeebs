USE threeebs_control;

CREATE TABLE IF NOT EXISTS configuracao_instalacao (
    id TINYINT UNSIGNED NOT NULL,
    primeiro_administrador_usuario_uuid
        CHAR(36) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
    status VARCHAR(30) CHARACTER SET ascii COLLATE ascii_bin
        NOT NULL DEFAULT 'pendente',
    inicializada_em DATETIME(6) DEFAULT NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6)
        ON UPDATE CURRENT_TIMESTAMP(6),
    PRIMARY KEY (id),
    CONSTRAINT chk_configuracao_instalacao_unica CHECK (id = 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

INSERT IGNORE INTO configuracao_instalacao (id) VALUES (1);
INSERT IGNORE INTO schema_migrations (migration)
VALUES ('002_create_configuracao_instalacao.sql');
