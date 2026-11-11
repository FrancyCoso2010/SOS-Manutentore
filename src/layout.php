<?php

declare(strict_types=1);

/**
 * Voci di menu visibili per ruolo, raggruppate per sezione.
 *
 * @return array<int, array{href: string, label: string, key: string, group: string}>
 */
function nav_items(string $role): array
{
    $items = [
        ['href' => 'dashboard.php', 'label' => 'Dashboard', 'key' => 'dashboard', 'group' => 'Lavoro'],
        ['href' => 'turno.php', 'label' => 'Turno di oggi', 'key' => 'turno', 'group' => 'Lavoro'],
    ];

    if ($role === 'operatore') {
        $items[] = ['href' => 'nuova-segnalazione.php', 'label' => 'Segnala guasto', 'key' => 'nuova', 'group' => 'Lavoro'];
        $items[] = ['href' => 'mie-segnalazioni.php', 'label' => 'Le mie segnalazioni', 'key' => 'miei', 'group' => 'Lavoro'];
    } else {
        $items[] = ['href' => 'segnalazioni.php', 'label' => 'Segnalazioni', 'key' => 'segnalazioni', 'group' => 'Lavoro'];
        $items[] = ['href' => 'attivita.php', 'label' => 'Attività', 'key' => 'attivita', 'group' => 'Lavoro'];
        $items[] = ['href' => 'categorie.php', 'label' => 'Categorie e macchine', 'key' => 'categorie', 'group' => 'Gestione'];
        $items[] = ['href' => 'statistiche.php', 'label' => 'Statistiche', 'key' => 'statistiche', 'group' => 'Gestione'];

        if ($role === 'admin') {
            $items[] = ['href' => 'utenti.php', 'label' => 'Utenti', 'key' => 'utenti', 'group' => 'Gestione'];
            $items[] = ['href' => 'log.php', 'label' => 'Registro', 'key' => 'log', 'group' => 'Gestione'];
        }
    }

    $items[] = ['href' => 'profilo.php', 'label' => 'Profilo', 'key' => 'profilo', 'group' => 'Account'];

    return $items;
}

/**
 * Icona SVG della voce di menu.
 */
function nav_icon(string $key): string
{
    $paths = match ($key) {
        'dashboard'    => '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>',
        'nuova'        => '<circle cx="12" cy="12" r="9"/><path d="M12 8.5v7M8.5 12h7"/>',
        'miei'         => '<path d="M9 6h11M9 12h11M9 18h11"/><circle cx="4.5" cy="6" r="1.2"/><circle cx="4.5" cy="12" r="1.2"/><circle cx="4.5" cy="18" r="1.2"/>',
        'segnalazioni' => '<path d="M10.3 3.9 1.9 18a2 2 0 0 0 1.7 3h16.8a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z"/><path d="M12 9v4M12 17h.01"/>',
        'allarme'      => '<path d="M10.3 3.9 1.9 18a2 2 0 0 0 1.7 3h16.8a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z"/><path d="M12 8.5v5M12 17.2h.01"/>',
        'check'        => '<circle cx="12" cy="12" r="9"/><path d="m8.4 12.4 2.6 2.6 4.6-5.2"/>',
        'attivita'     => '<rect x="3" y="4.5" width="18" height="16.5" rx="2"/><path d="M16 2.5v4M8 2.5v4M3 10h18"/><path d="m9 15.5 2 2 4-4"/>',
        'categorie'    => '<path d="m12 2.5 9 4.7-9 4.7-9-4.7 9-4.7Z"/><path d="m3 12 9 4.7 9-4.7"/><path d="m3 16.8 9 4.7 9-4.7"/>',
        'statistiche'  => '<path d="M3 3v18h18"/><path d="M7.5 17v-5M12 17V8M16.5 17V5.5"/>',
        'utenti'       => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.9"/><path d="M16 3.1a4 4 0 0 1 0 7.8"/>',
        'log'          => '<circle cx="12" cy="12" r="9"/><path d="M12 7.5V12l3.5 2"/>',
        'turno'        => '<circle cx="12" cy="12" r="9"/><path d="M12 7.5V12l3 2"/><path d="M6.5 4.2 4 6.7"/>',
        'profilo'      => '<circle cx="12" cy="8" r="4"/><path d="M4.5 21a7.5 7.5 0 0 1 15 0"/>',
        default        => '<circle cx="12" cy="12" r="9"/>',
    };

    return '<svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"'
        . ' stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">'
        . $paths . '</svg>';
}

