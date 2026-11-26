<?php
/**
 * Driver portal page shell (same look as the admin console, driver navigation).
 *
 *   driver_header('My profile', 'profile');
 *   ... content ...
 *   driver_footer();
 */

require_once __DIR__ . '/ui.php';

function driver_header(string $title, string $active = ''): void
{
    $d = current_driver();
    $nav = [
        'home'     => ['Home',               'driver/index.php',    'home'],
        'messages' => ['Messages',           'driver/messages.php', 'chat'],
        'earnings' => ['Earnings',           'driver/earnings.php', 'money'],
        'vehicle'  => ['Licence & vehicle',  'driver/vehicle.php',  'rides'],
        'settings' => ['Settings',           'driver/profile.php',  'settings'],
    ];
    $hasRequest = $d && pending_change_request((int)$d['id']);
    require_once __DIR__ . '/chat.php';
    $unreadChat = chat_unread_total(['role' => 'driver', 'id' => (int)$d['id']]);
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title) ?> · <?= e(APP_NAME) ?> Driver</title>
    <link rel="stylesheet" href="<?= e(asset('assets/css/admin.css')) ?>">
    <?= theme_head() ?>
</head>
<body>
<div class="app">
    <aside class="sidebar" id="sidebar">
        <a class="brand" href="<?= e(url('driver/index.php')) ?>">
            <?= crab_logo() ?>
            <span class="brand-name"><?= e(strtolower(APP_NAME)) ?></span>
            <span class="brand-tag">Driver</span>
        </a>

        <nav class="nav" aria-label="Main">
            <?php foreach ($nav as $key => [$label, $href, $ic]): ?>
                <a href="<?= e(url($href)) ?>" class="nav-link<?= $key === $active ? ' active' : '' ?>"<?= $key === $active ? ' aria-current="page"' : '' ?>>
                    <?= icon($ic) ?>
                    <span><?= e($label) ?></span>
                    <?php if ($key === 'messages' && $unreadChat > 0): ?>
                        <span class="nav-count" title="Unread messages"><?= $unreadChat ?></span>
                    <?php endif; ?>
                    <?php if ($key === 'vehicle' && $hasRequest): ?>
                        <span class="nav-count soft" title="Change request waiting for admin review">1</span>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        </nav>

        <div class="sidebar-foot">
            <?= sidebar_me('driver', $d['name'], $d['profile_photo'], $d['plate_no'] . ' · ' . (VEHICLE_TYPES[$d['vehicle_type']] ?? ''), $active === 'settings') ?>
            <form method="post" action="<?= e(url('driver/logout.php')) ?>">
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
            <div class="topbar-right"><?= theme_toggle() ?>
                <?php if ($d['status'] === 'approved'): ?>
                    <span class="presence <?= $d['is_online'] ? 'on' : '' ?>"><?= $d['is_online'] ? 'Online' : 'Offline' ?></span>
                <?php else: ?>
                    <?= badge($d['status']) ?>
                <?php endif; ?>
            </div>
        </header>

        <main class="content">
            <?php foreach (take_flashes() as $f): ?>
                <div class="flash flash-<?= e($f['type']) ?>" role="status"><?= e($f['message']) ?></div>
            <?php endforeach; ?>
    <?php
}

function driver_footer(): void
{
    ?>
        </main>
        <footer class="footer">&copy; <?= date('Y') ?> <?= e(APP_NAME) ?> E-Hailing · Driver Portal</footer>
    </div>
</div>
<script src="<?= e(asset('assets/js/admin.js')) ?>"></script>
</body>
</html>
    <?php
}

/** Status banner explaining what the driver's account status means for them. */
function driver_status_banner(array $d): string
{
    $note = $d['status_note'] ? '<p class="banner-note"><strong>Reason from Crab:</strong> ' . e($d['status_note']) . '</p>' : '';
    switch ($d['status']) {
        case 'pending':
            return '<div class="banner banner-warn">' . icon('clock') . '<div><strong>Your application is under review</strong>'
                . '<p>Our team is checking your licence and vehicle details. This usually takes 1–2 working days. You can still update your details while you wait.</p></div></div>';
        case 'rejected':
            return '<div class="banner banner-bad">' . icon('alert') . '<div><strong>Your application was not approved</strong>'
                . '<p>Update your <a href="' . e(url('driver/vehicle.php')) . '">licence &amp; vehicle</a> or <a href="' . e(url('driver/profile.php')) . '">profile</a> details — saving them re-submits your application automatically.</p>' . $note . '</div></div>';
        case 'suspended':
            return '<div class="banner banner-bad">' . icon('alert') . '<div><strong>Your account is suspended</strong>'
                . '<p>You can\'t go online or take rides right now. Please contact Crab driver support.</p>' . $note . '</div></div>';
    }
    return '';
}
