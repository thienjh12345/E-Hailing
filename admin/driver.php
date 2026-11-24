<?php
/**
 * Admin → Driver profile: full details, stats, account status actions,
 * and review of profile change requests submitted from the driver portal.
 */
require __DIR__ . '/../includes/admin_auth.php';
require __DIR__ . '/../includes/admin_layout.php';
require __DIR__ . '/../includes/driver_profile.php';
$admin = require_admin();

$id = (int)($_GET['id'] ?? 0);

/* ---------- Change request decisions ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    if (in_array($action, ['approve_request', 'reject_request'], true)) {
        $note = mb_substr(trim((string)($_POST['note'] ?? '')), 0, 255);
        if ($action === 'reject_request' && $note === '') {
            flash('error', 'Add a short note so the driver knows why the change was rejected.');
        } else {
            [$ok, $msg] = review_change_request((int)($_POST['request_id'] ?? 0), $id, (int)$admin['id'], $action === 'approve_request', $note);
            flash($ok ? 'success' : 'error', $msg);
        }
    }
    redirect('admin/driver.php?id=' . $id);
}

$d = find_driver($id);
if (!$d) {
    http_response_code(404);
    admin_header('Driver not found', 'drivers');
    echo '<article class="card"><p class="empty">Driver #' . $id . ' does not exist. <a class="link" href="' . e(url('admin/drivers.php')) . '">Back to drivers</a></p></article>';
    admin_footer();
    exit;
}

$stats    = driver_stats($id);
$requests = change_requests_for($id, 8);
$pending  = $requests && $requests[0]['status'] === 'pending' ? $requests[0] : null;
$history  = array_values(array_filter($requests, fn($r) => $r['status'] !== 'pending'));
$daysLeft = license_days_left($d['license_expiry']);

$s = db()->prepare("
    SELECT r.id, r.pickup_address, r.dropoff_address, r.fare, r.status, r.requested_at, r.rating, p.name AS passenger
    FROM rides r JOIN passengers p ON p.id = r.passenger_id
    WHERE r.driver_id = ? ORDER BY r.requested_at DESC LIMIT 8
");
$s->execute([$id]);
$trips = $s->fetchAll();

/** Status action button that posts to drivers.php (shared status logic) and returns here. */
function status_form(array $d, string $action, string $label, string $class, bool $withNote = false, string $confirm = ''): string
{
    $h = '<form method="post" action="' . e(url('admin/drivers.php')) . '" class="status-form">' . csrf_field()
        . '<input type="hidden" name="id" value="' . (int)$d['id'] . '"><input type="hidden" name="back" value="driver">';
    if ($withNote) {
        $h .= '<input type="text" name="note" maxlength="255" placeholder="Reason shown to the driver (optional)" aria-label="Reason for ' . e($label) . '">';
    }
    return $h . '<button class="btn btn-sm ' . $class . '" name="action" value="' . e($action) . '"'
        . ($confirm ? ' data-confirm="' . e($confirm) . '"' : '') . '>' . e($label) . '</button></form>';
}

admin_header($d['name'], 'drivers');
?>

<a class="back-link" href="<?= e(url('admin/drivers.php')) ?>">← All drivers</a>