/**
 * Marchio del prodotto: piastrella con chiave inglese.
 */
function brand_mark_svg(string $class = 'logo-mark'): string
{
    static $instance = 0;
    $instance++;
    $gradientId = 'sosBrand' . $instance;

    return '<svg class="' . e($class) . '" viewBox="0 0 64 64" aria-hidden="true" focusable="false">'
        . '<defs><linearGradient id="' . $gradientId . '" x1="6" y1="2" x2="58" y2="62" gradientUnits="userSpaceOnUse">'
        . '<stop stop-color="#2563eb"/><stop offset="1" stop-color="#0d9488"/></linearGradient></defs>'
        . '<rect width="64" height="64" rx="17" fill="url(#' . $gradientId . ')"/>'
        . '<g transform="translate(8 8) scale(2)" fill="none" stroke="#ffffff" stroke-width="1.15"'
        . ' stroke-linecap="round" stroke-linejoin="round">'
        . '<path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/>'
        . '</g></svg>';
}

/**
 * Logo orizzontale (marchio + nome del prodotto).
 */
function brand_lockup(string $class = ''): string
{
    return '<span class="logo-lockup' . ($class !== '' ? ' ' . e($class) : '') . '">'
        . brand_mark_svg('logo-mark')
        . '<span class="logo-word"><strong>' . e(product_name()) . '</strong>'
        . '<span>Gestione guasti</span></span></span>';
}

/**
 * Intestazione comune a tutte le pagine interne.
 */
