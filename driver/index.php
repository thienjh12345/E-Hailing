<?php
require __DIR__ . '/../includes/driver_auth.php';
require __DIR__ . '/../includes/driver_layout.php';
$d = require_driver();
$pdo = db();

/* ---------- Actions ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'toggle_online') {
        $days = license_days_left($d['license_expiry']);
        if ($d['status'] !== 'approved') {
            flash('error', 'Only approved drivers can go online.');
        } elseif (!$d['is_online'] && $days !== null && $days < 0) {
            flash('error', 'Your driving licence has expired. Update it under Licence & vehicle before going online.');
        } else {
            $pdo->prepare('UPDATE drivers SET is_online = 1 - is_online, updated_at = updated_at WHERE id = ? AND status = \'approved\'')->execute([$d['id']]);
            flash('success', $d['is_online'] ? 'You are now offline.' : 'You are online — new ride requests will come to you.');
        }
    } elseif ($action === 'cancel_request') {
        $pdo->prepare("UPDATE driver_change_requests SET status = 'cancelled', reviewed_at = NOW() WHERE driver_id = ? AND status = 'pending'")
            ->execute([$d['id']]);
        flash('success', 'Your change request was withdrawn.');
    }
    redirect('driver/index.php');
}

/* ---------- Data ---------- */
$stats   = driver_stats((int)$d['id']);
$pending = pending_change_request((int)$d['id']);
$history = array_values(array_filter(change_requests_for((int)$d['id'], 5), fn($r) => $r['status'] !== 'pending'));
$lastReviewed = $history[0] ?? null;
$daysLeft = license_days_left($d['license_expiry']);

