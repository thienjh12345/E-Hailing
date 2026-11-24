<?php
/**
 * Admin → Passenger profile: details, stats, saved places, account status, recent trips
 * (with the driver for each trip, linked to the driver profile).
 */
require __DIR__ . '/../includes/admin_auth.php';
require __DIR__ . '/../includes/admin_layout.php';
require __DIR__ . '/../includes/passenger_profile.php';
require_admin();

$id = (int)($_GET['id'] ?? 0);
$p = find_passenger($id);
if (!$p) {
    http_response_code(404);
    admin_header('Passenger not found', 'passengers');
    echo '<article class="card"><p class="empty">Passenger #' . $id . ' does not exist. <a class="link" href="' . e(url('admin/passengers.php')) . '">Back to passengers</a></p></article>';
    admin_footer();
    exit;
}

$stats  = passenger_stats($id);
$places = saved_places($id);
$trips  = passenger_rides($id, null, 8);
$topPay = passenger_top_payment($id);
$age    = passenger_age($p['date_of_birth']);

admin_header($p['name'], 'passengers');
?>

<a class="back-link" href="<?= e(url('admin/passengers.php')) ?>">← All passengers</a>

<section class="profile-head card">
    <div class="hero-id">
        <?= avatar($p['name'], $p['profile_photo'], 'xl') ?>
        <div>
            <h2><?= e($p['name']) ?> <?= badge($p['status']) ?></h2>
            <p class="muted">Passenger #<?= (int)$p['id'] ?> · Joined <?= e(fmt_datetime($p['created_at'], 'd M Y')) ?>
                · Last sign-in <?= e($p['last_login_at'] ? time_ago($p['last_login_at']) : 'never') ?>
                <?= $p['updated_at'] ? ' · Profile updated ' . e(time_ago($p['updated_at'])) : '' ?></p>
            <?php if ($p['status_note']): ?><p class="small"><strong>Suspension reason:</strong> <?= e($p['status_note']) ?></p><?php endif; ?>
        </div>
    </div>
    <div class="profile-actions">
        <a class="btn btn-sm btn-outline" href="<?= e(url('admin/passenger_edit.php?id=' . $p['id'])) ?>"><?= icon('edit', 16) ?> Edit profile</a>
        <a class="btn btn-sm btn-ghost" href="<?= e(url('admin/rides.php?passenger=' . $p['id'])) ?>">All rides</a>
        <form method="post" action="<?= e(url('admin/passengers.php')) ?>" class="status-form">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
            <input type="hidden" name="back" value="passenger">
            <?php if ($p['status'] === 'active'): ?>
                <input type="text" name="note" maxlength="255" placeholder="Reason (optional)" title="Shown to the passenger in their portal" aria-label="Reason for suspension">
                <button class="btn btn-sm btn-ghost" name="action" value="suspend" data-confirm="Suspend <?= e($p['name']) ?>? They won't be able to book rides.">Suspend</button>
            <?php else: ?>
                <button class="btn btn-sm btn-outline" name="action" value="activate">Reactivate</button>
            <?php endif; ?>
        </form>
    </div>
</section>

<section class="kpis">
    <article class="kpi plain">
        <div class="kpi-top"><span class="kpi-label">Completed trips</span><span class="kpi-icon"><?= icon('rides') ?></span></div>
        <div class="kpi-value"><?= $stats['completed'] ?></div>
        <span class="delta"><?= e(number_format($stats['km'], 1)) ?> km travelled</span>
    </article>
    <article class="kpi plain">
        <div class="kpi-top"><span class="kpi-label">Total spent</span><span class="kpi-icon"><?= icon('money') ?></span></div>
        <div class="kpi-value"><?= e(money($stats['spent'])) ?></div>
        <span class="delta"><?= e(money($stats['spent_month'])) ?> this month</span>
    </article>
    <article class="kpi plain">
        <div class="kpi-top"><span class="kpi-label">Avg rating given</span><span class="kpi-icon"><?= icon('star') ?></span></div>
        <div class="kpi-value"><?= $stats['avg_given'] !== null ? e($stats['avg_given']) : '—' ?></div>
        <span class="delta"><?= $stats['unrated'] ?> trip<?= $stats['unrated'] === 1 ? '' : 's' ?> not rated yet</span>
    </article>
    <article class="kpi plain">
        <div class="kpi-top"><span class="kpi-label">Cancellation rate</span><span class="kpi-icon"><?= icon('x') ?></span></div>
        <div class="kpi-value"><?= $stats['cancel_rate'] ?>%</div>
        <span class="delta">of finished trips</span>
    </article>
