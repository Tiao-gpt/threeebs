<?php

declare(strict_types=1);

require '/var/www/shared/bootstrap.php';
require '/var/www/shared/ui.php';

$path = request_path();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

function admin_navigation(): array
{
    return [
        ['href' => '/', 'label' => 'Visão geral', 'icon' => 'home'],
        ['href' => '/usuarios', 'label' => 'Usuários', 'icon' => 'users'],
        ['href' => '/clientes', 'label' => 'Clientes', 'icon' => 'clients'],
        ['href' => '/projetos', 'label' => 'Projetos', 'icon' => 'projects'],
        ['href' => '/jornadas', 'label' => 'Jornadas', 'icon' => 'journey'],
        ['href' => '/tarefas', 'label' => 'Tarefas', 'icon' => 'tasks'],
        ['href' => '/servidores', 'label' => 'Servidores', 'icon' => 'server'],
    ];
}

function admin_page_start(string $title): void
{
    global $path;
    if (auth_user()) {
        $GLOBALS['admin_ui_mode'] = 'app';
        ui_app_start($title, 'Admin', admin_navigation(), $path, auth_user());
        return;
    }
    $GLOBALS['admin_ui_mode'] = 'public';
    ui_public_start($title, 'Admin', 'admin-auth');
}

function admin_page_end(): void
{
    if (($GLOBALS['admin_ui_mode'] ?? 'public') === 'app') {
        ui_app_end();
        return;
    }
    ui_public_end();
}

function admin_nav(): void
{
    // A navegação agora é renderizada pelo shell compartilhado.
}

function admin_role_select(string $name, array $roles, string $selected = 'membro'): void
{
    echo '<p><label>Papel<br><select name="' . h($name) . '" required>';
    foreach ($roles as $value => $label) {
        echo '<option value="' . h($value) . '"' . ($value === $selected ? ' selected' : '') . '>'
            . h($label) . '</option>';
    }
    echo '</select></label></p>';
}

function admin_allowed_role(string $role, array $roles): string
{
    if (!array_key_exists($role, $roles)) {
        throw new ValidationException('Selecione um papel válido.');
    }
    return $role;
}

function input(string $name, string $label, string $type = 'text', string $value = '', bool $required = true): void
{
    echo '<p><label>' . h($label) . '<br><input name="' . h($name) . '" type="' . h($type) . '" value="' . h($value) . '"' . ($required ? ' required' : '') . '></label></p>';
}

function route_type_label(string $type): string
{
    return match ($type) {
        'subdominio' => 'Subdomínio Threeebs',
        'dominio_personalizado' => 'Domínio personalizado',
        default => $type,
    };
}

function admin_project(string $uuid): array
{
    $stmt = db('control')->prepare('SELECT p.*, c.nome cliente_nome, c.uuid cliente_uuid FROM projetos p JOIN clientes c ON c.id=p.cliente_id WHERE p.uuid=:uuid LIMIT 1');
    $stmt->execute(['uuid' => $uuid]);
    $project = $stmt->fetch();
    if (!$project) {
        http_response_code(404);
        exit('Projeto não encontrado.');
    }
    return $project;
}

