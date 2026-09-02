USE threeebs_finance;

CREATE TABLE IF NOT EXISTS lancamentos (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    uuid CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    tipo VARCHAR(30) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    conta_id BIGINT UNSIGNED NOT NULL,
    categoria_id BIGINT UNSIGNED NOT NULL,
    contraparte_id BIGINT UNSIGNED DEFAULT NULL,
    cliente_uuid CHAR(36) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
    projeto_uuid CHAR(36) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
    item_uuid CHAR(36) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
    descricao VARCHAR(500) NOT NULL,
    valor DECIMAL(19,4) UNSIGNED NOT NULL,
    moeda CHAR(3) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    competencia_em DATETIME(6) NOT NULL,
    vencimento_em DATETIME(6) DEFAULT NULL,
    pago_em DATETIME(6) DEFAULT NULL,
    status VARCHAR(30) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6)
        ON UPDATE CURRENT_TIMESTAMP(6),
    PRIMARY KEY (id),
    UNIQUE KEY uq_lancamentos_uuid (uuid),
    KEY idx_lancamentos_conta_competencia (conta_id, competencia_em),
    KEY idx_lancamentos_categoria_competencia (categoria_id, competencia_em),
    KEY idx_lancamentos_contraparte (contraparte_id),
    KEY idx_lancamentos_cliente (cliente_uuid, competencia_em),
    KEY idx_lancamentos_projeto (projeto_uuid, competencia_em),
    KEY idx_lancamentos_item (item_uuid),
    KEY idx_lancamentos_status_vencimento (status, vencimento_em),
    CONSTRAINT chk_lancamentos_valor_positivo CHECK (valor > 0),
    CONSTRAINT fk_lancamentos_conta
        FOREIGN KEY (conta_id) REFERENCES contas (id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_lancamentos_categoria
        FOREIGN KEY (categoria_id) REFERENCES categorias (id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_lancamentos_contraparte
        FOREIGN KEY (contraparte_id) REFERENCES contrapartes (id)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

INSERT IGNORE INTO schema_migrations (migration)
VALUES ('005_create_lancamentos.sql');
