<?php
/**
 * Admin page shell: <head>, sidebar, top bar, and footer.
 *
 * Usage in a page:
 *   admin_header('Drivers', 'drivers');   // title, active nav key
 *   ... page content ...
 *   admin_footer();
 */

/** Inline SVG icons (stroke style) so the admin works fully offline. */
function icon(string $name, int $size = 20): string
{
    $paths = [
        'dashboard' => '<rect x="3" y="3" width="7" height="9" rx="1.5"/><rect x="14" y="3" width="7" height="5" rx="1.5"/><rect x="14" y="12" width="7" height="9" rx="1.5"/><rect x="3" y="16" width="7" height="5" rx="1.5"/>',
        'rides'     => '<path d="M5 17h14M6 17l1.5-6.5A2 2 0 0 1 9.45 9h5.1a2 2 0 0 1 1.95 1.5L18 17"/><path d="M4 17v2a1 1 0 0 0 1 1h1a1 1 0 0 0 1-1v-2M17 17v2a1 1 0 0 0 1 1h1a1 1 0 0 0 1-1v-2"/><circle cx="7.5" cy="14" r=".6"/><circle cx="16.5" cy="14" r=".6"/>',
        'driver'    => '<circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="2.5"/><path d="M12 14.5V21M9.6 11.2 3.4 9.6M14.4 11.2l6.2-1.6"/>',
        'users'     => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>',
        'money'     => '<rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="2.5"/><path d="M6 12h.01M18 12h.01"/>',
        'clock'     => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'logout'    => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/>',
        'search'    => '<circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/>',
        'check'     => '<path d="M20 6 9 17l-5-5"/>',
        'x'         => '<path d="M18 6 6 18M6 6l12 12"/>',
        'menu'      => '<path d="M3 6h18M3 12h18M3 18h18"/>',
        'star'      => '<path d="m12 3 2.7 5.6 6.1.9-4.4 4.3 1 6.1L12 17l-5.4 2.9 1-6.1-4.4-4.3 6.1-.9z"/>',
        'pin'       => '<path d="M12 21s-7-6.2-7-11.5a7 7 0 0 1 14 0C19 14.8 12 21 12 21z"/><circle cx="12" cy="9.5" r="2.5"/>',
        'flag'      => '<path d="M5 21V4M5 4h11l-2 4 2 4H5"/>',
        'arrow'     => '<path d="M5 12h14M13 6l6 6-6 6"/>',
        'alert'     => '<path d="M12 9v4M12 17h.01"/><path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/>',
    ];
    $p = $paths[$name] ?? '';
    return '<svg class="icon" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $p . '</svg>';
}

/** Brand mark: a stylised red crab claw. */
function crab_logo(int $size = 32): string
{
    return '<svg width="' . $size . '" height="' . $size . '" viewBox="0 0 32 32" aria-hidden="true">'
        . '<rect width="32" height="32" rx="9" fill="var(--brand)"/>'
        . '<path d="M9 21c0-5 3-9 8-9.5L15 9h5a5 5 0 0 1 3 9l-3-2.2a2 2 0 0 0-.6-3.6L18 12.5c-3 .7-5.6 3.6-5.6 8.5z" fill="#fff"/>'
        . '<circle cx="11" cy="23.5" r="1.6" fill="#fff"/><circle cx="16" cy="23.5" r="1.6" fill="#fff"/><circle cx="21" cy="23.5" r="1.6" fill="#fff"/>'
        . '</svg>';
}

function admin_header(string $title, string $active = ''): void
{
    $admin = current_admin();

    // Badge count for drivers waiting for approval (shown in the sidebar).
    $pending = (int)db()->query("SELECT COUNT(*) FROM drivers WHERE status = 'pending'")->fetchColumn();

    $nav = [
        'dashboard'  => ['Dashboard',  'admin/index.php',      'dashboard'],
        'rides'      => ['Rides',      'admin/rides.php',      'rides'],
        'drivers'    => ['Drivers',    'admin/drivers.php',    'driver'],
        'passengers' => ['Passengers', 'admin/passengers.php', 'users'],
    ];
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title) ?> · <?= e(APP_NAME) ?> Admin</title>
    <link rel="stylesheet" href="<?= e(url('assets/css/admin.css')) ?>">
</head>
<body>
<div class="app">
    <aside class="sidebar" id="sidebar">
        <a class="brand" href="<?= e(url('admin/index.php')) ?>">
            <?= crab_logo() ?>
            <span class="brand-name"><?= e(strtolower(APP_NAME)) ?></span>
            <span class="brand-tag">Admin</span>
        </a>

        <nav class="nav" aria-label="Main">
            <?php foreach ($nav as $key => [$label, $href, $ic]): ?>
                <a href="<?= e(url($href)) ?>" class="nav-link<?= $key === $active ? ' active' : '' ?>"<?= $key === $active ? ' aria-current="page"' : '' ?>>
                    <?= icon($ic) ?>
                    <span><?= e($label) ?></span>
                    <?php if ($key === 'drivers' && $pending > 0): ?>
                        <span class="nav-count" title="Drivers awaiting approval"><?= $pending ?></span>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        </nav>

        <div class="sidebar-foot">
            <div class="me">
                <span class="avatar"><?= e(initials($admin['name'])) ?></span>
                <div class="me-text">
                    <strong><?= e($admin['name']) ?></strong>
                    <small><?= e($admin['email']) ?></small>
                </div>
            </div>
            <form method="post" action="<?= e(url('admin/logout.php')) ?>">
                <?= csrf_field() ?>
                <button type="submit" class="nav-link logout"><?= icon('logout') ?><span>Log out</span></button>
            </form>
        </div>
    </aside>
    <div class="scrim" id="scrim"></div>

    <div class="main">
        <header class="topbar">
            <button class="icon-btn menu-btn" id="menuBtn" aria-label="Open menu" aria-controls="sidebar"><?= icon('menu') ?></button>
            <h1 class="page-title"><?= e($title) ?></h1>
            <div class="topbar-right">
                <span class="today"><?= e(date('l, j F Y')) ?></span>
            </div>
        </header>

        <main class="content">
            <?php foreach (take_flashes() as $f): ?>
                <div class="flash flash-<?= e($f['type']) ?>" role="status"><?= e($f['message']) ?></div>
            <?php endforeach; ?>
    <?php
}

function admin_footer(): void
{
    ?>
        </main>
        <footer class="footer">&copy; <?= date('Y') ?> <?= e(APP_NAME) ?> E-Hailing · Admin Console</footer>
    </div>
</div>
<script src="<?= e(url('assets/js/admin.js')) ?>"></script>
</body>
</html>
    <?php
}
