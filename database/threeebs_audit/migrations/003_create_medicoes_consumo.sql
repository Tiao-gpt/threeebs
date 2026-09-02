USE threeebs_audit;

CREATE TABLE IF NOT EXISTS medicoes_consumo (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    uuid CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    cliente_uuid CHAR(36) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
    projeto_uuid CHAR(36) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
    ambiente_uuid CHAR(36) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
    recurso_tipo VARCHAR(80) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    origem VARCHAR(50) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    unidade VARCHAR(50) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    quantidade DECIMAL(24,8) UNSIGNED NOT NULL,
    custo_estimado DECIMAL(19,4) UNSIGNED DEFAULT NULL,
    moeda CHAR(3) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
    inicio_em DATETIME(6) DEFAULT NULL,
    fim_em DATETIME(6) DEFAULT NULL,
    registrado_em DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    detalhes JSON DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_medicoes_consumo_uuid (uuid),
    KEY idx_medicoes_recurso_data (recurso_tipo, registrado_em),
    KEY idx_medicoes_cliente_data (cliente_uuid, registrado_em),
    KEY idx_medicoes_projeto_data (projeto_uuid, registrado_em),
    KEY idx_medicoes_ambiente_data (ambiente_uuid, registrado_em),
    CONSTRAINT chk_medicoes_periodo
        CHECK (fim_em IS NULL OR inicio_em IS NULL OR fim_em >= inicio_em),
    CONSTRAINT chk_medicoes_moeda_custo
        CHECK (custo_estimado IS NULL OR moeda IS NOT NULL)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

INSERT IGNORE INTO schema_migrations (migration)
VALUES ('003_create_medicoes_consumo.sql');
