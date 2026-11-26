<?php
require __DIR__ . '/../includes/passenger_auth.php';
require __DIR__ . '/../includes/passenger_layout.php';
$p = require_passenger();

$stats  = passenger_stats((int)$p['id']);
$places = saved_places((int)$p['id']);
$live   = passenger_rides((int)$p['id'], 'live', 1);
$recent = passenger_rides((int)$p['id'], null, 5);

// Completed trips still waiting for a rating (newest first)
$s = db()->prepare("
    SELECT r.*, d.name AS driver_name, d.profile_photo AS driver_photo, d.vehicle_model, d.vehicle_color, d.plate_no, d.rating AS driver_rating, d.vehicle_type
    FROM rides r JOIN drivers d ON d.id = r.driver_id
    WHERE r.passenger_id = ? AND r.status = 'completed' AND r.rating IS NULL
    ORDER BY r.completed_at DESC LIMIT 2
");
$s->execute([$p['id']]);
$toRate = $s->fetchAll();

$missing = [];
if (!$p['profile_photo'])   $missing[] = ['profile photo', 'passenger/profile.php'];
if (!$p['emergency_phone']) $missing[] = ['emergency contact', 'passenger/profile.php'];
if (!$places)               $missing[] = ['Home or Work address', 'passenger/places.php'];

$liveLabels = ['requested' => 'Finding you a driver…', 'accepted' => 'Your driver is on the way', 'ongoing' => 'On your way'];

passenger_header('Home', 'home');
?>

<section class="hero-card">
    <div class="hero-id">
        <?= avatar($p['name'], $p['profile_photo'], 'xl') ?>
        <div>
            <p class="muted">Hi there,</p>
            <h2><?= e($p['name']) ?></h2>
            <p class="muted">Riding with Crab since <?= e(date('F Y', strtotime($p['created_at']))) ?> · pays by <?= e(PAYMENT_METHODS[$p['preferred_payment']] ?? '') ?></p>
        </div>
    </div>
    <a class="btn btn-primary book-cta" href="<?= e(url('passenger/book.php')) ?>"><?= icon('navigate', 18) ?> <?= $live ? 'View your trip' : 'Book a ride' ?></a>
</section>

<?php if ($live): $r = $live[0]; ?>
    <article class="card live-card">
        <header class="card-head">
            <div><h2><?= e($liveLabels[$r['status']] ?? 'Current trip') ?></h2><p class="muted"><?= e($r['pickup_address']) ?> → <?= e($r['dropoff_address']) ?></p></div>
            <?= badge($r['status']) ?>
        </header>
        <div class="live-foot">
            <?= driver_chip($r) ?>
            <?php if ($r['driver_name']): ?><a class="btn btn-primary btn-sm" href="<?= e(url('passenger/messages.php?ride=' . $r['id'])) ?>"><?= icon('chat', 15) ?> Message driver</a><?php endif; ?>
        </div>
    </article>
<?php endif; ?>

<section class="kpis">
    <article class="kpi">
        <div class="kpi-top"><span class="kpi-label">Spent this month</span><span class="kpi-icon"><?= icon('money') ?></span></div>
        <div class="kpi-value"><?= e(money($stats['spent_month'])) ?></div>
        <span class="delta"><?= $stats['rides_month'] ?> trip<?= $stats['rides_month'] === 1 ? '' : 's' ?> in <?= e(date('F')) ?></span>
    </article>
    <article class="kpi">
        <div class="kpi-top"><span class="kpi-label">Total trips</span><span class="kpi-icon"><?= icon('rides') ?></span></div>
        <div class="kpi-value"><?= $stats['completed'] ?></div>
        <span class="delta"><?= e(number_format($stats['km'], 1)) ?> km travelled</span>
    </article>
    <article class="kpi">
        <div class="kpi-top"><span class="kpi-label">Total spent</span><span class="kpi-icon"><?= icon('money') ?></span></div>
        <div class="kpi-value"><?= e(money($stats['spent'])) ?></div>
        <span class="delta">across all completed trips</span>
    </article>
    <article class="kpi">
        <div class="kpi-top"><span class="kpi-label">Trips to rate</span><span class="kpi-icon"><?= icon('star') ?></span></div>
        <div class="kpi-value"><?= $stats['unrated'] ?></div>
        <span class="delta">Ratings help keep Crab drivers great</span>
    </article>
</section>

<section class="grid-main">
    <div class="stack">
        <?php if ($toRate): ?>
            <article class="card">
                <header class="card-head"><div><h2>How was your trip?</h2><p class="muted">Your rating goes straight to the driver's profile</p></div></header>
                <ul class="rate-list">
                    <?php foreach ($toRate as $r): ?>
                        <li>
                            <?= driver_chip($r) ?>
                            <div class="rate-meta"><small class="muted"><?= e(fmt_datetime($r['completed_at'], 'd M, h:i A')) ?> · <?= e($r['dropoff_address']) ?></small><?= rating_form($r, 'home') ?></div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </article>
        <?php endif; ?>

        <article class="card">
            <header class="card-head">
                <div><h2>Recent trips</h2><p class="muted">Your latest 5 rides</p></div>
                <a class="link" href="<?= e(url('passenger/rides.php')) ?>">All trips <?= icon('arrow', 16) ?></a>
            </header>
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>When</th><th>Route</th><th>Driver</th><th class="num">Fare</th><th>Status</th></tr></thead>
                    <tbody>
                    <?php foreach ($recent as $r): ?>
                        <tr>
                            <td class="ago"><?= e(time_ago($r['requested_at'])) ?></td>
                            <td class="route"><span><?= e($r['pickup_address']) ?></span><span class="muted">→ <?= e($r['dropoff_address']) ?></span></td>
                            <td><?= $r['driver_name'] ? e($r['driver_name']) . '<small class="muted block">' . e($r['plate_no']) . '</small>' : '<span class="muted">—</span>' ?></td>
                            <td class="num"><?= e(money($r['fare'])) ?></td>
                            <td><?= badge($r['status']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$recent): ?><tr><td colspan="5" class="empty">No trips yet — your rides will show up here.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </article>
    </div>

    <div class="stack">
        <article class="card">
            <header class="card-head">
                <div><h2>Saved places</h2><p class="muted">For quicker bookings</p></div>
                <a class="link" href="<?= e(url('passenger/places.php')) ?>">Manage <?= icon('arrow', 16) ?></a>
            </header>
            <?php if ($places): ?>
                <ul class="place-list compact">
                    <?php foreach ($places as $pl): ?>
                        <li><span class="place-icon"><?= icon($pl['label'] === 'Home' ? 'home' : ($pl['label'] === 'Work' ? 'flag' : 'pin'), 18) ?></span>
                            <div><strong><?= e($pl['label']) ?></strong><small class="muted block"><?= e($pl['address']) ?></small></div></li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p class="empty">No saved places yet. <a class="link" href="<?= e(url('passenger/places.php')) ?>">Add Home or Work</a></p>
            <?php endif; ?>
        </article>

        <?php if ($missing): ?>
            <article class="card">
                <header class="card-head"><div><h2>Complete your profile</h2><p class="muted">Helps your drivers and our support team</p></div></header>
                <ul class="todo">
                    <?php foreach ($missing as [$label, $href]): ?>
                        <li><a href="<?= e(url($href)) ?>"><?= icon('edit', 16) ?> Add your <?= e($label) ?> <?= icon('arrow', 14) ?></a></li>
                    <?php endforeach; ?>
                </ul>
            </article>
        <?php endif; ?>
    </div>
</section>

<?php passenger_footer(); ?>
