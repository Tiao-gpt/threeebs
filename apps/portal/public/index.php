<?php

declare(strict_types=1);

require '/var/www/shared/bootstrap.php';

$path = request_path();

function portal_page_start(string $title): void
{
    echo '<!doctype html><html lang="pt-BR"><head><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<title>' . h($title) . '</title>'
        . '<link rel="stylesheet" href="/assets/css/base.css">'
        . '<link rel="stylesheet" href="/assets/css/main.css">'
        . '</head><body class="portal-app">'
        . '<header class="portal-header"><p class="app-kicker">Threeebs :3 / Portal</p>'
        . '<h1>' . h($title) . '</h1></header>';
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    require_csrf();
    $action = (string) ($_POST['_action'] ?? '');
    if ($action === 'login') {
        if (login_user((string) ($_POST['email'] ?? ''), (string) ($_POST['senha'] ?? ''))) {
            redirect('/');
        }
        flash('E-mail ou senha inválidos.');
        redirect('/login');
    }
    if ($action === 'logout') {
        logout_user();
        redirect('/');
    }
}

if ($path === '/login' && !auth_user()) {
    portal_page_start('Threeebs :3 — Entrar');
    show_flash();
    echo '<form method="post">' . csrf_field() . '<input type="hidden" name="_action" value="login"><p><label>E-mail<br><input type="email" name="email" required></label></p><p><label>Senha<br><input type="password" name="senha" required></label></p><button>Entrar</button></form>';
    page_end();
    exit;
}

if (!auth_user()) {
    portal_page_start('Threeebs :3');
    echo '<p><a href="/login">Entrar</a></p>';
    page_end();
    exit;
}

$user = require_auth();
portal_page_start('Threeebs :3');
echo '<nav><a href="/">Início</a></nav><hr>';
show_flash();

if ($path === '/') {
    echo '<h2>Olá, ' . h($user['nome']) . '</h2>';
    echo '<h3>Meus clientes</h3><ul>';
    foreach (authorized_clients($user) as $client) {
        echo '<li>' . h($client['nome']) . ' — ' . h($client['papel']) . '</li>';
    }
    echo '</ul><h3>Meus projetos</h3>';
    $projects = authorized_projects($user);
    $projectsByUuid = [];
    if ($projects === []) {
        echo '<p>Nenhum projeto associado à sua conta.</p>';
    } else {
        echo '<ul>';
        foreach ($projects as $project) {
            $projectsByUuid[(string) $project['uuid']] = (string) $project['nome'];
            echo '<li><a href="/projeto?uuid=' . h($project['uuid']) . '">'
                . h($project['nome']) . '</a> — ' . h($project['cliente_nome']) . '</li>';
        }
        echo '</ul>';
    }

    echo '<h3>Tarefas dos meus projetos</h3>';
    if ($projectsByUuid === []) {
        echo '<p>Nenhuma tarefa disponível.</p>';
    } else {
        $placeholders = implode(',', array_fill(0, count($projectsByUuid), '?'));
        $stmt = db('work')->prepare(
            "SELECT t.titulo,t.status,q.projeto_uuid,c.nome coluna,
                    EXISTS (
                        SELECT 1
                        FROM tarefa_responsaveis tr
                        WHERE tr.tarefa_id=t.id AND tr.usuario_uuid=?
                    ) responsavel
             FROM tarefas t
             JOIN colunas c ON c.id=t.coluna_id
             JOIN quadros q ON q.id=c.quadro_id
             WHERE q.projeto_uuid IN ({$placeholders})
             ORDER BY t.created_at DESC,t.id DESC"
        );
        $stmt->execute(array_merge([(string) $user['uuid']], array_keys($projectsByUuid)));
        $tasks = $stmt->fetchAll();
        if ($tasks === []) {
            echo '<p>Nenhuma tarefa cadastrada nos seus projetos.</p>';
        } else {
            echo '<ul>';
            foreach ($tasks as $task) {
                echo '<li><strong>' . h($task['titulo']) . '</strong> — '
                    . h($projectsByUuid[(string) $task['projeto_uuid']] ?? 'Projeto')
                    . ' / ' . h($task['coluna']) . ' — ' . h($task['status'])
                    . ((int) $task['responsavel'] === 1 ? ' — responsável: você' : '')
                    . '</li>';
            }
            echo '</ul>';
        }
    }
} elseif ($path === '/projeto') {
    $project = require_project((string) ($_GET['uuid'] ?? ''));
    $navigation = project_environment_navigation((int) $project['id']);
    echo '<h2>' . h($project['nome']) . '</h2><p>Cliente: ' . h($project['cliente_nome'])
        . '</p><p>' . nl2br(h($project['descricao'])) . '</p><h3>Acessos do projeto</h3><ul>';
    $production = $navigation['production'] ?? null;
    $sandbox = $navigation['sandbox'] ?? null;
    echo '<li><strong>Produção:</strong> ';
    echo is_array($production) && is_string($production['url'])
        ? '<a href="' . h($production['url']) . '" target="_blank" rel="noopener">Abrir produção</a>'
        : 'sem rota ativa';
    echo '</li><li><strong>Sandbox:</strong> ';
    echo is_array($sandbox) && is_string($sandbox['url'])
        ? '<a href="' . h($sandbox['url']) . '" target="_blank" rel="noopener">Abrir preview Sandbox</a>'
        : 'sem rota ativa';
    echo '</li><li><strong>Editor Threeebs:</strong> ';
    echo is_array($sandbox) && (string) $sandbox['status'] === 'ativo'
        ? '<a href="' . h(editor_project_url((string) $project['uuid'])) . '">Abrir Monaco edita</a>'
        : 'Sandbox ativo indisponível';
    echo '</li></ul><h3>Ambientes</h3><ul>';
    foreach ($navigation as $environment) {
        echo '<li>' . h($environment['nome']) . ' — Status: ' . h($environment['status'])
            . ' — Diretório: ' . h($environment['diretorio']) . '</li>';
    }
    echo '</ul><h3>Tarefas</h3>';
    $stmt = db('work')->prepare("SELECT c.nome coluna,t.titulo,t.descricao,t.status FROM quadros q JOIN colunas c ON c.quadro_id=q.id LEFT JOIN tarefas t ON t.coluna_id=c.id WHERE q.projeto_uuid=:project ORDER BY q.id,c.ordem,t.ordem,t.id");
    $stmt->execute(['project' => $project['uuid']]);
    $current = null;
    foreach ($stmt as $task) {
        if ($current !== $task['coluna']) {
            if ($current !== null) echo '</ul>';
            $current = $task['coluna'];
            echo '<h4>' . h($current) . '</h4><ul>';
        }
        if ($task['titulo']) echo '<li>' . h($task['titulo']) . ' — ' . h($task['status']) . '</li>';
    }
    if ($current !== null) echo '</ul>';
} else {
    http_response_code(404);
    echo '<p>Página não encontrada.</p>';
}

echo '<hr><form method="post">' . csrf_field() . '<input type="hidden" name="_action" value="logout"><button>Sair</button></form>';
page_end();
