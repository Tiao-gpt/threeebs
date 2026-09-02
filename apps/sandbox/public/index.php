<?php

declare(strict_types=1);

require '/var/www/shared/bootstrap.php';

$path = request_path();
$method = (string) ($_SERVER['REQUEST_METHOD'] ?? 'GET');

function sandbox_json(array $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function sandbox_page_start(string $title, bool $editor = false): void
{
    echo '<!doctype html><html lang="pt-BR"><head><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<title>' . h($title) . '</title>'
        . '<link rel="stylesheet" href="/assets/css/editor.css">';
    if ($editor) {
        echo '<script defer src="/vendor/monaco/vs/loader.js"></script>'
            . '<script defer src="/assets/js/editor.js"></script>';
    }
    echo '</head><body>';
}

function sandbox_editor_access(string $projectUuid): array
{
    $access = require_active_sandbox($projectUuid);
    $access['root'] = safe_environment_path((string) $access['environment']['diretorio'], false);
    return $access;
}

function sandbox_editor_audit(array $access, string $action, array $details): void
{
    audit_event(
        $action,
        'arquivo_sandbox',
        null,
        $access['project']['cliente_uuid'] ?? null,
        $access['project']['uuid'],
        $access['environment']['uuid'],
        $details
    );
}

if ($method === 'GET' && in_array($path, ['/api/editor/tree', '/api/editor/file'], true)) {
    try {
        $access = sandbox_editor_access((string) ($_GET['uuid'] ?? ''));
        if ($path === '/api/editor/tree') {
            sandbox_json(['tree' => sandbox_editor_tree($access['root'])]);
        }
        sandbox_json([
            'file' => sandbox_editor_read_file(
                $access['root'],
                (string) ($_GET['path'] ?? '')
            ),
        ]);
    } catch (Throwable $error) {
        error_log('Threeebs editor read failure: ' . $error->getMessage());
        sandbox_json(['error' => public_error_message($error)], $error instanceof ValidationException ? 422 : 500);
    }
}

if ($method === 'POST') {
    require_csrf();
    $action = (string) ($_POST['_action'] ?? '');
    try {
        if ($action === 'login') {
            if (!login_user((string) ($_POST['email'] ?? ''), (string) ($_POST['senha'] ?? ''))) {
                throw new ValidationException('E-mail ou senha inválidos.');
            }
            redirect('/');
        }
        if ($action === 'logout') {
            logout_user();
            redirect('/login');
        }
        if (str_starts_with($action, 'editor_')) {
            $access = sandbox_editor_access((string) ($_POST['projeto_uuid'] ?? ''));
            $itemPath = (string) ($_POST['path'] ?? '');
            if ($action === 'editor_save') {
                $hash = sandbox_editor_write_file(
                    $access['root'],
                    $itemPath,
                    (string) ($_POST['content'] ?? ''),
                    (string) ($_POST['expected_hash'] ?? '')
                );
                sandbox_editor_audit($access, 'sandbox.arquivo_alterado', ['arquivo' => $itemPath]);
                sandbox_json(['ok' => true, 'hash' => $hash]);
            }
            if ($action === 'editor_create_file') {
                sandbox_editor_create_file($access['root'], $itemPath);
                sandbox_editor_audit($access, 'sandbox.arquivo_criado', ['arquivo' => $itemPath]);
                sandbox_json(['ok' => true]);
            }
            if ($action === 'editor_create_directory') {
                sandbox_editor_create_directory($access['root'], $itemPath);
                sandbox_editor_audit($access, 'sandbox.pasta_criada', ['pasta' => $itemPath]);
                sandbox_json(['ok' => true]);
            }
            if ($action === 'editor_rename') {
                $destination = (string) ($_POST['destination'] ?? '');
                sandbox_editor_rename($access['root'], $itemPath, $destination);
                sandbox_editor_audit(
                    $access,
                    'sandbox.item_renomeado',
                    ['origem' => $itemPath, 'destino' => $destination]
                );
                sandbox_json(['ok' => true]);
            }
            if ($action === 'editor_delete') {
                $type = sandbox_editor_delete($access['root'], $itemPath);
                sandbox_editor_audit(
                    $access,
                    $type === 'directory' ? 'sandbox.pasta_excluida' : 'sandbox.arquivo_excluido',
                    [($type === 'directory' ? 'pasta' : 'arquivo') => $itemPath]
                );
                sandbox_json(['ok' => true]);
            }
            throw new ValidationException('Ação do editor inválida.');
        }
    } catch (Throwable $error) {
        error_log('Threeebs sandbox failure: ' . $error->getMessage());
        if (str_starts_with($action, 'editor_')) {
            sandbox_json(
                ['error' => public_error_message($error)],
                $error instanceof ValidationException ? 422 : 500
            );
        }
        flash(public_error_message($error));
        $return = (string) ($_POST['_return'] ?? '/login');
        redirect(str_starts_with($return, '/') && !str_starts_with($return, '//') ? $return : '/');
    }
}

if ($path === '/login' && !auth_user()) {
    sandbox_page_start('Threeebs Sandbox :3 — Entrar');
    echo '<main class="sandbox-auth"><h1>Entrar no Editor Threeebs</h1>';
    show_flash();
    echo '<form method="post">' . csrf_field()
        . '<input type="hidden" name="_action" value="login">'
        . '<p><label>E-mail<br><input type="email" name="email" required></label></p>'
        . '<p><label>Senha<br><input type="password" name="senha" required></label></p>'
        . '<button>Entrar</button></form></main></body></html>';
    exit;
}

$user = require_auth();

if ($path === '/') {
    sandbox_page_start('Editor Threeebs :3');
    echo '<header class="sandbox-header"><p class="sandbox-kicker">Threeebs :3 / Monaco edita</p>'
        . '<h1>Meus projetos</h1></header><main class="sandbox-projects">';
    show_flash();
    echo '<ul>';
    foreach (authorized_projects($user) as $project) {
        echo '<li><a href="/projeto?uuid=' . h($project['uuid']) . '">'
            . h($project['nome']) . '</a> — ' . h($project['cliente_nome']) . '</li>';
    }
    echo '</ul><form method="post">' . csrf_field()
        . '<input type="hidden" name="_action" value="logout"><button>Sair</button>'
        . '</form></main></body></html>';
    exit;
}

if ($path === '/projeto') {
    $access = sandbox_editor_access((string) ($_GET['uuid'] ?? ''));
    $project = $access['project'];
    $environment = $access['environment'];
    $route = db('control')->prepare(
        'SELECT hostname FROM rotas_web
         WHERE ambiente_id=(SELECT id FROM ambientes WHERE uuid=:environment)
           AND ativo=1
         ORDER BY id LIMIT 1'
    );
    $route->execute(['environment' => $environment['uuid']]);
    $hostname = $route->fetchColumn();

    sandbox_page_start('Editor Threeebs — ' . (string) $project['nome'], true);
    echo '<header class="sandbox-header"><p class="sandbox-kicker">Threeebs :3 / Monaco edita</p>'
        . '<div class="sandbox-nav"><h1>' . h($project['nome']) . '</h1>'
        . '<a href="/">Projetos</a>';
    if (is_string($hostname) && $hostname !== '') {
        echo '<a href="' . h(public_route_url($hostname)) . '" target="_blank" rel="noopener">'
            . 'Abrir preview Sandbox</a>';
    }
    echo '<form method="post">' . csrf_field()
        . '<input type="hidden" name="_action" value="logout"><button>Sair</button></form>'
        . '</div></header>';
    show_flash();
    echo '<main class="sandbox-editor" data-threeebs-editor'
        . ' data-project-uuid="' . h($project['uuid']) . '"'
        . ' data-csrf="' . h(csrf_token()) . '">'
        . '<aside class="explorer"><div class="explorer-toolbar">'
        . '<button type="button" id="create-file">+ Arquivo</button>'
        . '<button type="button" id="create-directory">+ Pasta</button>'
        . '<button type="button" id="rename-item" disabled>Renomear</button>'
        . '<button type="button" id="delete-item" class="danger" disabled>Excluir</button>'
        . '</div><nav id="project-tree" class="tree" aria-label="Arquivos do projeto"></nav></aside>'
        . '<section class="editor-pane"><div class="editor-toolbar">'
        . '<span id="editor-path" class="editor-path">Nenhum arquivo aberto</span>'
        . '<button type="button" id="save-file" disabled>Salvar</button></div>'
        . '<div id="editor-status" class="editor-status">Carregando editor…</div>'
        . '<div id="monaco-editor" aria-label="Editor de código"></div></section></main>'
        . '</body></html>';
    exit;
}

http_response_code(404);
sandbox_page_start('Página não encontrada');
echo '<main class="sandbox-auth"><h1>Página não encontrada</h1><p><a href="/">Voltar</a></p></main>'
    . '</body></html>';
