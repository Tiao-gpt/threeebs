USE threeebs_catalog;

CREATE TABLE IF NOT EXISTS precos_cliente (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    uuid CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    item_id BIGINT UNSIGNED NOT NULL,
    cliente_uuid CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    valor DECIMAL(19,4) UNSIGNED NOT NULL,
    moeda CHAR(3) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    motivo VARCHAR(255) DEFAULT NULL,
    vigencia_inicio DATETIME(6) NOT NULL,
    vigencia_fim DATETIME(6) DEFAULT NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6)
        ON UPDATE CURRENT_TIMESTAMP(6),
    PRIMARY KEY (id),
    UNIQUE KEY uq_precos_cliente_uuid (uuid),
    KEY idx_precos_cliente_item_cliente_vigencia (
        item_id,
        cliente_uuid,
        vigencia_inicio,
        vigencia_fim
    ),
    KEY idx_precos_cliente_cliente (cliente_uuid),
    CONSTRAINT chk_precos_cliente_vigencia
        CHECK (vigencia_fim IS NULL OR vigencia_fim > vigencia_inicio),
    CONSTRAINT fk_precos_cliente_item
        FOREIGN KEY (item_id) REFERENCES itens (id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

INSERT IGNORE INTO schema_migrations (migration)
VALUES ('005_create_precos_cliente.sql');