<section class="profile-head card">
    <div class="hero-id">
        <span class="avatar-wrap"><?= avatar($d['name'], $d['profile_photo'], 'xl') ?><?php if ($d['is_online'] && $d['status'] === 'approved'): ?><i class="online-dot big" title="Online"></i><?php endif; ?></span>
        <div>
            <h2><?= e($d['name']) ?> <?= badge($d['status']) ?></h2>
            <p class="muted"><?= e($d['vehicle_model']) ?> · <span class="plate"><?= e($d['plate_no']) ?></span> · <?= e(VEHICLE_TYPES[$d['vehicle_type']] ?? '') ?>
                · Joined <?= e(fmt_datetime($d['created_at'], 'd M Y')) ?><?= $d['updated_at'] ? ' · Profile updated ' . e(time_ago($d['updated_at'])) : '' ?></p>
            <?php if ($d['status_note']): ?><p class="small"><strong>Status note:</strong> <?= e($d['status_note']) ?></p><?php endif; ?>
        </div>
    </div>
    <div class="profile-actions">
        <a class="btn btn-sm btn-outline" href="<?= e(url('admin/driver_edit.php?id=' . $d['id'])) ?>"><?= icon('edit', 16) ?> Edit profile</a>
        <?php if ($d['status'] === 'pending'): ?>
            <?= status_form($d, 'approve', 'Approve', 'btn-primary') ?>
            <?= status_form($d, 'reject', 'Reject', 'btn-ghost', true, 'Reject ' . $d['name'] . '\'s application?') ?>
        <?php elseif ($d['status'] === 'approved'): ?>
            <?= status_form($d, 'suspend', 'Suspend', 'btn-ghost', true, 'Suspend ' . $d['name'] . '? They will be taken offline immediately.') ?>
        <?php elseif ($d['status'] === 'suspended'): ?>
            <?= status_form($d, 'reactivate', 'Reactivate', 'btn-outline') ?>
        <?php elseif ($d['status'] === 'rejected'): ?>
            <?= status_form($d, 'approve', 'Approve', 'btn-outline') ?>
        <?php endif; ?>
    </div>
</section>

<?php if ($pending): ?>
    <article class="card request-card">
        <header class="card-head">
            <div><h2><?= icon('inbox', 18) ?> Profile change request</h2><p class="muted">Submitted <?= e(fmt_datetime($pending['created_at'])) ?> · the current details stay live until approved</p></div>
            <?= badge('pending') ?>
        </header>
        <div class="table-wrap">
            <table class="table diff">
                <thead><tr><th>Field</th><th>Currently on file</th><th>Requested</th></tr></thead>
                <tbody>
                <?php foreach ($pending['changes'] as $field => $val): ?>
                    <tr>
                        <td><?= e(driver_field_label($field)) ?></td>
                        <td class="old"><?= e(driver_field_display($field, $d[$field] ?? null)) ?></td>
                        <td class="new"><?= e(driver_field_display($field, $val)) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <form method="post" class="decision">
            <?= csrf_field() ?>
            <input type="hidden" name="request_id" value="<?= (int)$pending['id'] ?>">
            <input type="text" name="note" maxlength="255" placeholder="Note to the driver (required when rejecting)" aria-label="Note to the driver">
            <button class="btn btn-primary" name="action" value="approve_request"><?= icon('check', 16) ?> Approve &amp; apply</button>
            <button class="btn btn-ghost" name="action" value="reject_request"><?= icon('x', 16) ?> Reject</button>
        </form>
    </article>
<?php endif; ?>