</section>

<section class="grid-main">
    <div class="stack">
        <article class="card">
            <header class="card-head"><h2>Personal details</h2></header>
            <dl class="facts">
                <div><dt>Phone</dt><dd><?= e($p['phone']) ?></dd></div>
                <div><dt>Email</dt><dd class="wrap"><?= e($p['email']) ?></dd></div>
                <div><dt>Date of birth</dt><dd><?= $p['date_of_birth'] ? e(fmt_datetime($p['date_of_birth'], 'd M Y')) . ' <span class="muted">(' . $age . ')</span>' : '—' ?></dd></div>
                <div><dt>Gender</dt><dd><?= e(GENDERS[$p['gender'] ?? ''] ?? '—') ?></dd></div>
                <div><dt>Preferred payment</dt><dd><?= e(PAYMENT_METHODS[$p['preferred_payment']] ?? '—') ?><?= $topPay && $topPay !== $p['preferred_payment'] ? '<small class="muted block">Mostly uses ' . e(PAYMENT_METHODS[$topPay]) . '</small>' : '' ?></dd></div>
                <div><dt>Emergency contact</dt><dd><?= e($p['emergency_name'] ?: '—') ?><?= $p['emergency_phone'] ? '<small class="muted block">' . e($p['emergency_phone']) . '</small>' : '' ?></dd></div>
            </dl>
        </article>

        <article class="card">
            <header class="card-head"><div><h2>Recent trips</h2><p class="muted">Latest 8 · click a driver to open their profile</p></div></header>
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>Ride</th><th>Route</th><th>Driver</th><th class="num">Fare</th><th>Rating</th><th>Status</th></tr></thead>
                    <tbody>
                    <?php foreach ($trips as $t): ?>
                        <tr>
                            <td><a class="mono link" href="<?= e(url('admin/ride.php?id=' . $t['id'])) ?>">#<?= (int)$t['id'] ?></a><small class="muted block ago"><?= e(time_ago($t['requested_at'])) ?></small></td>
                            <td class="route"><span><?= e($t['pickup_address']) ?></span><span class="muted">→ <?= e($t['dropoff_address']) ?></span></td>
                            <td><?= $t['driver_name'] ? '<a class="strong-link" href="' . e(url('admin/driver.php?id=' . $t['driver_id'])) . '">' . e($t['driver_name']) . '</a><small class="muted block">' . e($t['plate_no']) . '</small>' : '<span class="muted">Unassigned</span>' ?></td>
                            <td class="num"><?= e(money($t['fare'])) ?></td>
                            <td><?= stars($t['rating'] ? (int)$t['rating'] : null) ?></td>
                            <td><?= badge($t['status']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$trips): ?><tr><td colspan="6" class="empty">No trips yet.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </article>
    </div>

    <article class="card">
        <header class="card-head"><div><h2>Saved places</h2><p class="muted"><?= count($places) ?> saved</p></div></header>
        <?php if ($places): ?>
            <ul class="place-list compact">
                <?php foreach ($places as $pl): ?>
                    <li><span class="place-icon"><?= icon($pl['label'] === 'Home' ? 'home' : ($pl['label'] === 'Work' ? 'flag' : 'pin'), 18) ?></span>
                        <div><strong><?= e($pl['label']) ?></strong><small class="muted block"><?= e($pl['address']) ?></small>
                            <?php if ($pl['note']): ?><small class="muted block"><em><?= e($pl['note']) ?></em></small><?php endif; ?></div></li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p class="empty">No saved places.</p>
        <?php endif; ?>
    </article>
</section>

<?php admin_footer(); ?>