function layout_start(string $title, string $active = ''): void
{
    $user = Auth::user();
    $role = (string) ($user['role'] ?? 'operatore');
    $name = app_name();
    $theme = current_theme();
    $fullName = person($user['full_name'] ?? '', $user['username'] ?? '');
    $brandSub = Settings::get('company_name') !== '' ? product_name() : 'Manutenzioni interne';

    $badges = ['segnalazioni' => 0, 'attivita' => 0];

    if ($role === 'admin' || $role === 'manutentore') {
        $pdo = Database::pdo();

        $badges['segnalazioni'] = (int) $pdo
            ->query("SELECT COUNT(*) FROM reports WHERE status = 'aperto'")
            ->fetchColumn();

        $statement = $pdo->prepare('SELECT COUNT(*) FROM tasks WHERE is_active = 1 AND next_due_at <= :today');
        $statement->execute(['today' => date('Y-m-d')]);
        $badges['attivita'] = (int) $statement->fetchColumn();
    }

    $groups = [];

    foreach (nav_items($role) as $item) {
        $groups[$item['group']][] = $item;
    }

    ?>
<!DOCTYPE html>
<html lang="it" data-theme="<?= e($theme) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="<?= $theme === 'scuro' ? '#0b1220' : ($theme === 'contrasto' ? '#000000' : '#101828') ?>">
    <title><?= e($title) ?> · <?= e($name) ?></title>
    <link rel="icon" href="assets/favicon.svg" type="image/svg+xml">
    <link rel="stylesheet" href="<?= e(asset_url('assets/app.css')) ?>">
</head>
<body class="app-page role-<?= e($role) ?>">
    <a class="skip-link" href="#main">Vai al contenuto</a>

    <div class="shell">
        <aside class="sidebar" id="sidebar" aria-label="Menu principale">
            <div class="sidebar-head">
                <a class="brand" href="dashboard.php">
                    <?= brand_mark_svg('brand-mark') ?>
                    <span class="brand-info">
                        <span class="brand-text"><?= e($name) ?></span>
                        <span class="brand-sub"><?= e($brandSub) ?></span>
                    </span>
                </a>

                <button type="button" class="sidebar-close" data-close-sidebar aria-label="Chiudi il menu">
                    <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                         stroke-linecap="round" aria-hidden="true" focusable="false">
                        <path d="M6 6l12 12M18 6 6 18"/>
                    </svg>
                </button>
            </div>

            <nav class="side-nav">
                <?php foreach ($groups as $group => $items): ?>
                    <div class="side-nav-group">
                        <p class="side-nav-label"><?= e($group) ?></p>
                        <ul>
                            <?php foreach ($items as $item): ?>
                                <li>
                                    <a href="<?= e($item['href']) ?>"
                                       class="side-link <?= $active === $item['key'] ? 'is-active' : '' ?>"
                                       <?= $active === $item['key'] ? 'aria-current="page"' : '' ?>>
                                        <?= nav_icon($item['key']) ?>
                                        <span class="side-link-text"><?= e($item['label']) ?></span>
                                        <?php if (($badges[$item['key']] ?? 0) > 0): ?>
                                            <span class="nav-badge"><?= e((string) $badges[$item['key']]) ?></span>
                                        <?php endif; ?>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endforeach; ?>
            </nav>

            <div class="sidebar-foot">
                <a class="side-user" href="profilo.php">
                    <span class="avatar"><?= e(initials($fullName)) ?></span>
                    <span class="side-user-text">
                        <strong><?= e($fullName) ?></strong>
                        <span><?= e(role_label($role)) ?></span>
                    </span>
                </a>

                <form method="post" action="logout.php">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-side btn-block">Esci</button>
                </form>
            </div>
        </aside>

        <div class="sidebar-overlay" data-close-sidebar></div>

        <div class="main">
            <header class="topbar">
                <button type="button" class="icon-btn" data-toggle-sidebar aria-controls="sidebar"
                        aria-expanded="false" aria-label="Apri il menu">
                    <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
                         stroke-linecap="round" aria-hidden="true" focusable="false">
                        <path d="M4 6h16M4 12h16M4 18h16"/>
                    </svg>
                </button>

                <span class="topbar-title"><?= e($title) ?></span>

                <span class="topbar-meta">
                    <span class="pill pill-role"><?= e(role_label($role)) ?></span>
                    <span class="topbar-date"><?= e(date('d/m/Y')) ?></span>
                </span>
            </header>

            <main class="content" id="main">
                <?php foreach (flash_messages() as $message): ?>
                    <div class="alert alert-<?= $message['type'] === 'error' ? 'error' : 'success' ?>"
                         role="<?= $message['type'] === 'error' ? 'alert' : 'status' ?>">
                        <?= e($message['message']) ?>
                    </div>
                <?php endforeach; ?>

                <h1 class="page-title"><?= e($title) ?></h1>
    <?php
}

/**
 * Chiusura comune delle pagine interne.
 */
function layout_end(): void
{
    ?>
            </main>

            <footer class="app-footer">
                <span><?= e(app_name()) ?></span>
                <?php if (Settings::get('company_location') !== ''): ?>
                    <span><?= e(Settings::get('company_location')) ?></span>
                <?php endif; ?>
                <span><?= e(date('d/m/Y H:i')) ?></span>
            </footer>
        </div>
    </div>

    <script>
        (function () {
            var body = document.body;
            var toggle = document.querySelector('[data-toggle-sidebar]');
            var closers = document.querySelectorAll('[data-close-sidebar]');

            function setOpen(open) {
                body.classList.toggle('sidebar-open', open);

                if (toggle) {
                    toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
                }
            }

            if (toggle) {
                toggle.addEventListener('click', function () {
                    setOpen(!body.classList.contains('sidebar-open'));
                });
            }

            Array.prototype.forEach.call(closers, function (element) {
                element.addEventListener('click', function () {
                    setOpen(false);
                });
            });

            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape') {
                    setOpen(false);
                }
            });
        })();
    </script>
</body>
</html>
    <?php
}
