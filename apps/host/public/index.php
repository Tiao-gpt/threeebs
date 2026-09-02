<?php

declare(strict_types=1);

header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');

function host_not_found(): never
{
    http_response_code(404);
    header('Content-Type: text/html; charset=utf-8');
    exit('<h1>Threeebs :3</h1><p>Projeto não encontrado.</p>');
}

$hostname = strtolower(trim((string) ($_SERVER['HTTP_HOST'] ?? '')));
$hostname = rtrim(explode(':', $hostname, 2)[0], '.');
if ($hostname === '' || strlen($hostname) > 253
    || !preg_match('/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\\.)*[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/D', $hostname)) {
    host_not_found();
}

try {
    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        getenv('MYSQL_HOST') ?: 'mysql',
        getenv('MYSQL_PORT') ?: '3306',
        getenv('CONTROL_DB_NAME') ?: 'threeebs_control'
    );
    $pdo = new PDO($dsn, (string) getenv('DB_USER'), (string) getenv('DB_PASSWORD'), [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    $stmt = $pdo->prepare(
        "SELECT a.diretorio FROM rotas_web r
         JOIN ambientes a ON a.id=r.ambiente_id
         JOIN projetos p ON p.id=a.projeto_id
         WHERE r.hostname=:hostname AND r.ativo=1 AND a.status='ativo' AND p.status='ativo'
         LIMIT 1"
    );
    $stmt->execute(['hostname' => $hostname]);
    $relativeRoot = $stmt->fetchColumn();
} catch (Throwable $error) {
    error_log($error->getMessage());
    http_response_code(503);
    exit('Threeebs :3 — serviço temporariamente indisponível.');
}

if (!is_string($relativeRoot)
    || !preg_match(
        '#^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}/(?:sandbox|production)$#D',
        $relativeRoot
    )) {
    host_not_found();
}

$projectsRoot = realpath('/var/www/projects');
$environmentCandidate = $projectsRoot === false ? '' : $projectsRoot . '/' . $relativeRoot;
$environmentRoot = $environmentCandidate === '' ? false : realpath($environmentCandidate);
if ($projectsRoot === false || $environmentRoot === false
    || $environmentRoot !== $environmentCandidate
    || !str_starts_with($environmentRoot . '/', $projectsRoot . '/')) {
    host_not_found();
}

$uri = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
$uri = rawurldecode((string) ($uri ?: '/'));
if (str_contains($uri, "\0") || str_contains($uri, '..') || str_contains($uri, '\\') || str_contains($uri, '%')) {
    host_not_found();
}
$relativeFile = $uri === '/' ? 'index.html' : ltrim($uri, '/');
$segments = explode('/', $relativeFile);
foreach ($segments as $segment) {
    if ($segment === '' || str_starts_with($segment, '.')) {
        host_not_found();
    }
}

$fileCandidate = $environmentRoot . '/' . $relativeFile;
$file = realpath($fileCandidate);
if ($file === false || $file !== $fileCandidate
    || !is_file($file)
    || !str_starts_with($file, $environmentRoot . '/')) {
    host_not_found();
}

$mime = [
    'html' => 'text/html; charset=utf-8',
    'css' => 'text/css; charset=utf-8',
    'js' => 'application/javascript; charset=utf-8',
    'json' => 'application/json; charset=utf-8',
    'txt' => 'text/plain; charset=utf-8',
    'png' => 'image/png',
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'gif' => 'image/gif',
    'webp' => 'image/webp',
    'svg' => 'image/svg+xml',
    'ico' => 'image/x-icon',
    'woff' => 'font/woff',
    'woff2' => 'font/woff2',
    'pdf' => 'application/pdf',
];
$extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
if (!isset($mime[$extension])) {
    host_not_found();
}

header('Content-Type: ' . $mime[$extension]);
header('Content-Length: ' . (string) filesize($file));
header('Cache-Control: no-cache');
readfile($file);