$s = $pdo->prepare("
    SELECT r.id, r.pickup_address, r.dropoff_address, r.fare, r.status, r.requested_at, r.rating,
           p.name AS passenger_name, p.profile_photo AS passenger_photo
    FROM rides r JOIN passengers p ON p.id = r.passenger_id
    WHERE r.driver_id = ? ORDER BY r.requested_at DESC LIMIT 6
");
$s->execute([$d['id']]);
$trips = $s->fetchAll();

// Profile completeness nudges
$missing = [];
if (!$d['profile_photo'])   $missing[] = ['profile photo', 'driver/profile.php'];
if (!$d['emergency_phone']) $missing[] = ['emergency contact', 'driver/profile.php'];
if (!$d['address'])         $missing[] = ['home address', 'driver/profile.php'];
if (!$d['license_expiry'] || !$d['vehicle_color'] || !$d['vehicle_year'] || !$d['ic_no']) $missing[] = ['licence & vehicle details', 'driver/vehicle.php'];

driver_header('Home', 'home');
?>

<?= driver_status_banner($d) ?>

<?php if ($daysLeft !== null && $daysLeft <= 30): ?>
    <div class="banner <?= $daysLeft < 0 ? 'banner-bad' : 'banner-warn' ?>">
        <?= icon('id') ?>
        <div>
            <strong><?= $daysLeft < 0 ? 'Your driving licence has expired' : "Your driving licence expires in $daysLeft day" . ($daysLeft === 1 ? '' : 's') ?></strong>
            <p>Renew it and <a href="<?= e(url('driver/vehicle.php')) ?>">update your licence expiry date</a> to keep driving.</p>
        </div>
    </div>
<?php endif; ?>

<section class="hero-card">
    <div class="hero-id">
        <?= avatar($d['name'], $d['profile_photo'], 'xl') ?>
        <div>
            <p class="muted">Selamat datang,</p>
            <h2><?= e($d['name']) ?></h2>
            <p class="muted"><?= e($d['vehicle_model']) ?> · <span class="plate"><?= e($d['plate_no']) ?></span> · <?= e(VEHICLE_TYPES[$d['vehicle_type']] ?? '') ?></p>
        </div>
    </div>
    <?php if ($d['status'] === 'approved'): ?>
        <form method="post" class="online-form">
            <?= csrf_field() ?>
            <button class="online-toggle <?= $d['is_online'] ? 'on' : '' ?>" name="action" value="toggle_online" aria-pressed="<?= $d['is_online'] ? 'true' : 'false' ?>">
                <?= icon('power', 22) ?>
                <span><strong><?= $d['is_online'] ? 'You\'re online' : 'You\'re offline' ?></strong><small><?= $d['is_online'] ? 'Tap to go offline' : 'Tap to go online' ?></small></span>
            </button>
            <a class="btn btn-primary" href="<?= e(url('driver/trips.php')) ?>"><?= icon('navigate', 16) ?> Ride requests</a>
        </form>
    <?php endif; ?>
</section>

<section class="kpis">
    <article class="kpi">
        <div class="kpi-top"><span class="kpi-label">Trips this week</span><span class="kpi-icon"><?= icon('rides') ?></span></div>
        <div class="kpi-value"><?= $stats['trips_week'] ?></div>
        <a class="delta kpi-link" href="<?= e(url('driver/earnings.php')) ?>">See your earnings <?= icon('arrow', 14) ?></a>
    </article>
    <article class="kpi">
        <div class="kpi-top"><span class="kpi-label">Total trips</span><span class="kpi-icon"><?= icon('flag') ?></span></div>
        <div class="kpi-value"><?= $stats['trips'] ?></div>
        <span class="delta">completed since you joined</span>
    </article>
    <article class="kpi">
        <div class="kpi-top"><span class="kpi-label">Rating</span><span class="kpi-icon"><?= icon('star') ?></span></div>
        <div class="kpi-value"><?= $d['rating'] ? e($d['rating']) : '—' ?></div>
        <span class="delta"><?= $stats['ratings'] ?> passenger ratings</span>
    </article>
    <article class="kpi">
        <div class="kpi-top"><span class="kpi-label">Cancellation rate</span><span class="kpi-icon"><?= icon('x') ?></span></div>
        <div class="kpi-value"><?= $stats['cancel_rate'] ?>%</div>
        <span class="delta">Keep it under 10% to stay in good standing</span>
    </article>
</section>

<section class="grid-main">
    <article class="card">
        <header class="card-head"><div><h2>Recent trips</h2><p class="muted">Your latest 6 rides</p></div></header>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Trip</th><th>Passenger</th><th>Route</th><th class="num">Fare</th><th>Their rating</th><th>Status</th><th><span class="sr-only">Message</span></th></tr></thead>
                <tbody>
                <?php foreach ($trips as $t): ?>
                    <tr>
                        <td class="mono">#<?= (int)$t['id'] ?><small class="muted block ago"><?= e(time_ago($t['requested_at'])) ?></small></td>
                        <td><div class="person"><?= avatar($t['passenger_name'], $t['passenger_photo']) ?><span><?= e($t['passenger_name']) ?></span></div></td>
                        <td class="route"><span><?= e($t['pickup_address']) ?></span><span class="muted">→ <?= e($t['dropoff_address']) ?></span></td>
                        <td class="num"><?= e(money($t['fare'])) ?></td>
                        <td><?= $t['rating'] ? '<span class="rating">' . icon('star', 14) . ' ' . (int)$t['rating'] . '</span>' : '<span class="muted">—</span>' ?></td>
                        <td><?= badge($t['status']) ?></td>
                        <td><a class="icon-btn" href="<?= e(url('driver/messages.php?ride=' . $t['id'])) ?>" title="Message <?= e($t['passenger_name']) ?>" aria-label="Message <?= e($t['passenger_name']) ?>"><?= icon('chat', 16) ?></a></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$trips): ?>
                    <tr><td colspan="7" class="empty">No trips yet<?= $d['status'] === 'approved' ? ' — go online to receive ride requests.' : '. You can start driving once you\'re approved.' ?></td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </article>

    <div class="stack">
        <article class="card">
            <header class="card-head"><div><h2>Profile updates</h2><p class="muted">Changes that need Crab's approval</p></div></header>
            <?php if ($pending): ?>
                <div class="request-box">
                    <div class="request-head"><?= badge('pending') ?><small class="muted">Sent <?= e(time_ago($pending['created_at'])) ?></small></div>
                    <ul class="change-list">
                        <?php foreach ($pending['changes'] as $field => $val): ?>
                            <li><span class="muted"><?= e(driver_field_label($field)) ?></span>
                                <span><s class="muted"><?= e(driver_field_display($field, $d[$field] ?? null)) ?></s> → <strong><?= e(driver_field_display($field, $val)) ?></strong></span></li>
                        <?php endforeach; ?>
                    </ul>
                    <form method="post">
                        <?= csrf_field() ?>
                        <button class="btn btn-sm btn-ghost" name="action" value="cancel_request" data-confirm="Withdraw this change request?">Withdraw request</button>
                    </form>
                </div>
            <?php elseif ($lastReviewed): ?>
                <div class="request-box">
                    <div class="request-head"><?= badge($lastReviewed['status']) ?><small class="muted"><?= e(time_ago($lastReviewed['reviewed_at'] ?? $lastReviewed['created_at'])) ?></small></div>
                    <p class="small">Your last request (<?= e(implode(', ', array_map('driver_field_label', array_keys($lastReviewed['changes'])))) ?>)
                        was <?= e($lastReviewed['status']) ?>.</p>
                    <?php if ($lastReviewed['admin_note']): ?><p class="small"><strong>Note from Crab:</strong> <?= e($lastReviewed['admin_note']) ?></p><?php endif; ?>
                </div>
            <?php else: ?>
                <p class="empty">No pending updates.</p>
            <?php endif; ?>
        </article>

        <?php if ($missing): ?>
            <article class="card">
                <header class="card-head"><div><h2>Complete your profile</h2><p class="muted">Helps passengers and our support team</p></div></header>
                <ul class="todo">
                    <?php foreach ($missing as [$label, $href]): ?>
                        <li><a href="<?= e(url($href)) ?>"><?= icon('edit', 16) ?> Add your <?= e($label) ?> <?= icon('arrow', 14) ?></a></li>
                    <?php endforeach; ?>
                </ul>
            </article>
        <?php endif; ?>
    </div>
</section>

<?php driver_footer(); ?>
