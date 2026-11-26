<?php
/**
 * Passenger portal page shell (same look as the admin and driver portals).
 *
 *   passenger_header('My rides', 'rides');
 *   ... content ...
 *   passenger_footer();
 */

require_once __DIR__ . '/ui.php';

function passenger_header(string $title, string $active = ''): void
{
    $p = current_passenger();
    $unrated = (int)passenger_stats((int)$p['id'])['unrated'];
    require_once __DIR__ . '/chat.php';
    $unreadChat = chat_unread_total(['role' => 'passenger', 'id' => (int)$p['id']]);
    $nav = [
        'home'     => ['Home',         'passenger/index.php',    'home'],
        'book'     => ['Book a ride',  'passenger/book.php',     'navigate'],
        'rides'    => ['My rides',     'passenger/rides.php',    'rides'],
        'messages' => ['Messages',     'passenger/messages.php', 'chat'],
        'wallet'   => ['Wallet',       'passenger/wallet.php',   'wallet'],
        'places'   => ['Saved places', 'passenger/places.php',   'pin'],
        'settings' => ['Settings',     'passenger/profile.php',  'settings'],
    ];
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title) ?> · <?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="<?= e(asset('assets/css/admin.css')) ?>">
    <?= theme_head() ?>
</head>
<body>
<div class="app">
    <aside class="sidebar" id="sidebar">
        <a class="brand" href="<?= e(url('passenger/index.php')) ?>">
            <?= crab_logo() ?>
            <span class="brand-name"><?= e(strtolower(APP_NAME)) ?></span>
        </a>

        <nav class="nav" aria-label="Main">
            <?php foreach ($nav as $key => [$label, $href, $ic]): ?>
                <a href="<?= e(url($href)) ?>" class="nav-link<?= $key === $active ? ' active' : '' ?>"<?= $key === $active ? ' aria-current="page"' : '' ?>>
                    <?= icon($ic) ?>
                    <span><?= e($label) ?></span>
                    <?php if ($key === 'messages' && $unreadChat > 0): ?>
                        <span class="nav-count" title="Unread messages"><?= $unreadChat ?></span>
                    <?php endif; ?>
                    <?php if ($key === 'rides' && $unrated > 0): ?>
                        <span class="nav-count soft" title="Trips waiting for your rating"><?= $unrated ?></span>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        </nav>

        <div class="sidebar-foot">
            <?= sidebar_me('passenger', $p['name'], $p['profile_photo'], $p['email'], $active === 'settings') ?>
            <form method="post" action="<?= e(url('passenger/logout.php')) ?>">
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
            <div class="topbar-right"><?= theme_toggle() ?><?= $p['status'] === 'suspended' ? badge('suspended') : '' ?></div>
        </header>

        <main class="content">
            <?php if ($p['status'] === 'suspended'): ?>
                <div class="banner banner-bad"><?= icon('alert') ?>
                    <div><strong>Your account is suspended</strong>
                        <p>You can't book rides right now, but you can still view your trips and update your profile. Please contact Crab support.</p>
                        <?php if ($p['status_note']): ?><p class="banner-note"><strong>Reason from Crab:</strong> <?= e($p['status_note']) ?></p><?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
            <?php foreach (take_flashes() as $f): ?>
                <div class="flash flash-<?= e($f['type']) ?>" role="status"><?= e($f['message']) ?></div>
            <?php endforeach; ?>
    <?php
}

function passenger_footer(): void
{
    ?>
        </main>
        <footer class="footer">&copy; <?= date('Y') ?> <?= e(APP_NAME) ?> E-Hailing</footer>
    </div>
</div>
<script src="<?= e(asset('assets/js/admin.js')) ?>"></script>
</body>
</html>
    <?php
}

/** Compact "your driver" block: photo, name, vehicle, plate, rating. Comes from the driver profile module. */
function driver_chip(array $ride): string
{
    if (!$ride['driver_name']) return '<span class="muted">No driver assigned</span>';
    return '<div class="person">' . avatar($ride['driver_name'], $ride['driver_photo'])
        . '<div><strong>' . e($ride['driver_name']) . '</strong>'
        . ($ride['driver_rating'] ? ' <span class="rating small-rating">' . icon('star', 12) . ' ' . e($ride['driver_rating']) . '</span>' : '')
        . '<small class="muted block">' . e(trim(($ride['vehicle_color'] ?? '') . ' ' . $ride['vehicle_model'])) . ' · <span class="plate">' . e($ride['plate_no']) . '</span></small></div></div>';
}

/** Five-star rating form for an unrated completed ride. Works without JS (radio buttons + submit). */
function rating_form(array $ride, string $back, string $qs = ''): string
{
    $h = '<form method="post" action="' . e(url('passenger/rides.php')) . '" class="rate-form">' . csrf_field()
        . '<input type="hidden" name="action" value="rate"><input type="hidden" name="ride_id" value="' . (int)$ride['id'] . '">'
        . '<input type="hidden" name="back" value="' . e($back) . '"><input type="hidden" name="qs" value="' . e($qs) . '">'
        . '<fieldset class="star-input"><legend class="sr-only">Rate trip #' . (int)$ride['id'] . '</legend>';
    for ($i = 5; $i >= 1; $i--) {
        $id = 'r' . (int)$ride['id'] . 's' . $i;
        $h .= '<input type="radio" name="stars" value="' . $i . '" id="' . $id . '" required><label for="' . $id . '" title="' . $i . ' star' . ($i > 1 ? 's' : '') . '">★</label>';
    }
    return $h . '</fieldset><button class="btn btn-sm btn-outline">Rate</button></form>';
}