if ($method === 'POST') {
    require_csrf();
    $action = (string) ($_POST['_action'] ?? '');
    try {
        if ($action === 'setup') {
            $identity = db('identity');
            $identity->beginTransaction();
            $state = $identity->query('SELECT status FROM threeebs_control.configuracao_instalacao WHERE id=1 FOR UPDATE')->fetchColumn();
            if ($state !== 'pendente') {
                throw new ValidationException('A instalação já foi inicializada.');
            }
            $name = trim((string) ($_POST['nome'] ?? ''));
            $email = strtolower(trim((string) ($_POST['email'] ?? '')));
            $password = (string) ($_POST['senha'] ?? '');
            if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 10 || $password !== (string) ($_POST['confirmacao'] ?? '')) {
                throw new ValidationException('Confira nome, e-mail e senha (mínimo 10 caracteres).');
            }
            if (!hash_equals(env_required('THREEEBS_SETUP_KEY'), (string) ($_POST['setup_key'] ?? ''))) {
                throw new ValidationException('Chave de instalação inválida.');
            }
            $userUuid = uuid_v4();
            $stmt = $identity->prepare('INSERT INTO usuarios (uuid,nome,email,status) VALUES (:uuid,:nome,:email,\'ativo\')');
            $stmt->execute(['uuid' => $userUuid, 'nome' => $name, 'email' => $email]);
            $userId = (int) $identity->lastInsertId();
            $stmt = $identity->prepare("INSERT INTO credenciais (uuid,usuario_id,tipo,identificador,segredo_hash,ativa) VALUES (:uuid,:user,'senha','',:hash,1)");
            $stmt->execute(['uuid' => uuid_v4(), 'user' => $userId, 'hash' => password_hash($password, PASSWORD_DEFAULT)]);
            $stmt = $identity->prepare("UPDATE threeebs_control.configuracao_instalacao SET primeiro_administrador_usuario_uuid=:uuid,status='inicializada',inicializada_em=UTC_TIMESTAMP(6) WHERE id=1 AND status='pendente'");
            $stmt->execute(['uuid' => $userUuid]);
            $stmt = $identity->prepare(
                "INSERT INTO threeebs_control.plataforma_usuarios
                    (uuid,usuario_uuid,papel,ativo,concedido_por_usuario_uuid)
                 VALUES (:membership,:user,'owner',1,:granted_by)"
            );
            $stmt->execute([
                'membership' => uuid_v4(),
                'user' => $userUuid,
                'granted_by' => $userUuid,
            ]);
            $identity->commit();
            login_user($email, $password);
            audit_event('instalacao.inicializada', 'instalacao');
            flash('Primeiro administrador criado como owner da plataforma.');
            redirect('/');
        }

        if ($action === 'login') {
            if (!login_user((string) ($_POST['email'] ?? ''), (string) ($_POST['senha'] ?? ''))) {
                throw new ValidationException('E-mail ou senha inválidos.');
            }
            if (!is_admin()) {
                logout_user();
                throw new ValidationException('Este usuário não é o administrador inicial.');
            }
            redirect('/');
        }

        if ($action === 'logout') {
            logout_user();
            redirect('/login');
        }

        $admin = require_admin();

        if ($action === 'create_journey') {
            $name = trim((string) ($_POST['nome'] ?? ''));
            $code = slug((string) ($_POST['codigo'] ?? ''));
            $context = slug((string) ($_POST['contexto'] ?? ''));
            if ($name === '' || $code === '' || $context === '') {
                throw new ValidationException('Nome, código e contexto são obrigatórios.');
            }
            $stmt = db('control')->prepare(
                "INSERT INTO jornadas (uuid,codigo,nome,descricao,contexto,status)
                 VALUES (:uuid,:code,:name,:description,:context,:status)"
            );
            $uuid = uuid_v4();
            $stmt->execute([
                'uuid' => $uuid,
                'code' => $code,
                'name' => $name,
                'description' => trim((string) ($_POST['descricao'] ?? '')),
                'context' => $context,
                'status' => isset($_POST['ativa']) ? 'ativa' : 'inativa',
            ]);
            flash('Jornada criada.');
            redirect('/jornadas?uuid=' . rawurlencode($uuid));
        }

        if ($action === 'create_journey_stage') {
            $journeyUuid = (string) ($_POST['jornada_uuid'] ?? '');
            $title = trim((string) ($_POST['titulo'] ?? ''));
            $code = slug((string) ($_POST['codigo'] ?? ''));
            $order = (int) ($_POST['ordem'] ?? 0);
            if ($journeyUuid === '' || $title === '' || $code === '' || $order < 1) {
                throw new ValidationException('Jornada, título, código e ordem positiva são obrigatórios.');
            }
            $stmt = db('control')->prepare(
                "INSERT INTO jornada_etapas
                    (uuid,jornada_id,codigo,titulo,descricao,ordem,status)
                 SELECT :uuid,id,:code,:title,:description,:position,:status
                 FROM jornadas WHERE uuid=:journey"
            );
            $stmt->execute([
                'uuid' => uuid_v4(),
                'code' => $code,
                'title' => $title,
                'description' => trim((string) ($_POST['descricao'] ?? '')),
                'position' => $order,
                'status' => isset($_POST['ativa']) ? 'ativa' : 'inativa',
                'journey' => $journeyUuid,
            ]);
            if ($stmt->rowCount() !== 1) {
                throw new ValidationException('Jornada não encontrada.');
            }
            flash('Etapa criada.');
            redirect('/jornadas?uuid=' . rawurlencode($journeyUuid));
        }

        if ($action === 'assign_journey') {
            $journeyUuid = (string) ($_POST['jornada_uuid'] ?? '');
            $userUuid = (string) ($_POST['usuario_uuid'] ?? '');
            $user = db('identity')->prepare('SELECT 1 FROM usuarios WHERE uuid=:uuid AND status=\'ativo\'');
            $user->execute(['uuid' => $userUuid]);
            if (!$user->fetchColumn()) {
                throw new ValidationException('Usuário ativo não encontrado.');
            }
            $journey = db('control')->prepare('SELECT 1 FROM jornadas WHERE uuid=:uuid');
            $journey->execute(['uuid' => $journeyUuid]);
            if (!$journey->fetchColumn()) {
                throw new ValidationException('Jornada não encontrada.');
            }
            $stmt = db('control')->prepare(
                "INSERT INTO usuario_jornadas (uuid,jornada_id,usuario_uuid,status)
                 SELECT :uuid,id,:user,'pendente' FROM jornadas WHERE uuid=:journey
                 ON DUPLICATE KEY UPDATE updated_at=CURRENT_TIMESTAMP(6)"
            );
            $stmt->execute([
                'uuid' => uuid_v4(),
                'user' => $userUuid,
                'journey' => $journeyUuid,
            ]);
            flash('Jornada associada ao usuário.');
            redirect('/jornadas?uuid=' . rawurlencode($journeyUuid));
        }

        if ($action === 'toggle_journey') {
            $journeyUuid = (string) ($_POST['jornada_uuid'] ?? '');
            $stmt = db('control')->prepare(
                "UPDATE jornadas
                 SET status=IF(status='ativa','inativa','ativa')
                 WHERE uuid=:uuid"
            );
            $stmt->execute(['uuid' => $journeyUuid]);
            flash('Status da jornada alterado.');
            redirect('/jornadas?uuid=' . rawurlencode($journeyUuid));
        }

        if ($action === 'toggle_journey_stage') {
            $journeyUuid = (string) ($_POST['jornada_uuid'] ?? '');
            $stmt = db('control')->prepare(
                "UPDATE jornada_etapas e
                 JOIN jornadas j ON j.id=e.jornada_id
                 SET e.status=IF(e.status='ativa','inativa','ativa')
                 WHERE e.uuid=:stage AND j.uuid=:journey"
            );
            $stmt->execute([
                'stage' => (string) ($_POST['etapa_uuid'] ?? ''),
                'journey' => $journeyUuid,
            ]);
            flash('Status da etapa alterado.');
            redirect('/jornadas?uuid=' . rawurlencode($journeyUuid));
        }

        if ($action === 'advance_journey') {
            $journeyUuid = (string) ($_POST['jornada_uuid'] ?? '');
            flash(advance_user_journey((string) ($_POST['usuario_jornada_uuid'] ?? '')));
            redirect('/jornadas?uuid=' . rawurlencode($journeyUuid));
        }

        if ($action === 'create_user') {
            $name = trim((string) ($_POST['nome'] ?? ''));
            $email = strtolower(trim((string) ($_POST['email'] ?? '')));
            $password = (string) ($_POST['senha'] ?? '');
            if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 10) {
                throw new ValidationException('Nome, e-mail válido e senha de no mínimo 10 caracteres são obrigatórios.');
            }
            $pdo = db('identity');
            $pdo->beginTransaction();
            $stmt = $pdo->prepare('INSERT INTO usuarios (uuid,nome,email,status) VALUES (:uuid,:nome,:email,\'ativo\')');
            $uuid = uuid_v4();
            $stmt->execute(['uuid' => $uuid, 'nome' => $name, 'email' => $email]);
            $stmt = $pdo->prepare("INSERT INTO credenciais (uuid,usuario_id,tipo,identificador,segredo_hash,ativa) VALUES (:uuid,:user,'senha','',:hash,1)");
            $stmt->execute(['uuid' => uuid_v4(), 'user' => $pdo->lastInsertId(), 'hash' => password_hash($password, PASSWORD_DEFAULT)]);
            $pdo->commit();
            audit_event('usuario.criado', 'usuario', $uuid);
            flash('Usuário criado.');
            redirect('/usuarios?uuid=' . rawurlencode($uuid));
        }

        if ($action === 'create_client') {
            $name = trim((string) ($_POST['nome'] ?? ''));
            $clientSlug = slug((string) ($_POST['slug'] ?? ''));
            if ($name === '' || $clientSlug === '') {
                throw new ValidationException('Nome e slug são obrigatórios.');
            }
            $stmt = db('control')->prepare('INSERT INTO clientes (uuid,nome,slug,status,criado_por_usuario_uuid) VALUES (:uuid,:nome,:slug,:status,:by)');
            $uuid = uuid_v4();
            $stmt->execute(['uuid' => $uuid, 'nome' => $name, 'slug' => $clientSlug, 'status' => (string) ($_POST['status'] ?? 'ativo'), 'by' => $admin['uuid']]);
            audit_event('cliente.criado', 'cliente', $uuid, $uuid);
            flash('Cliente criado.');
            redirect('/clientes?uuid=' . rawurlencode($uuid));
        }

        if ($action === 'link_client_user') {
            $clientUuid = (string) ($_POST['cliente_uuid'] ?? '');
            $clientUserUuid = (string) ($_POST['usuario_uuid'] ?? '');
            if (!current_active_user(['uuid' => $clientUserUuid])) {
                throw new ValidationException('O usuário selecionado não existe ou não está ativo.');
            }
            $clientLookup = db('control')->prepare(
                "SELECT id FROM clientes WHERE uuid=:client AND status='ativo' LIMIT 1"
            );
            $clientLookup->execute(['client' => $clientUuid]);
            $clientId = $clientLookup->fetchColumn();
            if (!$clientId) {
                throw new ValidationException('Cliente ativo não encontrado.');
            }

            $stmt = db('control')->prepare(
                "INSERT INTO cliente_usuarios
                    (cliente_id,usuario_uuid,papel,ativo,concedido_por_usuario_uuid)
                 VALUES (:client,:user,:role,:active,:by)
                 ON DUPLICATE KEY UPDATE
                    papel=VALUES(papel),ativo=VALUES(ativo),
                    concedido_por_usuario_uuid=VALUES(concedido_por_usuario_uuid)"
            );
            $clientRoles = ['proprietario' => 'Proprietário', 'gestor' => 'Gestor', 'membro' => 'Membro'];
            $clientRole = admin_allowed_role(trim((string) ($_POST['papel'] ?? '')), $clientRoles);
            $clientUserActive = isset($_POST['ativo']) ? 1 : 0;
            $stmt->execute([
                'client' => $clientId,
                'user' => $clientUserUuid,
                'role' => $clientRole,
                'active' => $clientUserActive,
                'by' => $admin['uuid'],
            ]);
            audit_event(
                'cliente.usuario_vinculado',
                'cliente',
                $clientUuid,
                $clientUuid,
                null,
                null,
                ['usuario_uuid' => $clientUserUuid, 'papel' => $clientRole, 'ativo' => $clientUserActive]
            );
            flash('Vínculo do cliente salvo.');
            redirect('/clientes?uuid=' . rawurlencode($clientUuid));
        }

        if ($action === 'create_project') {
            $clientUuid = (string) $_POST['cliente_uuid'];
            $name = trim((string) ($_POST['nome'] ?? ''));
            $projectSlug = slug((string) ($_POST['slug'] ?? ''));
            if ($name === '' || $projectSlug === '') {
                throw new ValidationException('Nome e slug são obrigatórios.');
            }
            $stmt = db('control')->prepare('INSERT INTO projetos (uuid,cliente_id,nome,slug,descricao,status) SELECT :uuid,id,:nome,:slug,:description,:status FROM clientes WHERE uuid=:client');
            $uuid = uuid_v4();
            $stmt->execute(['uuid' => $uuid, 'nome' => $name, 'slug' => $projectSlug, 'description' => trim((string) ($_POST['descricao'] ?? '')), 'status' => (string) ($_POST['status'] ?? 'ativo'), 'client' => $clientUuid]);
            if ($stmt->rowCount() !== 1) {
                throw new ValidationException('Cliente não encontrado.');
            }
            audit_event('projeto.criado', 'projeto', $uuid, $clientUuid, $uuid);
            flash('Projeto criado.');
            redirect('/projetos?uuid=' . rawurlencode($uuid));
        }

        if ($action === 'link_project_user') {
            $projectUuid = (string) ($_POST['projeto_uuid'] ?? '');
            $projectUserUuid = (string) ($_POST['usuario_uuid'] ?? '');
            $project = admin_project($projectUuid);
            if (!current_active_user(['uuid' => $projectUserUuid])) {
                throw new ValidationException('O usuário selecionado não existe ou não está ativo.');
            }
            $membership = db('control')->prepare(
                'SELECT 1
                 FROM cliente_usuarios
                 WHERE cliente_id=:client AND usuario_uuid=:user AND ativo=1
                 LIMIT 1'
            );
            $membership->execute([
                'client' => $project['cliente_id'],
                'user' => $projectUserUuid,
            ]);
            if (!$membership->fetchColumn()) {
                throw new ValidationException(
                    'O usuário precisa pertencer ativamente ao cliente dono deste projeto.'
                );
            }

            $stmt = db('control')->prepare(
                "INSERT INTO projeto_usuarios
                    (projeto_id,usuario_uuid,papel,ativo,concedido_por_usuario_uuid)
                 VALUES (:project,:user,:role,:active,:by)
                 ON DUPLICATE KEY UPDATE
                    papel=VALUES(papel),ativo=VALUES(ativo),
                    concedido_por_usuario_uuid=VALUES(concedido_por_usuario_uuid)"
            );
            $projectRoles = [
                'gestor' => 'Gestor',
                'desenvolvedor' => 'Desenvolvedor',
                'colaborador' => 'Colaborador',
                'membro' => 'Membro',
                'visualizador' => 'Visualizador',
            ];
            $projectRole = admin_allowed_role(trim((string) ($_POST['papel'] ?? '')), $projectRoles);
            $projectUserActive = isset($_POST['ativo']) ? 1 : 0;
            $stmt->execute([
                'project' => $project['id'],
                'user' => $projectUserUuid,
                'role' => $projectRole,
                'active' => $projectUserActive,
                'by' => $admin['uuid'],
            ]);
            audit_event(
                'projeto.usuario_vinculado',
                'projeto',
                $projectUuid,
                $project['cliente_uuid'],
                $projectUuid,
                null,
                ['usuario_uuid' => $projectUserUuid, 'papel' => $projectRole, 'ativo' => $projectUserActive]
            );
            flash('Vínculo do projeto salvo.');
            redirect('/projetos?uuid=' . rawurlencode($projectUuid));
        }

        if ($action === 'provision_environments') {
            $project = admin_project((string) $_POST['projeto_uuid']);
            $serverId = db('control')->query("SELECT id FROM servidores WHERE padrao=1 AND status='ativo' ORDER BY id LIMIT 1")->fetchColumn();
            if (!$serverId) {
                throw new ValidationException('Servidor padrão ativo não encontrado.');
            }
            $stmt = db('control')->prepare("INSERT INTO ambientes (uuid,projeto_id,servidor_id,tipo,nome,slug,diretorio,status)
                VALUES (:uuid,:project,:server,:type,:name,:slug,:directory,'ativo')
                ON DUPLICATE KEY UPDATE servidor_id=VALUES(servidor_id),nome=VALUES(nome),slug=VALUES(slug),diretorio=VALUES(diretorio)");
            foreach (['sandbox' => ['Sandbox', 'sandbox'], 'production' => ['Produção', 'production']] as $type => [$name, $folder]) {
                $relative = $project['uuid'] . '/' . $folder;
                $stmt->execute(['uuid' => uuid_v4(), 'project' => $project['id'], 'server' => $serverId, 'type' => $type, 'name' => $name, 'slug' => $folder, 'directory' => $relative]);
                $directory = safe_environment_path($relative, true);
                $index = $directory . '/index.html';
                if (!is_file($index)) {
                    $label = $type === 'sandbox' ? 'Sandbox' : 'Production';
                    atomic_write($index, '<h1>Threeebs ' . $label . ' :3</h1>' . "\n" . '<p>Projeto: ' . h($project['nome']) . '</p>' . "\n");
                }
            }
            audit_event(
                'ambientes.criados',
                'projeto',
                $project['uuid'],
                null,
                $project['uuid'],
                null,
                ['tipos' => ['sandbox', 'production']]
            );
            flash('Ambientes padrão provisionados sem sobrescrever conteúdo existente.');
            redirect('/projetos?uuid=' . rawurlencode($project['uuid']));
        }

        if ($action === 'create_route') {
            $projectUuid = (string) $_POST['projeto_uuid'];
            $hostname = normalize_hostname((string) ($_POST['hostname'] ?? ''));
            $routeType = (string) ($_POST['tipo'] ?? '');
            if (!in_array($routeType, ['subdominio', 'dominio_personalizado'], true)) {
                throw new ValidationException('Tipo de rota inválido.');
            }

            $existing = db('control')->prepare(
                'SELECT r.hostname,a.nome ambiente,p.nome projeto
                 FROM rotas_web r
                 JOIN ambientes a ON a.id=r.ambiente_id
                 JOIN projetos p ON p.id=a.projeto_id
                 WHERE r.hostname=:hostname LIMIT 1'
            );
            $existing->execute(['hostname' => $hostname]);
            if ($currentRoute = $existing->fetch()) {
                throw new ValidationException(
                    'Hostname já cadastrado para o projeto '
                    . $currentRoute['projeto'] . ' / ' . $currentRoute['ambiente'] . '.'
                );
            }

            $stmt = db('control')->prepare(
                'INSERT INTO rotas_web (uuid,ambiente_id,hostname,tipo,ativo)
                 SELECT :uuid,a.id,:hostname,:type,:active
                 FROM ambientes a
                 JOIN projetos p ON p.id=a.projeto_id
                 WHERE a.uuid=:environment AND p.uuid=:project'
            );
            $routeUuid = uuid_v4();
            $stmt->execute([
                'uuid' => $routeUuid,
                'hostname' => $hostname,
                'type' => $routeType,
                'active' => isset($_POST['ativo']) ? 1 : 0,
                'environment' => (string) $_POST['ambiente_uuid'],
                'project' => $projectUuid,
            ]);
            if ($stmt->rowCount() !== 1) {
                throw new ValidationException('Ambiente não encontrado neste projeto.');
            }
            audit_event(
                'rota.criada',
                'rota_web',
                $routeUuid,
                null,
                $projectUuid,
                (string) $_POST['ambiente_uuid'],
                ['hostname' => $hostname, 'tipo' => $routeType]
            );
            flash('Rota criada. Configure o mesmo hostname na Cloudflare para publicar o acesso.');
            redirect('/projetos?uuid=' . rawurlencode($projectUuid));
        }

        if ($action === 'create_default_board') {
            $project = admin_project((string) $_POST['projeto_uuid']);
            $work = db('work');
            $work->beginTransaction();
            $stmt = $work->prepare("INSERT INTO quadros (uuid,projeto_uuid,nome,slug,status) VALUES (:uuid,:project,'Fluxo padrão','fluxo-padrao','ativo') ON DUPLICATE KEY UPDATE nome=VALUES(nome)");
            $stmt->execute(['uuid' => uuid_v4(), 'project' => $project['uuid']]);
            $stmt = $work->prepare("SELECT id FROM quadros WHERE projeto_uuid=:project AND slug='fluxo-padrao'");
            $stmt->execute(['project' => $project['uuid']]);
            $boardId = (int) $stmt->fetchColumn();
            $column = $work->prepare("INSERT INTO colunas (uuid,quadro_id,nome,slug,ordem,status) VALUES (:uuid,:board,:name,:slug,:position,'ativa') ON DUPLICATE KEY UPDATE nome=VALUES(nome)");
            foreach (['Backlog', 'Fazer', 'Fazendo', 'Revisão', 'Concluído'] as $position => $name) {
                $column->execute(['uuid' => uuid_v4(), 'board' => $boardId, 'name' => $name, 'slug' => slug($name), 'position' => $position + 1]);
            }
            $work->commit();
            flash('Quadro padrão criado.');
            redirect('/projetos?uuid=' . rawurlencode($project['uuid']));
        }

        if ($action === 'create_board') {
            $project = admin_project((string) $_POST['projeto_uuid']);
            $name = trim((string) $_POST['nome']);
            if ($name === '') throw new ValidationException('Informe o nome do quadro.');
            $stmt = db('work')->prepare("INSERT INTO quadros (uuid,projeto_uuid,nome,slug,status) VALUES (:uuid,:project,:name,:slug,'ativo')");
            $stmt->execute(['uuid' => uuid_v4(), 'project' => $project['uuid'], 'name' => $name, 'slug' => slug($name)]);
            redirect('/projetos?uuid=' . rawurlencode($project['uuid']));
        }

        if ($action === 'create_column') {
            $project = admin_project((string) $_POST['projeto_uuid']);
            $name = trim((string) $_POST['nome']);
            $stmt = db('work')->prepare("INSERT INTO colunas (uuid,quadro_id,nome,slug,ordem,status) VALUES (:uuid,:board,:name,:slug,:position,'ativa')");
            $stmt->execute(['uuid' => uuid_v4(), 'board' => (int) $_POST['quadro_id'], 'name' => $name, 'slug' => slug($name), 'position' => max(1, (int) $_POST['ordem'])]);
            redirect('/projetos?uuid=' . rawurlencode($project['uuid']));
        }

        if ($action === 'create_task') {
            $project = admin_project((string) $_POST['projeto_uuid']);
            $title = trim((string) $_POST['titulo']);
            if ($title === '') throw new ValidationException('Informe o título da tarefa.');
            $work = db('work');
            $work->beginTransaction();
            $stmt = $work->prepare("INSERT INTO tarefas (uuid,coluna_id,titulo,descricao,status,prioridade,criado_por_usuario_uuid) VALUES (:uuid,:column,:title,:description,'aberta',:priority,:by)");
            $taskUuid = uuid_v4();
            $stmt->execute(['uuid' => $taskUuid, 'column' => (int) $_POST['coluna_id'], 'title' => $title, 'description' => trim((string) ($_POST['descricao'] ?? '')), 'priority' => trim((string) ($_POST['prioridade'] ?? 'normal')) ?: 'normal', 'by' => $admin['uuid']]);
            $taskId = (int) $work->lastInsertId();
            if (!empty($_POST['responsavel_uuid'])) {
                $stmt = $work->prepare("INSERT INTO tarefa_responsaveis (tarefa_id,usuario_uuid,papel) VALUES (:task,:user,'responsavel')");
                $stmt->execute(['task' => $taskId, 'user' => (string) $_POST['responsavel_uuid']]);
            }
            $work->commit();
            audit_event(
                'tarefa.criada',
                'tarefa',
                $taskUuid,
                $project['cliente_uuid'] ?? null,
                $project['uuid']
            );
            flash('Tarefa criada.');
            redirect('/projetos?uuid=' . rawurlencode($project['uuid']));
        }
    } catch (Throwable $error) {
        if (isset($identity) && $identity instanceof PDO && $identity->inTransaction()) $identity->rollBack();
        if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
        if (isset($work) && $work instanceof PDO && $work->inTransaction()) $work->rollBack();
        error_log($error->getMessage());
        $message = $error instanceof PDOException && $error->getCode() === '23000'
            ? 'Registro duplicado ou vínculo inválido.'
            : public_error_message($error);
        flash($message);
        $return = (string) ($_POST['_return'] ?? '/');
        redirect(str_starts_with($return, '/') && !str_starts_with($return, '//') ? $return : '/');
    }
}

