<?php

declare(strict_types=1);

require '/var/www/shared/bootstrap.php';
require '/var/www/shared/ui.php';

$path = request_path();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

function portal_navigation(): array
{
    return [
        ['href' => '/', 'label' => 'Visão geral', 'icon' => 'home'],
        ['href' => '/projetos', 'label' => 'Projetos', 'icon' => 'projects'],
        ['href' => '/tarefas', 'label' => 'Tarefas', 'icon' => 'tasks'],
        ['href' => '/colaboradores', 'label' => 'Colaboradores', 'icon' => 'users'],
    ];
}

function portal_collaborators(array $user): array
{
    if (is_admin($user)) {
        return db('control')->query(
            "SELECT DISTINCT u.uuid,u.nome,u.email,c.nome cliente_nome,cu.papel
             FROM cliente_usuarios cu
             JOIN clientes c ON c.id=cu.cliente_id AND c.status='ativo'
             JOIN threeebs_identity.usuarios u ON u.uuid=cu.usuario_uuid AND u.status='ativo'
             WHERE cu.ativo=1
             ORDER BY u.nome,u.email,c.nome"
        )->fetchAll();
    }

    $stmt = db('control')->prepare(
        "SELECT DISTINCT u.uuid,u.nome,u.email,c.nome cliente_nome,collaborator.papel
         FROM clientes c
         JOIN cliente_usuarios member
           ON member.cliente_id=c.id AND member.usuario_uuid=:user AND member.ativo=1
         JOIN cliente_usuarios collaborator
           ON collaborator.cliente_id=c.id AND collaborator.ativo=1
         JOIN threeebs_identity.usuarios u
           ON u.uuid=collaborator.usuario_uuid AND u.status='ativo'
         WHERE c.status='ativo' AND collaborator.usuario_uuid<>:other_user
         ORDER BY u.nome,u.email,c.nome"
    );
    $stmt->execute(['user' => $user['uuid'], 'other_user' => $user['uuid']]);
    return $stmt->fetchAll();
}

function portal_projects_by_uuid(array $projects): array
{
    $result = [];
    foreach ($projects as $project) {
        $result[(string) $project['uuid']] = (string) $project['nome'];
    }
    return $result;
}

function portal_tasks(array $projects, array $user): array
{
    $projectsByUuid = portal_projects_by_uuid($projects);
    if ($projectsByUuid === []) {
        return [];
    }
    $placeholders = implode(',', array_fill(0, count($projectsByUuid), '?'));
    $stmt = db('work')->prepare(
        "SELECT t.titulo,t.descricao,t.status,t.prioridade,q.projeto_uuid,c.nome coluna,
                EXISTS (SELECT 1 FROM tarefa_responsaveis tr
                        WHERE tr.tarefa_id=t.id AND tr.usuario_uuid=?) responsavel
         FROM tarefas t
         JOIN colunas c ON c.id=t.coluna_id
         JOIN quadros q ON q.id=c.quadro_id
         WHERE q.projeto_uuid IN ({$placeholders})
         ORDER BY t.created_at DESC,t.id DESC"
    );
    $stmt->execute(array_merge([(string) $user['uuid']], array_keys($projectsByUuid)));
    return $stmt->fetchAll();
}

function portal_app_start(string $title, array $user): void
{
    global $path;
    ui_app_start($title, 'Portal', portal_navigation(), $path, $user);
    show_flash();
}

