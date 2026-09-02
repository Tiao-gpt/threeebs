USE threeebs_audit;

CREATE TABLE IF NOT EXISTS eventos (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    uuid CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    ator_tipo VARCHAR(50) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    ator_uuid CHAR(36) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
    origem VARCHAR(50) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    acao VARCHAR(100) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    entidade_tipo VARCHAR(80) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
    entidade_uuid CHAR(36) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
    cliente_uuid CHAR(36) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
    projeto_uuid CHAR(36) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
    ambiente_uuid CHAR(36) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
    request_id VARCHAR(100) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
    ip_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
    detalhes JSON DEFAULT NULL,
    ocorrido_em DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    PRIMARY KEY (id),
    UNIQUE KEY uq_eventos_uuid (uuid),
    KEY idx_eventos_acao_data (acao, ocorrido_em),
    KEY idx_eventos_ator_data (ator_tipo, ator_uuid, ocorrido_em),
    KEY idx_eventos_entidade_data (entidade_tipo, entidade_uuid, ocorrido_em),
    KEY idx_eventos_cliente_data (cliente_uuid, ocorrido_em),
    KEY idx_eventos_projeto_data (projeto_uuid, ocorrido_em),
    KEY idx_eventos_ambiente_data (ambiente_uuid, ocorrido_em),
    KEY idx_eventos_request (request_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

INSERT IGNORE INTO schema_migrations (migration)
VALUES ('002_create_eventos.sql');
