USE threeebs_work;

CREATE TABLE IF NOT EXISTS comentarios (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    uuid CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    tarefa_id BIGINT UNSIGNED NOT NULL,
    autor_usuario_uuid
        CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    conteudo TEXT NOT NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6)
        ON UPDATE CURRENT_TIMESTAMP(6),
    PRIMARY KEY (id),
    UNIQUE KEY uq_comentarios_uuid (uuid),
    KEY idx_comentarios_tarefa_data (tarefa_id, created_at),
    KEY idx_comentarios_autor_data (autor_usuario_uuid, created_at),
    CONSTRAINT fk_comentarios_tarefa
        FOREIGN KEY (tarefa_id) REFERENCES tarefas (id)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

INSERT IGNORE INTO schema_migrations (migration)
VALUES ('006_create_comentarios.sql');