if ($method === 'POST') {
    require_csrf();
    $action = (string) ($_POST['_action'] ?? '');
    try {
        if ($action === 'register_interest') {
            $name = trim((string) ($_POST['nome'] ?? ''));
            $email = strtolower(trim((string) ($_POST['email'] ?? '')));
            $company = trim((string) ($_POST['empresa'] ?? ''));
            $type = trim((string) ($_POST['tipo_projeto'] ?? ''));
            $stage = trim((string) ($_POST['momento'] ?? ''));
            $message = trim((string) ($_POST['mensagem'] ?? ''));
            $honeypot = trim((string) ($_POST['website'] ?? ''));
            $allowedTypes = ['site', 'sistema', 'loja', 'outro'];
            $allowedStages = ['ideia', 'planejamento', 'em_andamento', 'evolucao'];

            if ($honeypot !== '') {
                flash('Recebemos seu interesse. Em breve entraremos em contato.');
                redirect('/#interesse');
            }
            if ($name === '' || strlen($name) > 150 || !filter_var($email, FILTER_VALIDATE_EMAIL)
                || strlen($email) > 254 || strlen($company) > 180
                || !in_array($type, $allowedTypes, true) || !in_array($stage, $allowedStages, true)
                || $message === '' || strlen($message) > 4000 || !isset($_POST['consentimento'])) {
                throw new ValidationException('Confira os campos obrigatórios e tente novamente.');
            }
            $lastSubmission = (int) ($_SESSION['interest_submitted_at'] ?? 0);
            if ($lastSubmission > 0 && time() - $lastSubmission < 60) {
                throw new ValidationException('Aguarde um minuto antes de enviar novamente.');
            }

            $ip = client_ip();
            $ipHash = $ip === '' ? null : hash('sha256', $ip . '|' . (getenv('BASE_DOMAIN') ?: 'threeebs'));
            $stmt = db('control')->prepare(
                "INSERT INTO interessados
                    (uuid,nome,email,empresa,tipo_projeto,momento,mensagem,consentimento,origem,status,ip_hash)
                 VALUES (:uuid,:name,:email,:company,:type,:stage,:message,1,'portal','novo',:ip_hash)"
            );
            $stmt->execute([
                'uuid' => uuid_v4(), 'name' => $name, 'email' => $email,
                'company' => $company === '' ? null : $company, 'type' => $type,
                'stage' => $stage, 'message' => $message, 'ip_hash' => $ipHash,
            ]);
            $_SESSION['interest_submitted_at'] = time();
            flash('Recebemos seu interesse. Em breve entraremos em contato.');
            redirect('/#interesse');
        }

        if ($action === 'login') {
            if (!login_user((string) ($_POST['email'] ?? ''), (string) ($_POST['senha'] ?? ''))) {
                throw new ValidationException('E-mail ou senha inválidos.');
            }
            redirect('/');
        }
        if ($action === 'logout') {
            logout_user();
            redirect('/');
        }
    } catch (Throwable $error) {
        error_log($error->getMessage());
        flash(public_error_message($error));
        redirect($action === 'register_interest' ? '/#interesse' : '/login');
    }
}

if ($path === '/login' && !auth_user()) {
    ui_public_start('Entrar', 'Portal', 'auth-public');
    echo '<section class="auth-shell"><div class="auth-copy"><p class="eyebrow"><span></span>Portal Threeebs</p>'
        . '<h1>Bem-vindo de volta.</h1><p>Acesse projetos, ambientes, colaboradores e tarefas em um só lugar.</p>'
        . '<a class="text-link" href="/">Voltar para o início</a></div>'
        . '<div class="auth-card"><h2>Entrar no Portal</h2><p>Use o acesso fornecido pela equipe responsável pelo seu projeto.</p>';
    show_flash();
    echo '<form class="experience-form" method="post">' . csrf_field()
        . '<input type="hidden" name="_action" value="login"><label>E-mail<input type="email" name="email" autocomplete="username" required></label>'
        . '<label>Senha<input type="password" name="senha" autocomplete="current-password" required></label>'
        . '<button class="button button--wide">Entrar</button></form></div></section>';
    ui_public_end();
    exit;
}

if (!auth_user()) {
    ui_public_start('Projetos digitais com direção', 'Portal', 'portal-landing');
    show_flash();
    echo '<section class="landing-hero"><div><p class="eyebrow"><span></span>Threeebs Portal</p>'
        . '<h1>Da ideia ao projeto, com <em>clareza.</em></h1>'
        . '<p class="hero-lead">Um espaço para acompanhar sua presença digital, conversar com colaboradores e transformar próximos passos em entregas reais.</p>'
        . '<div class="hero-actions"><a class="button" href="#interesse">Quero desenvolver um projeto</a>'
        . '<a class="button button--secondary" href="/login">Já sou cliente</a></div></div>'
        . '<div class="hero-orbit" aria-hidden="true"><span>:3</span><i></i><i></i><i></i></div></section>'
        . '<section class="landing-section" id="como-funciona"><div class="section-heading"><div><p class="eyebrow"><span></span>Como funciona</p>'
        . '<h2>Um caminho simples e acompanhado.</h2></div></div><div class="feature-grid">'
        . '<article><strong>01</strong><h3>Conte sua ideia</h3><p>Entendemos contexto, momento e objetivo do projeto.</p></article>'
        . '<article><strong>02</strong><h3>Organize o trabalho</h3><p>Projetos, tarefas e colaboradores ficam visíveis no Portal.</p></article>'
        . '<article><strong>03</strong><h3>Acompanhe a evolução</h3><p>Produção, Sandbox e Editor Threeebs ficam separados e acessíveis.</p></article>'
        . '</div></section>'
        . '<section class="interest-section" id="interesse"><div><p class="eyebrow"><span></span>Comece por aqui</p>'
        . '<h2>Vamos desenvolver seu projeto?</h2><p>Preencha os dados essenciais. Esta etapa registra seu interesse; não cria uma conta automaticamente.</p></div>'
        . '<form class="interest-form" method="post">' . csrf_field() . '<input type="hidden" name="_action" value="register_interest">'
        . '<div class="honeypot" aria-hidden="true"><label>Website<input name="website" tabindex="-1" autocomplete="off"></label></div>'
        . '<label>Seu nome<input name="nome" maxlength="150" autocomplete="name" required></label>'
        . '<label>E-mail<input type="email" name="email" maxlength="254" autocomplete="email" required></label>'
        . '<label>Empresa ou projeto <span>(opcional)</span><input name="empresa" maxlength="180" autocomplete="organization"></label>'
        . '<div class="form-grid"><label>Tipo de projeto<select name="tipo_projeto" required><option value="">Selecione</option><option value="site">Site</option><option value="sistema">Sistema</option><option value="loja">Loja virtual</option><option value="outro">Outro</option></select></label>'
        . '<label>Momento atual<select name="momento" required><option value="">Selecione</option><option value="ideia">Tenho uma ideia</option><option value="planejamento">Estou planejando</option><option value="em_andamento">Já está em andamento</option><option value="evolucao">Quero evoluir algo existente</option></select></label></div>'
        . '<label>O que você quer transformar?<textarea name="mensagem" maxlength="4000" rows="6" required></textarea></label>'
        . '<label class="check-consent"><input type="checkbox" name="consentimento" value="1" required><span>Autorizo o contato da equipe Threeebs sobre este interesse.</span></label>'
        . '<button class="button">Enviar interesse</button></form></section>';
    ui_public_end();
    exit;
}

