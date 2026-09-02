USE threeebs_work;

CREATE TABLE IF NOT EXISTS tarefa_responsaveis (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    tarefa_id BIGINT UNSIGNED NOT NULL,
    usuario_uuid CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    papel VARCHAR(100) CHARACTER SET ascii COLLATE ascii_bin
        NOT NULL DEFAULT 'responsavel',
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    PRIMARY KEY (id),
    UNIQUE KEY uq_tarefa_responsaveis_vinculo (tarefa_id, usuario_uuid),
    KEY idx_tarefa_responsaveis_usuario (usuario_uuid),
    CONSTRAINT fk_tarefa_responsaveis_tarefa
        FOREIGN KEY (tarefa_id) REFERENCES tarefas (id)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

INSERT IGNORE INTO schema_migrations (migration)
VALUES ('005_create_tarefa_responsaveis.sql');