$state = installation();
if (($state['status'] ?? 'pendente') === 'pendente') {
    admin_page_start('Threeebs Admin :3 — Primeiro acesso');
    show_flash();
    echo '<form method="post">' . csrf_field() . '<input type="hidden" name="_action" value="setup">';
    input('nome', 'Nome'); input('email', 'E-mail', 'email'); input('senha', 'Senha (mínimo 10 caracteres)', 'password'); input('confirmacao', 'Confirmar senha', 'password'); input('setup_key', 'THREEEBS_SETUP_KEY', 'password');
    echo '<button>Criar primeiro administrador</button></form>';
    admin_page_end(); exit;
}

if ($path === '/login' && !auth_user()) {
    admin_page_start('Threeebs Admin :3 — Entrar'); show_flash();
    echo '<form method="post">' . csrf_field() . '<input type="hidden" name="_action" value="login">';
    input('email', 'E-mail', 'email'); input('senha', 'Senha', 'password'); echo '<button>Entrar</button></form>';
    admin_page_end(); exit;
}

$admin = require_admin();
admin_page_start('Threeebs Admin :3');
admin_nav(); show_flash();

if ($path === '/') {
    echo '<section class="admin-overview"><p class="eyebrow"><span></span>Administração da plataforma</p><h1>Olá, ' . h($admin['nome']) . '.</h1><p>Gerencie pessoas, clientes, projetos e operação a partir de um único painel.</p></section>'
        . '<div class="admin-grid">'
        . '<a href="/usuarios"><span>Acessos</span><strong>Usuários</strong></a>'
        . '<a href="/clientes"><span>Organizações</span><strong>Clientes</strong></a>'
        . '<a href="/projetos"><span>Ambientes e rotas</span><strong>Projetos</strong></a>'
        . '<a href="/jornadas"><span>Onboarding</span><strong>Jornadas</strong></a>'
        . '<a href="/tarefas"><span>Planejamento</span><strong>Tarefas</strong></a>'
        . '<a href="/servidores"><span>Infraestrutura</span><strong>Servidores</strong></a>'
        . '</div>';
} elseif ($path === '/usuarios') {
    $uuid = (string) ($_GET['uuid'] ?? '');
    if ($uuid !== '') {
        $stmt = db('identity')->prepare('SELECT uuid,nome,email,status,created_at FROM usuarios WHERE uuid=:uuid'); $stmt->execute(['uuid' => $uuid]); $item = $stmt->fetch();
        echo $item ? '<h2>' . h($item['nome']) . '</h2><dl><dt>E-mail</dt><dd>' . h($item['email']) . '</dd><dt>Status</dt><dd>' . h($item['status']) . '</dd><dt>UUID</dt><dd>' . h($item['uuid']) . '</dd></dl>' : '<p>Usuário não encontrado.</p>';
    }
    echo '<h2>Criar usuário</h2><p class="admin-intro">Crie a identidade primeiro. Depois associe o usuário a um cliente e aos projetos que ele poderá acessar.</p><form method="post">' . csrf_field() . '<input type="hidden" name="_action" value="create_user"><input type="hidden" name="_return" value="/usuarios">';
    input('nome','Nome'); input('email','E-mail','email'); input('senha','Senha temporária (mínimo 10 caracteres)','password'); echo '<button>Criar</button></form><h2>Usuários</h2><ul>';
    foreach (db('identity')->query('SELECT uuid,nome,email,status FROM usuarios ORDER BY nome,email') as $user) echo '<li><a href="/usuarios?uuid=' . h($user['uuid']) . '">' . h($user['nome'] ?: $user['email']) . '</a> — ' . h($user['status']) . '</li>';
    echo '</ul>';
} elseif ($path === '/jornadas') {
    $uuid = (string) ($_GET['uuid'] ?? '');
    if ($uuid === '') {
        echo '<h2>Criar jornada</h2><form method="post">' . csrf_field()
            . '<input type="hidden" name="_action" value="create_journey">'
            . '<input type="hidden" name="_return" value="/jornadas">';
        input('nome', 'Nome');
        input('codigo', 'Código');
        input('contexto', 'Contexto', 'text', 'cliente');
        echo '<p><label>Descrição<br><textarea name="descricao"></textarea></label></p>'
            . '<label><input type="checkbox" name="ativa" checked> ativa</label> '
            . '<button>Criar jornada</button></form><h2>Jornadas</h2><ul>';
        foreach (db('control')->query('SELECT uuid,nome,contexto,status FROM jornadas ORDER BY nome') as $journey) {
            echo '<li><a href="/jornadas?uuid=' . h($journey['uuid']) . '">'
                . h($journey['nome']) . '</a> — ' . h($journey['contexto'])
                . ' — ' . h($journey['status']) . '</li>';
        }
        echo '</ul>';
    } else {
        $stmt = db('control')->prepare('SELECT * FROM jornadas WHERE uuid=:uuid');
        $stmt->execute(['uuid' => $uuid]);
        $journey = $stmt->fetch();
        if (!$journey) {
            http_response_code(404);
            echo '<p>Jornada não encontrada.</p>';
        } else {
            echo '<h2>' . h($journey['nome']) . '</h2><p>Código: '
                . h($journey['codigo']) . ' | Contexto: ' . h($journey['contexto'])
                . ' | Status: ' . h($journey['status']) . '</p><p>'
                . nl2br(h($journey['descricao'])) . '</p>'
                . '<form method="post">' . csrf_field()
                . '<input type="hidden" name="_action" value="toggle_journey">'
                . '<input type="hidden" name="jornada_uuid" value="' . h($uuid) . '">'
                . '<button>Alternar status da jornada</button></form>';

            $stmt = db('control')->prepare(
                'SELECT * FROM jornada_etapas WHERE jornada_id=:id ORDER BY ordem,id'
            );
            $stmt->execute(['id' => $journey['id']]);
            $stages = $stmt->fetchAll();
            $activeStageCount = 0;
            echo '<h3>Etapas</h3><ol>';
            foreach ($stages as $stage) {
                if ($stage['status'] === 'ativa') {
                    $activeStageCount++;
                }
                echo '<li>' . h($stage['titulo']) . ' — ordem ' . h($stage['ordem'])
                    . ' — ' . h($stage['status'])
                    . '<form method="post">' . csrf_field()
                    . '<input type="hidden" name="_action" value="toggle_journey_stage">'
                    . '<input type="hidden" name="jornada_uuid" value="' . h($uuid) . '">'
                    . '<input type="hidden" name="etapa_uuid" value="' . h($stage['uuid']) . '">'
                    . '<button>Alternar status</button></form></li>';
            }
            echo '</ol><h3>Adicionar etapa</h3><form method="post">' . csrf_field()
                . '<input type="hidden" name="_action" value="create_journey_stage">'
                . '<input type="hidden" name="jornada_uuid" value="' . h($uuid) . '">';
            input('titulo', 'Título');
            input('codigo', 'Código');
            input('ordem', 'Ordem', 'number', (string) (count($stages) + 1));
            echo '<p><label>Descrição<br><textarea name="descricao"></textarea></label></p>'
                . '<label><input type="checkbox" name="ativa" checked> ativa</label> '
                . '<button>Adicionar etapa</button></form>';

            $users = db('identity')->query(
                "SELECT uuid,nome,email FROM usuarios WHERE status='ativo' ORDER BY nome,email"
            )->fetchAll();
            $usersByUuid = [];
            foreach ($users as $user) {
                $usersByUuid[$user['uuid']] = $user['nome'] ?: $user['email'];
            }
            echo '<h3>Associar usuário</h3><form method="post">' . csrf_field()
                . '<input type="hidden" name="_action" value="assign_journey">'
                . '<input type="hidden" name="jornada_uuid" value="' . h($uuid) . '">'
                . '<select name="usuario_uuid">';
            foreach ($users as $user) {
                echo '<option value="' . h($user['uuid']) . '">'
                    . h(($user['nome'] ?: $user['email']) . ' — ' . $user['email'])
                    . '</option>';
            }
            echo '</select> <button>Associar jornada</button></form>';

            $stmt = db('control')->prepare(
                "SELECT uj.uuid,uj.usuario_uuid,uj.status,uj.iniciada_em,uj.concluida_em,
                        e.titulo etapa_atual,
                        (SELECT COUNT(*) FROM usuario_jornada_etapas uje
                         WHERE uje.usuario_jornada_id=uj.id
                           AND uje.status='concluida') etapas_concluidas
                 FROM usuario_jornadas uj
                 LEFT JOIN jornada_etapas e ON e.id=uj.etapa_atual_id
                 WHERE uj.jornada_id=:journey
                 ORDER BY uj.created_at,uj.id"
            );
            $stmt->execute(['journey' => $journey['id']]);
            echo '<h3>Progresso dos usuários</h3><ul>';
            foreach ($stmt as $progress) {
                echo '<li><strong>'
                    . h($usersByUuid[$progress['usuario_uuid']] ?? $progress['usuario_uuid'])
                    . '</strong> — ' . h($progress['status'])
                    . ' — progresso ' . h($progress['etapas_concluidas']) . '/'
                    . h($activeStageCount)
                    . ' — etapa atual: ' . h($progress['etapa_atual'] ?: 'nenhuma');
                if ($progress['status'] !== 'concluida') {
                    echo '<form method="post">' . csrf_field()
                        . '<input type="hidden" name="_action" value="advance_journey">'
                        . '<input type="hidden" name="jornada_uuid" value="' . h($uuid) . '">'
                        . '<input type="hidden" name="usuario_jornada_uuid" value="'
                        . h($progress['uuid']) . '"><button>'
                        . ($progress['status'] === 'pendente'
                            ? 'Iniciar jornada'
                            : 'Concluir etapa e avançar')
                        . '</button></form>';
                }
                echo '</li>';
            }
            echo '</ul>';
        }
    }
} elseif ($path === '/clientes') {
    $uuid = (string) ($_GET['uuid'] ?? '');
    if ($uuid !== '') {
        $stmt = db('control')->prepare('SELECT * FROM clientes WHERE uuid=:uuid'); $stmt->execute(['uuid'=>$uuid]); $client=$stmt->fetch();
        if (!$client) { http_response_code(404); echo '<p>Cliente não encontrado.</p>'; }
        else {
            echo '<h2>' . h($client['nome']) . '</h2><p>Slug: ' . h($client['slug']) . ' | Status: ' . h($client['status']) . '</p>';
            $users=db('identity')->query('SELECT uuid,nome,email FROM usuarios ORDER BY nome,email')->fetchAll();
            echo '<h3>Associar usuário</h3><form method="post">'.csrf_field().'<input type="hidden" name="_action" value="link_client_user"><input type="hidden" name="cliente_uuid" value="'.h($uuid).'"><input type="hidden" name="_return" value="/clientes?uuid='.h($uuid).'"><select name="usuario_uuid">';
            foreach($users as $u) echo '<option value="'.h($u['uuid']).'">'.h($u['nome'].' — '.$u['email']).'</option>';
            echo '</select>';
            admin_role_select('papel', ['proprietario' => 'Proprietário', 'gestor' => 'Gestor', 'membro' => 'Membro']);
            echo '<p class="role-help">O papel descreve a atuação da pessoa dentro do cliente.</p><label><input type="checkbox" name="ativo" checked> vínculo ativo</label> <button>Salvar vínculo</button></form><ul>';
            $stmt=db('control')->prepare('SELECT cu.usuario_uuid,cu.papel,cu.ativo FROM cliente_usuarios cu WHERE cu.cliente_id=:id'); $stmt->execute(['id'=>$client['id']]);
            foreach($stmt as $link) echo '<li>'.h($link['usuario_uuid']).' — '.h($link['papel']).' — '.($link['ativo']?'ativo':'inativo').'</li>'; echo '</ul>';
            echo '<h3>Novo projeto</h3><form method="post">'.csrf_field().'<input type="hidden" name="_action" value="create_project"><input type="hidden" name="cliente_uuid" value="'.h($uuid).'"><input type="hidden" name="_return" value="/clientes?uuid='.h($uuid).'">';
            input('nome','Nome'); input('slug','Slug'); echo '<p><label>Descrição<br><textarea name="descricao"></textarea></label></p>'; input('status','Status','text','ativo'); echo '<button>Criar projeto</button></form><h3>Projetos</h3><ul>';
            $stmt=db('control')->prepare('SELECT uuid,nome,status FROM projetos WHERE cliente_id=:id ORDER BY nome'); $stmt->execute(['id'=>$client['id']]); foreach($stmt as $p) echo '<li><a href="/projetos?uuid='.h($p['uuid']).'">'.h($p['nome']).'</a> — '.h($p['status']).'</li>'; echo '</ul>';
        }
    }
    echo '<h2>Criar cliente</h2><p class="admin-intro">O cliente agrupa colaboradores e projetos. Use um slug curto, estável e sem espaços.</p><form method="post">'.csrf_field().'<input type="hidden" name="_action" value="create_client"><input type="hidden" name="_return" value="/clientes">'; input('nome','Nome'); input('slug','Slug'); input('status','Status','text','ativo'); echo '<button>Criar</button></form><h2>Clientes</h2><ul>';
    foreach(db('control')->query('SELECT uuid,nome,status FROM clientes ORDER BY nome') as $c) echo '<li><a href="/clientes?uuid='.h($c['uuid']).'">'.h($c['nome']).'</a> — '.h($c['status']).'</li>'; echo '</ul>';
} elseif ($path === '/projetos') {
    $uuid=(string)($_GET['uuid']??'');
    if($uuid===''){ echo '<h2>Projetos</h2><ul>'; foreach(db('control')->query('SELECT p.uuid,p.nome,p.status,c.nome cliente FROM projetos p JOIN clientes c ON c.id=p.cliente_id ORDER BY p.nome') as $p) echo '<li><a href="/projetos?uuid='.h($p['uuid']).'">'.h($p['nome']).'</a> — '.h($p['cliente']).' — '.h($p['status']).'</li>'; echo '</ul>'; }
    else {
        $project=admin_project($uuid); echo '<h2>'.h($project['nome']).'</h2><p>Cliente: '.h($project['cliente_nome']).' | Slug: '.h($project['slug']).' | Status: '.h($project['status']).'</p><p>'.nl2br(h($project['descricao'])).'</p>';
        $users = db('control')->prepare(
            "SELECT u.uuid,u.nome,u.email
             FROM threeebs_identity.usuarios u
             JOIN cliente_usuarios cu ON cu.usuario_uuid=u.uuid
             WHERE cu.cliente_id=:client AND cu.ativo=1 AND u.status='ativo'
             ORDER BY u.nome,u.email"
        );
        $users->execute(['client' => $project['cliente_id']]);
        echo '<h3>Usuários do projeto</h3><form method="post">'.csrf_field().'<input type="hidden" name="_action" value="link_project_user"><input type="hidden" name="projeto_uuid" value="'.h($uuid).'"><input type="hidden" name="_return" value="/projetos?uuid='.h($uuid).'"><select name="usuario_uuid">'; foreach($users as $u) echo '<option value="'.h($u['uuid']).'">'.h($u['nome'].' — '.$u['email']).'</option>';
        echo '</select>';
        admin_role_select('papel', [
            'gestor' => 'Gestor',
            'desenvolvedor' => 'Desenvolvedor',
            'colaborador' => 'Colaborador',
            'membro' => 'Membro',
            'visualizador' => 'Visualizador',
        ]);
        echo '<p class="role-help">O usuário precisa ser membro ativo do cliente antes de receber acesso ao projeto.</p><label><input type="checkbox" name="ativo" checked> vínculo ativo</label> <button>Salvar vínculo</button></form>';
        $stmt=db('control')->prepare('SELECT usuario_uuid,papel,ativo FROM projeto_usuarios WHERE projeto_id=:id');$stmt->execute(['id'=>$project['id']]);echo '<ul>';foreach($stmt as $link)echo '<li>'.h($link['usuario_uuid']).' — '.h($link['papel']).' — '.($link['ativo']?'ativo':'inativo').'</li>';echo '</ul>';
        echo '<h3>Ambientes</h3><form method="post">'.csrf_field().'<input type="hidden" name="_action" value="provision_environments"><input type="hidden" name="projeto_uuid" value="'.h($uuid).'"><input type="hidden" name="_return" value="/projetos?uuid='.h($uuid).'"><button>Criar ambientes padrão</button></form>';
        $stmt=db('control')->prepare('SELECT a.*,s.nome servidor FROM ambientes a JOIN servidores s ON s.id=a.servidor_id WHERE a.projeto_id=:id ORDER BY a.tipo');$stmt->execute(['id'=>$project['id']]);$environments=$stmt->fetchAll();echo '<ul>';foreach($environments as $e)echo '<li>'.h($e['nome']).' — '.h($e['diretorio']).' — '.h($e['status']).'</li>';echo '</ul>';
        $navigation=project_environment_navigation((int)$project['id']);
        $production=$navigation['production']??null;
        $sandbox=$navigation['sandbox']??null;
        echo '<h3>Acessos do projeto</h3><ul><li><strong>Produção:</strong> ';
        echo is_array($production)&&is_string($production['url'])
            ? '<a href="'.h($production['url']).'" target="_blank" rel="noopener">Abrir produção</a>'
            : 'sem rota ativa';
        echo '</li><li><strong>Sandbox:</strong> ';
        echo is_array($sandbox)&&is_string($sandbox['url'])
            ? '<a href="'.h($sandbox['url']).'" target="_blank" rel="noopener">Abrir preview Sandbox</a>'
            : 'sem rota ativa';
        echo '</li><li><strong>Editor Threeebs:</strong> ';
        echo is_array($sandbox)&&(string)$sandbox['status']==='ativo'
            ? '<a href="'.h(editor_project_url($uuid)).'">Abrir Monaco edita</a>'
            : 'Sandbox ativo indisponível';
        echo '</li></ul>';
        echo '<h3>Rotas web</h3>'
            . '<p>Um ambiente pode ter vários hostnames. Cadastre somente o domínio, sem protocolo, caminho ou barra final.</p>'
            . '<p>Sugestões: Produção <code>' . h($project['slug'] . '.3eb.site')
            . '</code> | Sandbox <code>' . h('sandbox-' . $project['slug'] . '.3eb.site') . '</code></p>';
        if (!$environments) {
            echo '<p>Crie os ambientes primeiro.</p>';
        } else {
            echo '<form method="post">' . csrf_field()
                . '<input type="hidden" name="_action" value="create_route">'
                . '<input type="hidden" name="projeto_uuid" value="' . h($uuid) . '">'
                . '<input type="hidden" name="_return" value="/projetos?uuid=' . h($uuid) . '">'
                . '<p><label>Ambiente<br><select name="ambiente_uuid">';
            foreach ($environments as $environment) {
                echo '<option value="' . h($environment['uuid']) . '">' . h($environment['nome']) . '</option>';
            }
            echo '</select></label></p>';
            input('hostname', 'Hostname', 'text', $project['slug'] . '.3eb.site');
            echo '<p><label>Tipo da rota<br><select name="tipo" required>'
                . '<option value="subdominio">Subdomínio Threeebs</option>'
                . '<option value="dominio_personalizado">Domínio personalizado</option>'
                . '</select></label></p>'
                . '<label><input type="checkbox" name="ativo" checked> ativa</label> '
                . '<button>Criar rota</button></form>';
        }
        $stmt = db('control')->prepare(
            'SELECT r.hostname,r.tipo,r.ativo,a.nome ambiente
             FROM rotas_web r
             JOIN ambientes a ON a.id=r.ambiente_id
             WHERE a.projeto_id=:id
             ORDER BY a.nome,r.tipo,r.hostname'
        );
        $stmt->execute(['id' => $project['id']]);
        echo '<table><thead><tr><th>Hostname</th><th>Ambiente</th><th>Tipo</th><th>Status</th></tr></thead><tbody>';
        foreach ($stmt as $route) {
            echo '<tr><td><code>' . h($route['hostname']) . '</code></td>'
                . '<td>' . h($route['ambiente']) . '</td>'
                . '<td>' . h(route_type_label((string) $route['tipo'])) . '</td>'
                . '<td>' . ($route['ativo'] ? 'ativa' : 'inativa') . '</td></tr>';
        }
        echo '</tbody></table>';
        echo '<h3>Work</h3><form method="post">'.csrf_field().'<input type="hidden" name="_action" value="create_default_board"><input type="hidden" name="projeto_uuid" value="'.h($uuid).'"><input type="hidden" name="_return" value="/projetos?uuid='.h($uuid).'"><button>Criar quadro padrão</button></form>';
        $stmt=db('work')->prepare('SELECT * FROM quadros WHERE projeto_uuid=:uuid ORDER BY id');$stmt->execute(['uuid'=>$uuid]);$boards=$stmt->fetchAll();
        echo '<form method="post">'.csrf_field().'<input type="hidden" name="_action" value="create_board"><input type="hidden" name="projeto_uuid" value="'.h($uuid).'"><input type="hidden" name="_return" value="/projetos?uuid='.h($uuid).'">';input('nome','Novo quadro');echo '<button>Criar quadro</button></form>';
        foreach($boards as $board){echo '<h4>'.h($board['nome']).'</h4>'; $stmt=db('work')->prepare('SELECT * FROM colunas WHERE quadro_id=:id ORDER BY ordem');$stmt->execute(['id'=>$board['id']]);$columns=$stmt->fetchAll();foreach($columns as $column){echo '<h5>'.h($column['nome']).'</h5><ul>'; $task=db('work')->prepare('SELECT t.*,GROUP_CONCAT(tr.usuario_uuid) responsaveis FROM tarefas t LEFT JOIN tarefa_responsaveis tr ON tr.tarefa_id=t.id WHERE t.coluna_id=:id GROUP BY t.id ORDER BY t.ordem,t.id');$task->execute(['id'=>$column['id']]);foreach($task as $t)echo '<li>'.h($t['titulo']).($t['responsaveis']?' — '.h($t['responsaveis']):'').'</li>';echo '</ul>';}
            echo '<form method="post">'.csrf_field().'<input type="hidden" name="_action" value="create_column"><input type="hidden" name="projeto_uuid" value="'.h($uuid).'"><input type="hidden" name="quadro_id" value="'.h($board['id']).'"><input name="nome" placeholder="Nova coluna" required> <input name="ordem" type="number" min="1" value="'.h(count($columns)+1).'" required> <button>Criar coluna</button></form>';}
        $allColumns=[];foreach($boards as $board){$stmt=db('work')->prepare('SELECT id,nome FROM colunas WHERE quadro_id=:id ORDER BY ordem');$stmt->execute(['id'=>$board['id']]);$allColumns=array_merge($allColumns,$stmt->fetchAll());}
        if($allColumns){echo '<h4>Nova tarefa</h4><form method="post">'.csrf_field().'<input type="hidden" name="_action" value="create_task"><input type="hidden" name="projeto_uuid" value="'.h($uuid).'"><input type="hidden" name="_return" value="/projetos?uuid='.h($uuid).'">';input('titulo','Título');echo '<p><textarea name="descricao" placeholder="Descrição"></textarea></p><select name="coluna_id">';foreach($allColumns as $c)echo '<option value="'.h($c['id']).'">'.h($c['nome']).'</option>';echo '</select> <select name="responsavel_uuid"><option value="">Sem responsável</option>';foreach($users as $u)echo '<option value="'.h($u['uuid']).'">'.h($u['nome'].' — '.$u['email']).'</option>';echo '</select> <input name="prioridade" value="normal" required> <button>Criar tarefa</button></form>';}
    }
} elseif($path==='/tarefas'){echo '<h2>Tarefas por projeto</h2><ul>';foreach(db('control')->query('SELECT uuid,nome FROM projetos ORDER BY nome') as $p)echo '<li><a href="/projetos?uuid='.h($p['uuid']).'">'.h($p['nome']).'</a></li>';echo '</ul>';}
elseif($path==='/servidores'){echo '<h2>Servidores</h2><table><tr><th>Nome</th><th>Driver</th><th>Hostname</th><th>Status</th></tr>';foreach(db('control')->query('SELECT nome,driver,hostname,status FROM servidores ORDER BY nome') as $s)echo '<tr><td>'.h($s['nome']).'</td><td>'.h($s['driver']).'</td><td>'.h($s['hostname']).'</td><td>'.h($s['status']).'</td></tr>';echo '</table>';}
else{http_response_code(404);echo '<p>Página não encontrada.</p>';}

echo '<form class="logout-form" method="post">'.csrf_field().'<input type="hidden" name="_action" value="logout"><button class="button button--ghost">Sair</button></form>';
admin_page_end();