<section class="kpis">
    <article class="kpi plain">
        <div class="kpi-top"><span class="kpi-label">Completed trips</span><span class="kpi-icon"><?= icon('rides') ?></span></div>
        <div class="kpi-value"><?= $stats['trips'] ?></div>
        <span class="delta"><?= $stats['trips_week'] ?> in the last 7 days</span>
    </article>
    <article class="kpi plain">
        <div class="kpi-top"><span class="kpi-label">Total earned</span><span class="kpi-icon"><?= icon('money') ?></span></div>
        <div class="kpi-value"><?= e(money($stats['earned'])) ?></div>
        <span class="delta"><?= e(money($stats['earned_week'])) ?> this week</span>
    </article>
    <article class="kpi plain">
        <div class="kpi-top"><span class="kpi-label">Rating</span><span class="kpi-icon"><?= icon('star') ?></span></div>
        <div class="kpi-value"><?= $d['rating'] ? e($d['rating']) : '—' ?></div>
        <span class="delta"><?= $stats['ratings'] ?> ratings</span>
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
                <div><dt>IC number</dt><dd class="mono"><?= e($d['ic_no'] ?: '—') ?></dd></div>
                <div><dt>Phone</dt><dd><?= e($d['phone']) ?></dd></div>
                <div><dt>Email</dt><dd class="wrap"><?= e($d['email']) ?></dd></div>
                <div><dt>Emergency contact</dt><dd><?= e($d['emergency_name'] ?: '—') ?><?= $d['emergency_phone'] ? '<small class="muted block">' . e($d['emergency_phone']) . '</small>' : '' ?></dd></div>
                <div class="span-all"><dt>Home address</dt><dd><?= e($d['address'] ?: '—') ?></dd></div>
            </dl>
        </article>

        <article class="card">
            <header class="card-head"><h2>Licence &amp; vehicle</h2></header>
            <dl class="facts">
                <div><dt>Licence no.</dt><dd class="mono"><?= e($d['license_no']) ?></dd></div>
                <div><dt>Licence expiry</dt><dd><?= e(driver_field_display('license_expiry', $d['license_expiry'])) ?>
                    <?php if ($daysLeft !== null && $daysLeft < 0): ?><span class="badge badge-suspended">Expired</span>
                    <?php elseif ($daysLeft !== null && $daysLeft <= 30): ?><span class="badge badge-pending"><?= $daysLeft ?> days left</span><?php endif; ?></dd></div>
                <div><dt>Service type</dt><dd><?= e(VEHICLE_TYPES[$d['vehicle_type']] ?? $d['vehicle_type']) ?></dd></div>
                <div><dt>Vehicle</dt><dd><?= e($d['vehicle_model']) ?></dd></div>
                <div><dt>Plate</dt><dd><span class="plate"><?= e($d['plate_no']) ?></span></dd></div>
                <div><dt>Colour / year</dt><dd><?= e($d['vehicle_color'] ?: '—') ?> · <?= e($d['vehicle_year'] ?: '—') ?></dd></div>
            </dl>
        </article>

        <article class="card">
            <header class="card-head"><div><h2>Recent trips</h2><p class="muted">Latest 8</p></div></header>
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>Ride</th><th>Route</th><th>Passenger</th><th class="num">Fare</th><th>Status</th></tr></thead>
                    <tbody>
                    <?php foreach ($trips as $t): ?>
                        <tr class="clickable" data-href="<?= e(url('admin/ride.php?id=' . $t['id'])) ?>">
                            <td><a class="mono link" href="<?= e(url('admin/ride.php?id=' . $t['id'])) ?>">#<?= (int)$t['id'] ?></a><small class="muted block ago"><?= e(time_ago($t['requested_at'])) ?></small></td>
                            <td class="route"><span><?= e($t['pickup_address']) ?></span><span class="muted">→ <?= e($t['dropoff_address']) ?></span></td>
                            <td><?= e($t['passenger']) ?></td>
                            <td class="num"><?= e(money($t['fare'])) ?></td>
                            <td><?= badge($t['status']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$trips): ?><tr><td colspan="5" class="empty">No trips yet.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </article>
    </div>

    <article class="card">
        <header class="card-head"><div><h2>Change history</h2><p class="muted">Requests from the driver portal</p></div></header>
        <?php if ($history): ?>
            <ol class="timeline">
                <?php foreach ($history as $r): ?>
                    <li>
                        <div class="request-head"><?= badge($r['status']) ?><small class="muted"><?= e(fmt_datetime($r['reviewed_at'] ?? $r['created_at'], 'd M Y, h:i A')) ?></small></div>
                        <p class="small"><?= e(implode(', ', array_map('driver_field_label', array_keys($r['changes'])))) ?></p>
                        <?php if ($r['admin_name']): ?><small class="muted">by <?= e($r['admin_name']) ?></small><?php endif; ?>
                        <?php if ($r['admin_note']): ?><p class="small quote"><?= e($r['admin_note']) ?></p><?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ol>
        <?php else: ?>
            <p class="empty">No past change requests.</p>
        <?php endif; ?>
    </article>
</section>

<?php admin_footer(); ?>
