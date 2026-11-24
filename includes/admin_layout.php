<?php
/**
 * Admin page shell: <head>, sidebar, top bar, and footer.
 *
 * Usage in a page:
 *   admin_header('Drivers', 'drivers');   // title, active nav key
 *   ... page content ...
 *   admin_footer();
 */

require_once __DIR__ . "/ui.php";

function admin_header(string $title, string $active = ''): void
{
    $admin = current_admin();

    // Sidebar badge: driver applications + profile change requests waiting for an admin.
    $pending = (int)db()->query("SELECT (SELECT COUNT(*) FROM drivers WHERE status = 'pending') + (SELECT COUNT(*) FROM driver_change_requests WHERE status = 'pending')")->fetchColumn();

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
                        <span class="nav-count" title="Driver applications and profile changes awaiting review"><?= $pending ?></span>
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
