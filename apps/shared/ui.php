<?php

declare(strict_types=1);

function ui_icon(string $name, int $size = 20): string
{
    $paths = match ($name) {
        'home' => '<path d="m3 11 9-8 9 8"/><path d="M5 10v10h14V10"/><path d="M9 20v-6h6v6"/>',
        'users' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>',
        'clients' => '<rect x="3" y="7" width="18" height="13" rx="2"/><path d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2M8 12h8"/>',
        'projects' => '<path d="M3 7h6l2 2h10v10a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2Z"/><path d="M3 7V5a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2"/>',
        'journey' => '<circle cx="6" cy="19" r="2"/><circle cx="18" cy="5" r="2"/><path d="M8 19h3a4 4 0 0 0 4-4V9a4 4 0 0 1 4-4"/>',
        'tasks' => '<rect x="3" y="3" width="18" height="18" rx="2"/><path d="m8 12 2 2 5-5M8 7h.01M8 17h8"/>',
        'server' => '<rect x="3" y="4" width="18" height="6" rx="2"/><rect x="3" y="14" width="18" height="6" rx="2"/><path d="M7 7h.01M7 17h.01"/>',
        'menu' => '<path d="M4 6h16M4 12h16M4 18h16"/>',
        'close' => '<path d="m6 6 12 12M18 6 6 18"/>',
        'panel' => '<rect x="3" y="3" width="18" height="18" rx="2"/><path d="M9 3v18m6-14-4 5 4 5"/>',
        'sun' => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.42 1.42M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.42-1.42M17.66 6.34l1.41-1.41"/>',
        'moon' => '<path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79Z"/>',
        'external' => '<path d="M15 3h6v6M10 14 21 3"/><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/>',
        default => '<circle cx="12" cy="12" r="9"/><path d="M12 8v8M8 12h8"/>',
    };
    return '<svg aria-hidden="true" width="' . $size . '" height="' . $size
        . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"'
        . ' stroke-linecap="round" stroke-linejoin="round">' . $paths . '</svg>';
}

function ui_app_start(string $title, string $product, array $navigation, string $currentPath, array $user): void
{
    echo '<!doctype html><html lang="pt-BR" data-theme="dark" data-product="' . h(strtolower($product)) . '"><head>'
        . '<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<meta name="theme-color" content="#07100f"><title>' . h($title) . ' · Threeebs</title>'
        . '<link rel="stylesheet" href="/assets/identity/css/threeebs.css">'
        . '<link rel="stylesheet" href="/assets/identity/css/shell.css">'
        . '<link rel="stylesheet" href="/assets/identity/css/pages.css">'
        . '<link rel="stylesheet" href="/assets/identity/css/responsive.css">'
        . '<link rel="stylesheet" href="/assets/css/main.css"></head><body>'
        . '<a class="skip-link" href="#main-content">Pular para o conteúdo</a>'
        . '<div class="app-shell" id="app-shell"><aside class="sidebar" id="sidebar" aria-label="Navegação principal">'
        . '<div class="sidebar-brand"><a class="brand-text" href="/" aria-label="Threeebs ' . h($product) . ' — início">'
        . '<strong class="brand-lockup">Threeebs <span>:3</span></strong><strong class="brand-symbol">:3</strong></a>'
        . '<button class="icon-button sidebar-close" type="button" data-sidebar-close aria-label="Fechar menu">' . ui_icon('close') . '</button></div>'
        . '<nav class="sidebar-nav" aria-label="Seções do ' . h($product) . '"><section class="nav-group">'
        . '<p class="nav-label">' . h($product) . '</p>';
    foreach ($navigation as $item) {
        $active = $currentPath === $item['href'];
        echo '<a class="nav-item' . ($active ? ' is-active' : '') . '" href="' . h($item['href']) . '"'
            . ($active ? ' aria-current="page"' : '') . ' data-tooltip="' . h($item['label']) . '">'
            . ui_icon($item['icon']) . '<span>' . h($item['label']) . '</span></a>';
    }
    echo '</section></nav><div class="sidebar-footer"><div class="version-chip"><span></span><strong>Alpha</strong><small>Internal</small></div>'
        . '<button class="sidebar-collapse" type="button" data-sidebar-collapse aria-label="Retrair menu" aria-expanded="true">'
        . ui_icon('panel') . '<span>Retrair menu</span></button></div></aside>'
        . '<div class="app-main"><header class="topbar"><div class="topbar-start">'
        . '<button class="icon-button mobile-menu" type="button" data-sidebar-open aria-label="Abrir menu" aria-controls="sidebar" aria-expanded="false">' . ui_icon('menu') . '</button>'
        . '<div class="breadcrumb"><a href="/">' . h($product) . '</a><span>/</span><strong>' . h($title) . '</strong></div></div>'
        . '<div class="topbar-actions"><span class="user-chip">' . h((string) ($user['nome'] ?: $user['email'])) . '</span>'
        . '<button class="icon-button theme-toggle" type="button" data-theme-toggle aria-label="Alternar tema">'
        . '<span class="theme-icon theme-icon--sun">' . ui_icon('sun') . '</span><span class="theme-icon theme-icon--moon">' . ui_icon('moon') . '</span></button></div>'
        . '</header><main class="page-content" id="main-content" tabindex="-1">';
}

function ui_app_end(): void
{
    echo '</main></div></div><div class="sidebar-scrim" data-sidebar-close hidden></div>'
        . '<script src="/assets/identity/js/shell.js" defer></script></body></html>';
}

function ui_public_start(string $title, string $product, string $bodyClass = ''): void
{
    echo '<!doctype html><html lang="pt-BR" data-theme="dark" data-product="' . h(strtolower($product)) . '"><head>'
        . '<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<meta name="theme-color" content="#07100f"><title>' . h($title) . ' · Threeebs</title>'
        . '<link rel="stylesheet" href="/assets/identity/css/threeebs.css">'
        . '<link rel="stylesheet" href="/assets/identity/css/pages.css">'
        . '<link rel="stylesheet" href="/assets/identity/css/experiences.css">'
        . '<link rel="stylesheet" href="/assets/css/main.css"></head><body class="public-page ' . h($bodyClass) . '">'
        . '<a class="skip-link" href="#main-content">Pular para o conteúdo</a>'
        . '<header class="public-topbar"><a class="brand-text" href="/"><strong>Threeebs <span>:3</span></strong></a>'
        . '<nav aria-label="Navegação pública"><a href="/#como-funciona">Como funciona</a><a href="/#interesse">Começar um projeto</a>'
        . '<a class="button button--secondary" href="/login">Entrar</a></nav></header><main id="main-content">';
}

function ui_public_end(): void
{
    echo '</main><footer class="public-footer"><strong>Threeebs :3</strong><span>Projetos digitais com caminho claro.</span></footer></body></html>';
}
