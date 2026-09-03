<?php

declare(strict_types=1);

const PROJECTS_ROOT = '/var/www/projects';

date_default_timezone_set('UTC');
ini_set('display_errors', PHP_SAPI === 'cli' ? '1' : '0');
ini_set('log_errors', '1');

final class ValidationException extends RuntimeException
{
}

require_once __DIR__ . '/sandbox_files.php';

if (PHP_SAPI !== 'cli' && session_status() !== PHP_SESSION_ACTIVE) {
    $configuredScheme = strtolower((string) parse_url((string) (getenv('APP_URL') ?: ''), PHP_URL_SCHEME));
    $trustProxyHeaders = strtolower((string) (getenv('TRUST_PROXY_HEADERS') ?: 'false')) === 'true';
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || $configuredScheme === 'https'
        || ($trustProxyHeaders
            && strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_strict_mode', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_secure', $https ? '1' : '0');
    ini_set('session.cookie_samesite', 'Lax');
    ini_set('session.cookie_lifetime', '0');
    session_name('threeebs_' . preg_replace('/[^a-z0-9_]/', '', strtolower((string) (getenv('APP_CONTEXT') ?: 'app'))));
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $https,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

if (PHP_SAPI !== 'cli' && !headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: no-referrer');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=(), usb=()');
    header('Cache-Control: no-store');
    $context = strtolower((string) (getenv('APP_CONTEXT') ?: 'app'));
    if ($context === 'sandbox') {
        header("Content-Security-Policy: default-src 'self'; base-uri 'none'; frame-ancestors 'none'; form-action 'self'; object-src 'none'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self' data:; connect-src 'self'; worker-src 'self' blob:");
    } elseif (in_array($context, ['admin', 'portal'], true)) {
        header("Content-Security-Policy: default-src 'self'; base-uri 'none'; frame-ancestors 'none'; form-action 'self'; object-src 'none'; script-src 'self'; style-src 'self'; img-src 'self' data:; connect-src 'none'");
    }
}

function env_required(string $name): string
{
    $value = getenv($name);
    if (!is_string($value) || $value === '') {
        throw new RuntimeException("Variável obrigatória ausente: {$name}");
    }
    return $value;
}

function db(string $domain): PDO
{
    static $connections = [];
    $allowed = [
        'identity' => 'IDENTITY_DB_NAME',
        'control' => 'CONTROL_DB_NAME',
        'work' => 'WORK_DB_NAME',
        'catalog' => 'CATALOG_DB_NAME',
        'finance' => 'FINANCE_DB_NAME',
        'audit' => 'AUDIT_DB_NAME',
    ];
    if (!isset($allowed[$domain])) {
        throw new InvalidArgumentException('Domínio de banco inválido.');
    }
    if (isset($connections[$domain])) {
        return $connections[$domain];
    }
    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        getenv('MYSQL_HOST') ?: 'mysql',
        getenv('MYSQL_PORT') ?: '3306',
        env_required($allowed[$domain])
    );
    $connections[$domain] = new PDO($dsn, env_required('DB_USER'), env_required('DB_PASSWORD'), [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_STRINGIFY_FETCHES => false,
    ]);
    return $connections[$domain];
}

