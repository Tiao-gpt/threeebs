USE threeebs_control;

INSERT INTO servidores (uuid, nome, slug, driver, hostname, padrao, status)
VALUES (
    '00000000-0000-4000-8000-000000000001',
    'Threeebs Local',
    'threeebs-local',
    'docker',
    NULL,
    1,
    'ativo'
)
ON DUPLICATE KEY UPDATE
    nome = VALUES(nome),
    driver = VALUES(driver),
    hostname = NULL,
    padrao = 1,
    status = 'ativo';
