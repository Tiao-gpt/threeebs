USE threeebs_work;

CREATE TABLE IF NOT EXISTS tarefas (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    uuid CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    coluna_id BIGINT UNSIGNED NOT NULL,
    titulo VARCHAR(180) NOT NULL,
    descricao TEXT DEFAULT NULL,
    status VARCHAR(30) CHARACTER SET ascii COLLATE ascii_bin
        NOT NULL DEFAULT 'aberta',
    prioridade VARCHAR(30) CHARACTER SET ascii COLLATE ascii_bin
        NOT NULL DEFAULT 'normal',
    ordem INT UNSIGNED NOT NULL DEFAULT 0,
    criado_por_usuario_uuid
        CHAR(36) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
    prazo_em DATETIME(6) DEFAULT NULL,
    concluida_em DATETIME(6) DEFAULT NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6)
        ON UPDATE CURRENT_TIMESTAMP(6),
    PRIMARY KEY (id),
    UNIQUE KEY uq_tarefas_uuid (uuid),
    KEY idx_tarefas_coluna_status_ordem (coluna_id, status, ordem),
    KEY idx_tarefas_prazo (status, prazo_em),
    KEY idx_tarefas_criado_por (criado_por_usuario_uuid),
    CONSTRAINT fk_tarefas_coluna
        FOREIGN KEY (coluna_id) REFERENCES colunas (id)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

INSERT IGNORE INTO schema_migrations (migration)
VALUES ('004_create_tarefas.sql');