function h(mixed $value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function redirect(string $path): never
{
    header('Location: ' . $path, true, 303);
    exit;
}

function request_path(): string
{
    $path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
    $path = '/' . trim((string) $path, '/');
    return $path === '/' ? '/' : rtrim($path, '/');
}

function csrf_token(): string
{
    if (!isset($_SESSION['csrf']) || !is_string($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . h(csrf_token()) . '">';
}

function require_csrf(): void
{
    $sent = $_POST['csrf'] ?? null;
    $known = $_SESSION['csrf'] ?? null;
    if (!is_string($sent) || !is_string($known) || !hash_equals($known, $sent)) {
        http_response_code(419);
        exit('CSRF inválido. Recarregue a página.');
    }
}

function uuid_v4(): string
{
    $bytes = random_bytes(16);
    $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
    $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
    $hex = bin2hex($bytes);
    return sprintf('%s-%s-%s-%s-%s', substr($hex, 0, 8), substr($hex, 8, 4), substr($hex, 12, 4), substr($hex, 16, 4), substr($hex, 20));
}

function slug(string $value): string
{
    $value = strtolower(trim(iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) ?: $value));
    $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
    return trim($value, '-');
}

function normalize_hostname(string $value): string
{
    $value = strtolower(trim($value));
    $value = preg_replace('#^https?://#', '', $value) ?? $value;
    $value = explode('/', $value, 2)[0];
    $value = rtrim(explode(':', $value, 2)[0], '.');
    if ($value === '' || strlen($value) > 253 || !preg_match('/^(?=.{1,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)*[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/', $value)) {
        throw new InvalidArgumentException('Hostname inválido. Informe apenas o domínio, sem protocolo ou caminho.');
    }
    return $value;
}

function auth_user(): ?array
{
    return isset($_SESSION['user']) && is_array($_SESSION['user']) ? $_SESSION['user'] : null;
}

function current_active_user(?array $user = null): ?array
{
    static $users = [];
    $user ??= auth_user();
    $uuid = is_array($user) ? (string) ($user['uuid'] ?? '') : '';
    if ($uuid === '') {
        return null;
    }
    if (!array_key_exists($uuid, $users)) {
        $stmt = db('identity')->prepare(
            'SELECT id,uuid,nome,email,status
             FROM usuarios
             WHERE uuid=:uuid LIMIT 1'
        );
        $stmt->execute(['uuid' => $uuid]);
        $row = $stmt->fetch();
        $users[$uuid] = is_array($row) && (string) $row['status'] === 'ativo' ? $row : null;
    }
    if (is_array($users[$uuid]) && isset($_SESSION['user'])
        && (string) ($_SESSION['user']['uuid'] ?? '') === $uuid) {
        $_SESSION['user'] = $users[$uuid];
    }
    return $users[$uuid];
}

function installation(): array
{
    $row = db('control')->query('SELECT * FROM configuracao_instalacao WHERE id = 1')->fetch();
    return is_array($row) ? $row : ['status' => 'pendente'];
}

function platform_membership(?array $user = null): ?array
{
    static $memberships = [];
    $user = current_active_user($user);
    $userUuid = is_array($user) ? (string) ($user['uuid'] ?? '') : '';
    if ($userUuid === '') {
        return null;
    }
    if (array_key_exists($userUuid, $memberships)) {
        return $memberships[$userUuid];
    }
    $stmt = db('control')->prepare(
        'SELECT uuid,usuario_uuid,papel,ativo
         FROM plataforma_usuarios
         WHERE usuario_uuid=:user LIMIT 1'
    );
    $stmt->execute(['user' => $userUuid]);
    $membership = $stmt->fetch();
    $memberships[$userUuid] = is_array($membership) ? $membership : null;
    return $memberships[$userUuid];
}

function platform_role(?array $user = null): ?string
{
    $membership = platform_membership($user);
    return $membership && (int) $membership['ativo'] === 1
        ? (string) $membership['papel']
        : null;
}

function is_admin(?array $user = null): bool
{
    return in_array(platform_role($user), ['owner', 'admin'], true);
}

function public_error_message(Throwable $error): string
{
    return $error instanceof ValidationException
        ? $error->getMessage()
        : 'Não foi possível concluir a operação. Tente novamente.';
}

function client_ip(): string
{
    $remote = trim((string) ($_SERVER['REMOTE_ADDR'] ?? ''));
    $trustProxyHeaders = strtolower((string) (getenv('TRUST_PROXY_HEADERS') ?: 'false')) === 'true';
    $forwarded = $trustProxyHeaders
        ? trim((string) ($_SERVER['HTTP_CF_CONNECTING_IP'] ?? ''))
        : '';
    $candidate = $forwarded !== '' ? $forwarded : $remote;
    return filter_var($candidate, FILTER_VALIDATE_IP) ? $candidate : '';
}

function env_int(string $name, int $default, int $minimum, int $maximum): int
{
    $raw = getenv($name);
    if (!is_string($raw) || !preg_match('/^\d+$/', $raw)) {
        return $default;
    }
    return max($minimum, min($maximum, (int) $raw));
}

function authentication_event(
    string $type,
    bool $success,
    ?int $userId,
    string $identifier,
    array $details = []
): void {
    try {
        $encodedDetails = $details === []
            ? null
            : json_encode($details, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        $stmt = db('identity')->prepare(
            'INSERT INTO eventos_autenticacao
                (usuario_id,tipo,sucesso,identificador_hash,ip_hash,user_agent_hash,detalhes)
             VALUES (:user,:type,:success,:identifier,:ip,:user_agent,:details)'
        );
        $remoteAddress = client_ip();
        $userAgent = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');
        $stmt->execute([
            'user' => $userId,
            'type' => $type,
            'success' => $success ? 1 : 0,
            'identifier' => $identifier === '' ? null : hash('sha256', $identifier),
            'ip' => $remoteAddress === '' ? null : hash('sha256', $remoteAddress),
            'user_agent' => $userAgent === '' ? null : hash('sha256', $userAgent),
            'details' => $encodedDetails,
        ]);
    } catch (Throwable $error) {
        error_log('Threeebs authentication event failure: ' . $error->getMessage());
    }
}

function audit_event(
    string $action,
    ?string $entityType = null,
    ?string $entityUuid = null,
    ?string $clientUuid = null,
    ?string $projectUuid = null,
    ?string $environmentUuid = null,
    array $details = []
): void {
    try {
        $user = auth_user();
        $requestId = preg_replace(
            '/[^a-zA-Z0-9._:-]/',
            '',
            (string) ($_SERVER['HTTP_X_REQUEST_ID'] ?? '')
        ) ?? '';
        $remoteAddress = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
        $encodedDetails = $details === []
            ? null
            : json_encode($details, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        $stmt = db('audit')->prepare(
            'INSERT INTO eventos
                (uuid,ator_tipo,ator_uuid,origem,acao,entidade_tipo,entidade_uuid,
                 cliente_uuid,projeto_uuid,ambiente_uuid,request_id,ip_hash,detalhes)
             VALUES
                (:uuid,:actor_type,:actor,:origin,:action,:entity_type,:entity,
                 :client,:project,:environment,:request,:ip,:details)'
        );
        $stmt->execute([
            'uuid' => uuid_v4(),
            'actor_type' => $user ? 'usuario' : 'sistema',
            'actor' => $user['uuid'] ?? null,
            'origin' => substr((string) (getenv('APP_CONTEXT') ?: 'app'), 0, 50),
            'action' => $action,
            'entity_type' => $entityType,
            'entity' => $entityUuid,
            'client' => $clientUuid,
            'project' => $projectUuid,
            'environment' => $environmentUuid,
            'request' => $requestId === '' ? null : substr($requestId, 0, 100),
            'ip' => $remoteAddress === '' ? null : hash('sha256', $remoteAddress),
            'details' => $encodedDetails,
        ]);
    } catch (Throwable $error) {
        error_log('Threeebs audit failure: ' . $error->getMessage());
    }
}

function login_user(string $email, string $password): bool
{
    $email = strtolower(trim($email));
    $identity = db('identity');
    $identifierHash = hash('sha256', $email);
    $ip = client_ip();
    $ipHash = $ip === '' ? null : hash('sha256', $ip);
    $maximumAttempts = env_int('LOGIN_MAX_ATTEMPTS', 5, 1, 100);
    $windowSeconds = env_int('LOGIN_WINDOW_SECONDS', 600, 1, 86400);
    $ipMaximumAttempts = env_int('LOGIN_IP_MAX_ATTEMPTS', 25, $maximumAttempts, 1000);

    $stmt = $identity->prepare(
        "SELECT u.id,u.uuid,u.nome,u.email,u.status,u.bloqueado_ate,u.bloqueio_motivo,
                (u.bloqueado_ate IS NOT NULL AND u.bloqueado_ate > UTC_TIMESTAMP(6))
                    AS esta_bloqueado,
                (u.bloqueado_ate IS NOT NULL AND u.bloqueado_ate <= UTC_TIMESTAMP(6))
                    AS bloqueio_expirado,
                c.segredo_hash
         FROM usuarios u
         JOIN credenciais c ON c.usuario_id=u.id
         WHERE u.email=:email AND c.tipo='senha' AND c.ativa=1
         ORDER BY c.id DESC LIMIT 1"
    );
    $stmt->execute(['email' => $email]);
    $user = $stmt->fetch();
    $userId = is_array($user) ? (int) $user['id'] : null;

    if ($ipHash !== null) {
        $ipAttempts = $identity->prepare(
            "SELECT COUNT(*)
             FROM eventos_autenticacao
             WHERE tipo='login_falha'
               AND ip_hash=:ip
               AND ocorrido_em>=DATE_SUB(UTC_TIMESTAMP(6), INTERVAL {$windowSeconds} SECOND)"
        );
        $ipAttempts->execute(['ip' => $ipHash]);
        if ((int) $ipAttempts->fetchColumn() >= $ipMaximumAttempts) {
            authentication_event('login_bloqueado', false, $userId, $email, ['motivo' => 'limite_ip']);
            return false;
        }
    }

    if ($user && (int) $user['esta_bloqueado'] === 1) {
        authentication_event(
            'login_bloqueado',
            false,
            $userId,
            $email,
            ['motivo' => (string) ($user['bloqueio_motivo'] ?: 'seguranca')]
        );
        return false;
    }

    if ($user && (int) $user['bloqueio_expirado'] === 1) {
        $clear = $identity->prepare(
            'UPDATE usuarios
             SET bloqueado_ate=NULL,bloqueio_motivo=NULL
             WHERE id=:id AND bloqueado_ate<=UTC_TIMESTAMP(6)'
        );
        $clear->execute(['id' => $userId]);
    }

    $credentialHash = $user
        ? (string) $user['segredo_hash']
        : '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.';
    $passwordMatches = password_verify($password, $credentialHash);
    $valid = $user && (string) $user['status'] === 'ativo' && $passwordMatches;

    if (!$valid) {
        authentication_event('login_falha', false, $userId, $email);
        $pairAttempts = $identity->prepare(
            "SELECT COUNT(*)
             FROM eventos_autenticacao
             WHERE tipo='login_falha'
               AND identificador_hash=:identifier
               AND ((:ip_null IS NULL AND ip_hash IS NULL) OR ip_hash=:ip_value)
               AND ocorrido_em>=DATE_SUB(UTC_TIMESTAMP(6), INTERVAL {$windowSeconds} SECOND)"
        );
        $pairAttempts->execute([
            'identifier' => $identifierHash,
            'ip_null' => $ipHash,
            'ip_value' => $ipHash,
        ]);
        if ($user && (string) $user['status'] === 'ativo'
            && (int) $pairAttempts->fetchColumn() >= $maximumAttempts) {
            $lockSeconds = env_int('LOGIN_LOCK_SECONDS', 900, 1, 86400);
            $lock = $identity->prepare(
                "UPDATE usuarios
                 SET bloqueado_ate=DATE_ADD(UTC_TIMESTAMP(6), INTERVAL {$lockSeconds} SECOND),
                     bloqueio_motivo='tentativas_login'
                 WHERE id=:id"
            );
            $lock->execute(['id' => $userId]);
            authentication_event(
                'login_bloqueado',
                false,
                $userId,
                $email,
                ['motivo' => 'tentativas_login']
            );
        }
        return false;
    }

    $update = $identity->prepare(
        'UPDATE usuarios
         SET ultimo_login_em=UTC_TIMESTAMP(6),bloqueado_ate=NULL,bloqueio_motivo=NULL
         WHERE id=:id'
    );
    $update->execute(['id' => $userId]);
    authentication_event('login_sucesso', true, $userId, $email);

    session_regenerate_id(true);
    unset(
        $user['segredo_hash'],
        $user['bloqueado_ate'],
        $user['bloqueio_motivo'],
        $user['esta_bloqueado'],
        $user['bloqueio_expirado']
    );
    $_SESSION['user'] = $user;
    unset($_SESSION['csrf']);
    return true;
}

function logout_user(): void
{
    $user = auth_user();
    if ($user) {
        authentication_event(
            'logout',
            true,
            isset($user['id']) ? (int) $user['id'] : null,
            (string) ($user['email'] ?? '')
        );
    }
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires' => time() - 42000,
            'path' => $params['path'],
            'domain' => $params['domain'],
            'secure' => (bool) $params['secure'],
            'httponly' => (bool) $params['httponly'],
            'samesite' => (string) ($params['samesite'] ?? 'Lax'),
        ]);
    }
    session_destroy();
}

function require_auth(): array
{
    $sessionUser = auth_user();
    if (!$sessionUser) {
        redirect('/login');
    }
    $user = current_active_user($sessionUser);
    if (!$user) {
        logout_user();
        redirect('/login');
    }
    return $user;
}

function require_admin(): array
{
    $user = require_auth();
    if (!is_admin($user)) {
        http_response_code(403);
        exit('Acesso administrativo negado.');
    }
    return $user;
}

function authorized_project(string $projectUuid, ?array $user = null): ?array
{
    $user = current_active_user($user ?? auth_user());
    if (!$user || $projectUuid === '') {
        return null;
    }
    if (is_admin($user)) {
        $stmt = db('control')->prepare(
            'SELECT p.*,c.nome cliente_nome,c.uuid cliente_uuid
             FROM projetos p
             JOIN clientes c ON c.id=p.cliente_id
             WHERE p.uuid=:project LIMIT 1'
        );
        $stmt->execute(['project' => $projectUuid]);
    } else {
        $stmt = db('control')->prepare(
            "SELECT p.*,c.nome cliente_nome,c.uuid cliente_uuid
             FROM projetos p
             JOIN clientes c ON c.id=p.cliente_id
             JOIN cliente_usuarios cu
               ON cu.cliente_id=c.id AND cu.usuario_uuid=:client_user AND cu.ativo=1
             JOIN projeto_usuarios pu
               ON pu.projeto_id=p.id AND pu.usuario_uuid=:project_user AND pu.ativo=1
             WHERE p.uuid=:project
               AND p.status='ativo'
               AND c.status='ativo'
             LIMIT 1"
        );
        $stmt->execute([
            'project' => $projectUuid,
            'client_user' => $user['uuid'],
            'project_user' => $user['uuid'],
        ]);
    }
    $project = $stmt->fetch();
    return is_array($project) ? $project : null;
}

function authorized_projects(?array $user = null): array
{
    $user = current_active_user($user ?? auth_user());
    if (!$user) {
        return [];
    }
    if (is_admin($user)) {
        return db('control')->query(
            "SELECT p.uuid,p.nome,p.status,c.nome cliente_nome,c.uuid cliente_uuid
             FROM projetos p
             JOIN clientes c ON c.id=p.cliente_id
             ORDER BY p.nome"
        )->fetchAll();
    }
    $stmt = db('control')->prepare(
        "SELECT p.uuid,p.nome,p.status,c.nome cliente_nome,c.uuid cliente_uuid
         FROM projetos p
         JOIN clientes c ON c.id=p.cliente_id
         JOIN cliente_usuarios cu
           ON cu.cliente_id=c.id AND cu.usuario_uuid=:client_user AND cu.ativo=1
         JOIN projeto_usuarios pu
           ON pu.projeto_id=p.id AND pu.usuario_uuid=:project_user AND pu.ativo=1
         WHERE p.status='ativo' AND c.status='ativo'
         ORDER BY p.nome"
    );
    $stmt->execute([
        'client_user' => $user['uuid'],
        'project_user' => $user['uuid'],
    ]);
    return $stmt->fetchAll();
}

function authorized_clients(?array $user = null): array
{
    $user = current_active_user($user ?? auth_user());
    if (!$user) {
        return [];
    }
    if (is_admin($user)) {
        return db('control')->query(
            'SELECT uuid,nome,status,NULL papel FROM clientes ORDER BY nome'
        )->fetchAll();
    }
    $stmt = db('control')->prepare(
        "SELECT c.uuid,c.nome,c.status,cu.papel
         FROM clientes c
         JOIN cliente_usuarios cu ON cu.cliente_id=c.id
         WHERE cu.usuario_uuid=:user AND cu.ativo=1 AND c.status='ativo'
         ORDER BY c.nome"
    );
    $stmt->execute(['user' => $user['uuid']]);
    return $stmt->fetchAll();
}

function user_has_project(string $projectUuid, ?array $user = null): bool
{
    return authorized_project($projectUuid, $user) !== null;
}

function require_project(string $projectUuid): array
{
    $user = require_auth();
    $project = authorized_project($projectUuid, $user);
    if (!$project) {
        http_response_code(is_admin($user) ? 404 : 403);
        exit(is_admin($user) ? 'Projeto não encontrado.' : 'Você não possui acesso a este projeto.');
    }
    return $project;
}

function require_active_sandbox(string $projectUuid): array
{
    $project = require_project($projectUuid);
    $stmt = db('control')->prepare(
        "SELECT uuid,diretorio,status
         FROM ambientes
         WHERE projeto_id=:project AND tipo='sandbox' AND status='ativo'
         LIMIT 1"
    );
    $stmt->execute(['project' => $project['id']]);
    $environment = $stmt->fetch();
    if (!is_array($environment) || !is_string($environment['diretorio'])
        || $environment['diretorio'] === '') {
        http_response_code(403);
        exit('Sandbox ativo indisponível.');
    }
    return ['project' => $project, 'environment' => $environment];
}

function public_route_url(string $hostname): string
{
    $hostname = normalize_hostname($hostname);
    $configured = strtolower((string) (getenv('PUBLIC_ROUTE_SCHEME') ?: ''));
    $scheme = in_array($configured, ['http', 'https'], true)
        ? $configured
        : ((string) (getenv('APP_ENV') ?: 'development') === 'production' ? 'https' : 'http');
    return $scheme . '://' . $hostname;
}

function editor_project_url(string $projectUuid): string
{
    $base = rtrim((string) (getenv('SANDBOX_URL') ?: 'http://127.0.0.1:6016'), '/');
    return $base . '/projeto?uuid=' . rawurlencode($projectUuid);
}

function project_environment_navigation(int $projectId): array
{
    $stmt = db('control')->prepare(
        "SELECT a.uuid,a.tipo,a.nome,a.diretorio,a.status,
                (SELECT r.hostname
                   FROM rotas_web r
                  WHERE r.ambiente_id=a.id AND r.ativo=1
                  ORDER BY r.id LIMIT 1) hostname
         FROM ambientes a
         WHERE a.projeto_id=:project
         ORDER BY a.tipo,a.id"
    );
    $stmt->execute(['project' => $projectId]);
    $result = [];
    foreach ($stmt as $environment) {
        $type = (string) $environment['tipo'];
        if (!isset($result[$type])) {
            $environment['url'] = is_string($environment['hostname'])
                && $environment['hostname'] !== ''
                ? public_route_url($environment['hostname'])
                : null;
            $result[$type] = $environment;
        }
    }
    return $result;
}

function advance_user_journey(string $assignmentUuid): string
{
    $control = db('control');
    $control->beginTransaction();
    try {
        $stmt = $control->prepare(
            "SELECT uj.id, uj.jornada_id, uj.status, uj.etapa_atual_id,
                    j.status jornada_status, e.ordem etapa_atual_ordem
             FROM usuario_jornadas uj
             JOIN jornadas j ON j.id = uj.jornada_id
             LEFT JOIN jornada_etapas e ON e.id = uj.etapa_atual_id
             WHERE uj.uuid = :uuid
             FOR UPDATE"
        );
        $stmt->execute(['uuid' => $assignmentUuid]);
        $assignment = $stmt->fetch();
        if (!$assignment) {
            throw new ValidationException('Jornada do usuário não encontrada.');
        }
        if ($assignment['jornada_status'] !== 'ativa') {
            throw new ValidationException('A jornada está inativa.');
        }
        if ($assignment['status'] === 'concluida') {
            $control->commit();
            return 'A jornada já estava concluída.';
        }

        if ($assignment['status'] === 'pendente') {
            $next = $control->prepare(
                "SELECT id, titulo FROM jornada_etapas
                 WHERE jornada_id = :journey AND status = 'ativa'
                 ORDER BY ordem, id LIMIT 1"
            );
            $next->execute(['journey' => $assignment['jornada_id']]);
            $stage = $next->fetch();
            if (!$stage) {
                throw new ValidationException('A jornada não possui etapas ativas.');
            }
            $history = $control->prepare(
                "INSERT INTO usuario_jornada_etapas
                    (usuario_jornada_id, jornada_etapa_id, status, iniciada_em)
                 VALUES (:assignment, :stage, 'em_andamento', UTC_TIMESTAMP(6))
                 ON DUPLICATE KEY UPDATE
                    status = IF(status = 'concluida', status, 'em_andamento'),
                    iniciada_em = COALESCE(iniciada_em, UTC_TIMESTAMP(6))"
            );
            $history->execute([
                'assignment' => $assignment['id'],
                'stage' => $stage['id'],
            ]);
            $update = $control->prepare(
                "UPDATE usuario_jornadas
                 SET status = 'em_andamento', etapa_atual_id = :stage,
                     iniciada_em = COALESCE(iniciada_em, UTC_TIMESTAMP(6)),
                     concluida_em = NULL
                 WHERE id = :id"
            );
            $update->execute(['stage' => $stage['id'], 'id' => $assignment['id']]);
            $control->commit();
            return 'Jornada iniciada em: ' . $stage['titulo'] . '.';
        }

        if (!$assignment['etapa_atual_id'] || $assignment['etapa_atual_ordem'] === null) {
            throw new ValidationException('A jornada em andamento não possui etapa atual válida.');
        }

        $complete = $control->prepare(
            "INSERT INTO usuario_jornada_etapas
                (usuario_jornada_id, jornada_etapa_id, status, iniciada_em, concluida_em)
             VALUES (:assignment, :stage, 'concluida', UTC_TIMESTAMP(6), UTC_TIMESTAMP(6))
             ON DUPLICATE KEY UPDATE
                status = 'concluida',
                concluida_em = COALESCE(concluida_em, UTC_TIMESTAMP(6))"
        );
        $complete->execute([
            'assignment' => $assignment['id'],
            'stage' => $assignment['etapa_atual_id'],
        ]);

        $next = $control->prepare(
            "SELECT e.id, e.titulo
             FROM jornada_etapas e
             WHERE e.jornada_id = :journey
               AND e.status = 'ativa'
               AND e.ordem > :current_order
               AND NOT EXISTS (
                   SELECT 1 FROM usuario_jornada_etapas uje
                   WHERE uje.usuario_jornada_id = :assignment
                     AND uje.jornada_etapa_id = e.id
                     AND uje.status = 'concluida'
               )
             ORDER BY e.ordem, e.id LIMIT 1"
        );
        $next->execute([
            'journey' => $assignment['jornada_id'],
            'current_order' => $assignment['etapa_atual_ordem'],
            'assignment' => $assignment['id'],
        ]);
        $stage = $next->fetch();

        if (!$stage) {
            $update = $control->prepare(
                "UPDATE usuario_jornadas
                 SET status = 'concluida', etapa_atual_id = NULL,
                     concluida_em = UTC_TIMESTAMP(6)
                 WHERE id = :id"
            );
            $update->execute(['id' => $assignment['id']]);
            $control->commit();
            return 'Jornada concluída.';
        }

        $history = $control->prepare(
            "INSERT INTO usuario_jornada_etapas
                (usuario_jornada_id, jornada_etapa_id, status, iniciada_em)
             VALUES (:assignment, :stage, 'em_andamento', UTC_TIMESTAMP(6))
             ON DUPLICATE KEY UPDATE
                status = IF(status = 'concluida', status, 'em_andamento'),
                iniciada_em = COALESCE(iniciada_em, UTC_TIMESTAMP(6))"
        );
        $history->execute([
            'assignment' => $assignment['id'],
            'stage' => $stage['id'],
        ]);
        $update = $control->prepare(
            "UPDATE usuario_jornadas
             SET etapa_atual_id = :stage, status = 'em_andamento'
             WHERE id = :id"
        );
        $update->execute(['stage' => $stage['id'], 'id' => $assignment['id']]);
        $control->commit();
        return 'Próxima etapa: ' . $stage['titulo'] . '.';
    } catch (Throwable $error) {
        if ($control->inTransaction()) {
            $control->rollBack();
        }
        throw $error;
    }
}

function safe_environment_path(string $relative, bool $create = false): string
{
    $relative = trim($relative, '/');
    if (!preg_match(
        '#^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}/(?:sandbox|production)$#D',
        $relative
    )) {
        throw new RuntimeException('Diretório de ambiente inválido.');
    }
    $root = realpath(PROJECTS_ROOT);
    if ($root === false || is_link(PROJECTS_ROOT)) {
        throw new RuntimeException('Storage de projetos indisponível.');
    }

    $current = $root;
    foreach (explode('/', $relative) as $segment) {
        $current .= '/' . $segment;
        if (is_link($current)) {
            throw new RuntimeException('Diretório de ambiente inválido.');
        }
        if ($create && !is_dir($current)
            && !mkdir($current, 0775)
            && !is_dir($current)) {
            throw new RuntimeException('Não foi possível criar o diretório do ambiente.');
        }
    }

    $resolved = realpath($current);
    if ($resolved === false
        || $resolved !== $current
        || !str_starts_with($resolved . '/', $root . '/')) {
        throw new RuntimeException('Diretório fora do storage permitido.');
    }
    return $resolved;
}

function atomic_write(string $path, string $content): void
{
    $root = realpath(PROJECTS_ROOT);
    $directory = realpath(dirname($path));
    $environmentType = $directory === false ? '' : basename($directory);
    $context = strtolower((string) (getenv('APP_CONTEXT') ?: 'app'));
    $writeAllowed = $environmentType === 'sandbox'
        || ($context === 'admin' && $environmentType === 'production');
    if ($root === false || $directory === false
        || basename($path) !== 'index.html'
        || !$writeAllowed
        || is_link(dirname($path))
        || is_link($path)
        || !str_starts_with($directory . '/', $root . '/')) {
        throw new RuntimeException('Destino de escrita inválido.');
    }

    $temporary = tempnam($directory, '.threeebs-');
    if ($temporary === false
        || file_put_contents($temporary, $content, LOCK_EX) === false
        || !rename($temporary, $directory . '/index.html')) {
        if (is_string($temporary) && is_file($temporary)) {
            unlink($temporary);
        }
        throw new RuntimeException('Não foi possível salvar o arquivo.');
    }
    chmod($directory . '/index.html', 0664);
}

function page_start(string $title): void
{
    echo '<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . h($title) . '</title></head><body>';
    echo '<header><h1>' . h($title) . '</h1></header><hr>';
}

function page_end(): void
{
    echo '</body></html>';
}

function flash(string $message): void
{
    $_SESSION['flash'] = $message;
}

function show_flash(): void
{
    if (isset($_SESSION['flash'])) {
        echo '<p><strong>' . h($_SESSION['flash']) . '</strong></p>';
        unset($_SESSION['flash']);
    }
}