$user = require_auth();
$projects = authorized_projects($user);
$projectsByUuid = portal_projects_by_uuid($projects);

if ($path === '/') {
    portal_app_start('Visão geral', $user);
    echo '<section class="dashboard-hero"><p class="eyebrow"><span></span>Seu espaço Threeebs</p><h1>Olá, ' . h((string) $user['nome']) . '.</h1>'
        . '<p>Acompanhe projetos, acessos e o trabalho da sua equipe.</p></section>'
        . '<div class="metric-grid"><article><strong>' . count($projects) . '</strong><span>Projetos ativos</span></article>'
        . '<article><strong>' . count(portal_collaborators($user)) . '</strong><span>Colaboradores</span></article>'
        . '<article><strong>' . count(portal_tasks($projects, $user)) . '</strong><span>Tarefas visíveis</span></article></div>'
        . '<section class="content-section"><div class="section-title"><h2>Meus projetos</h2><a href="/projetos">Ver todos</a></div><div class="project-grid">';
    if ($projects === []) {
        echo '<p class="empty-state">Nenhum projeto está associado à sua conta.</p>';
    }
    foreach (array_slice($projects, 0, 4) as $project) {
        echo '<article class="project-card"><span>' . h($project['cliente_nome']) . '</span><h3>' . h($project['nome']) . '</h3>'
            . '<a class="button button--secondary" href="/projeto?uuid=' . h($project['uuid']) . '">Abrir projeto</a></article>';
    }
    echo '</div></section>';
} elseif ($path === '/projetos') {
    portal_app_start('Projetos', $user);
    echo '<div class="page-heading compact-heading"><p class="eyebrow"><span></span>Projetos</p><h1>Seus espaços de trabalho</h1><p>Acesse cada projeto e escolha entre Produção, Sandbox e Editor Threeebs.</p></div><div class="project-grid">';
    if ($projects === []) echo '<p class="empty-state">Nenhum projeto associado à sua conta.</p>';
    foreach ($projects as $project) {
        echo '<article class="project-card"><span>' . h($project['cliente_nome']) . '</span><h2>' . h($project['nome']) . '</h2>'
            . '<a class="button" href="/projeto?uuid=' . h($project['uuid']) . '">Acessar</a></article>';
    }
    echo '</div>';
} elseif ($path === '/colaboradores') {
    portal_app_start('Colaboradores', $user);
    echo '<div class="page-heading compact-heading"><p class="eyebrow"><span></span>Equipe</p><h1>Meus colaboradores</h1><p>Pessoas vinculadas aos mesmos clientes ativos que você.</p></div><div class="people-grid">';
    $collaborators = portal_collaborators($user);
    if ($collaborators === []) echo '<p class="empty-state">Nenhum colaborador disponível.</p>';
    foreach ($collaborators as $collaborator) {
        echo '<article class="person-card"><div class="avatar">' . h(strtoupper(substr((string) ($collaborator['nome'] ?: $collaborator['email']), 0, 1))) . '</div>'
            . '<div><h3>' . h($collaborator['nome'] ?: $collaborator['email']) . '</h3><p>' . h($collaborator['cliente_nome']) . ' · ' . h($collaborator['papel']) . '</p></div></article>';
    }
    echo '</div>';
} elseif ($path === '/tarefas') {
    portal_app_start('Tarefas', $user);
    echo '<div class="page-heading compact-heading"><p class="eyebrow"><span></span>Trabalho</p><h1>Tarefas dos meus projetos</h1><p>A lista respeita os vínculos ativos de cliente e projeto.</p></div><div class="task-list">';
    $tasks = portal_tasks($projects, $user);
    if ($tasks === []) echo '<p class="empty-state">Nenhuma tarefa disponível.</p>';
    foreach ($tasks as $task) {
        echo '<article><div><span>' . h($projectsByUuid[(string) $task['projeto_uuid']] ?? 'Projeto') . ' · ' . h($task['coluna']) . '</span>'
            . '<h3>' . h($task['titulo']) . '</h3></div><div class="task-meta"><span>' . h($task['status']) . '</span>'
            . ((int) $task['responsavel'] === 1 ? '<strong>Responsável: você</strong>' : '') . '</div></article>';
    }
    echo '</div>';
} elseif ($path === '/projeto') {
    $project = require_project((string) ($_GET['uuid'] ?? ''));
    $navigation = project_environment_navigation((int) $project['id']);
    $production = $navigation['production'] ?? null;
    $sandbox = $navigation['sandbox'] ?? null;
    portal_app_start((string) $project['nome'], $user);
    echo '<div class="project-heading"><div><p class="eyebrow"><span></span>' . h($project['cliente_nome']) . '</p><h1>' . h($project['nome']) . '</h1>'
        . '<p>' . nl2br(h($project['descricao'])) . '</p></div><a class="text-link" href="/projetos">Voltar aos projetos</a></div>'
        . '<section class="access-grid">';
    $accesses = [
        ['title' => 'Produção', 'description' => 'Versão pública e estável do projeto.', 'available' => is_array($production) && is_string($production['url']), 'url' => $production['url'] ?? null, 'external' => true],
        ['title' => 'Sandbox', 'description' => 'Preview aberto do ambiente de validação.', 'available' => is_array($sandbox) && is_string($sandbox['url']), 'url' => $sandbox['url'] ?? null, 'external' => true],
        ['title' => 'Editor Threeebs', 'description' => 'Edite com segurança o index.html do Sandbox.', 'available' => is_array($sandbox) && (string) $sandbox['status'] === 'ativo', 'url' => editor_project_url((string) $project['uuid']), 'external' => false],
    ];
    foreach ($accesses as $access) {
        echo '<article><span class="access-state">' . ($access['available'] ? 'Disponível' : 'Indisponível') . '</span><h2>' . h($access['title']) . '</h2><p>' . h($access['description']) . '</p>';
        echo $access['available']
            ? '<a class="button" href="' . h((string) $access['url']) . '"' . ($access['external'] ? ' target="_blank" rel="noopener"' : '') . '>Acessar ' . ui_icon('external', 17) . '</a>'
            : '<span class="button button--disabled" aria-disabled="true">Ainda não disponível</span>';
        echo '</article>';
    }
    echo '</section><section class="content-section"><div class="section-title"><h2>Tarefas</h2></div><div class="task-list">';
    $stmt = db('work')->prepare("SELECT c.nome coluna,t.titulo,t.status FROM quadros q JOIN colunas c ON c.quadro_id=q.id LEFT JOIN tarefas t ON t.coluna_id=c.id WHERE q.projeto_uuid=:project ORDER BY q.id,c.ordem,t.ordem,t.id");
    $stmt->execute(['project' => $project['uuid']]);
    $hasTasks = false;
    foreach ($stmt as $task) {
        if (!$task['titulo']) continue;
        $hasTasks = true;
        echo '<article><div><span>' . h($task['coluna']) . '</span><h3>' . h($task['titulo']) . '</h3></div><div class="task-meta"><span>' . h($task['status']) . '</span></div></article>';
    }
    if (!$hasTasks) echo '<p class="empty-state">Nenhuma tarefa cadastrada neste projeto.</p>';
    echo '</div></section>';
} else {
    http_response_code(404);
    portal_app_start('Página não encontrada', $user);
    echo '<p class="empty-state">A página solicitada não foi encontrada.</p>';
}

echo '<form class="logout-form" method="post">' . csrf_field() . '<input type="hidden" name="_action" value="logout"><button class="button button--ghost">Sair</button></form>';
ui_app_end();
