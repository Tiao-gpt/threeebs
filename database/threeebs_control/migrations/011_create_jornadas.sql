USE threeebs_control;

CREATE TABLE IF NOT EXISTS jornadas (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    uuid CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    codigo VARCHAR(100) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    nome VARCHAR(180) NOT NULL,
    descricao TEXT DEFAULT NULL,
    contexto VARCHAR(50) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    status VARCHAR(30) CHARACTER SET ascii COLLATE ascii_bin
        NOT NULL DEFAULT 'ativa',
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6)
        ON UPDATE CURRENT_TIMESTAMP(6),
    PRIMARY KEY (id),
    UNIQUE KEY uq_jornadas_uuid (uuid),
    UNIQUE KEY uq_jornadas_codigo (codigo),
    KEY idx_jornadas_contexto_status (contexto, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS jornada_etapas (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    uuid CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    jornada_id BIGINT UNSIGNED NOT NULL,
    codigo VARCHAR(100) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    titulo VARCHAR(180) NOT NULL,
    descricao TEXT DEFAULT NULL,
    ordem INT UNSIGNED NOT NULL,
    status VARCHAR(30) CHARACTER SET ascii COLLATE ascii_bin
        NOT NULL DEFAULT 'ativa',
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6)
        ON UPDATE CURRENT_TIMESTAMP(6),
    PRIMARY KEY (id),
    UNIQUE KEY uq_jornada_etapas_uuid (uuid),
    UNIQUE KEY uq_jornada_etapas_codigo (jornada_id, codigo),
    UNIQUE KEY uq_jornada_etapas_ordem (jornada_id, ordem),
    KEY idx_jornada_etapas_status_ordem (jornada_id, status, ordem),
    CONSTRAINT fk_jornada_etapas_jornada
        FOREIGN KEY (jornada_id) REFERENCES jornadas (id)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS usuario_jornadas (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    uuid CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    jornada_id BIGINT UNSIGNED NOT NULL,
    usuario_uuid CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    etapa_atual_id BIGINT UNSIGNED DEFAULT NULL,
    status VARCHAR(30) CHARACTER SET ascii COLLATE ascii_bin
        NOT NULL DEFAULT 'pendente',
    iniciada_em DATETIME(6) DEFAULT NULL,
    concluida_em DATETIME(6) DEFAULT NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6)
        ON UPDATE CURRENT_TIMESTAMP(6),
    PRIMARY KEY (id),
    UNIQUE KEY uq_usuario_jornadas_uuid (uuid),
    UNIQUE KEY uq_usuario_jornadas_usuario (jornada_id, usuario_uuid),
    KEY idx_usuario_jornadas_usuario_status (usuario_uuid, status),
    KEY idx_usuario_jornadas_etapa_atual (etapa_atual_id),
    CONSTRAINT fk_usuario_jornadas_jornada
        FOREIGN KEY (jornada_id) REFERENCES jornadas (id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_usuario_jornadas_etapa_atual
        FOREIGN KEY (etapa_atual_id) REFERENCES jornada_etapas (id)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS usuario_jornada_etapas (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    usuario_jornada_id BIGINT UNSIGNED NOT NULL,
    jornada_etapa_id BIGINT UNSIGNED NOT NULL,
    status VARCHAR(30) CHARACTER SET ascii COLLATE ascii_bin
        NOT NULL DEFAULT 'em_andamento',
    iniciada_em DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    concluida_em DATETIME(6) DEFAULT NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6)
        ON UPDATE CURRENT_TIMESTAMP(6),
    PRIMARY KEY (id),
    UNIQUE KEY uq_usuario_jornada_etapa (
        usuario_jornada_id,
        jornada_etapa_id
    ),
    KEY idx_usuario_jornada_etapas_status (
        usuario_jornada_id,
        status
    ),
    CONSTRAINT fk_usuario_jornada_etapas_usuario_jornada
        FOREIGN KEY (usuario_jornada_id) REFERENCES usuario_jornadas (id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_usuario_jornada_etapas_etapa
        FOREIGN KEY (jornada_etapa_id) REFERENCES jornada_etapas (id)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

INSERT IGNORE INTO schema_migrations (migration)
VALUES ('011_create_jornadas.sql');
