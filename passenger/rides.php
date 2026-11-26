<?php
require __DIR__ . '/../includes/passenger_auth.php';
require __DIR__ . '/../includes/passenger_layout.php';
$p = require_passenger();

/* ---------- Rate a trip (also updates the driver's overall rating) ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'rate') {
    verify_csrf();
    [$ok, $msg] = rate_ride((int)$p['id'], (int)($_POST['ride_id'] ?? 0), (int)($_POST['stars'] ?? 0));
    flash($ok ? 'success' : 'error', $msg);
    redirect(($_POST['back'] ?? '') === 'home' ? 'passenger/index.php' : 'passenger/rides.php' . safe_qs($_POST['qs'] ?? ''));
}

$filters = ['all' => 'All', 'live' => 'Live', 'completed' => 'Completed', 'cancelled' => 'Cancelled', 'unrated' => 'To rate'];
$status = $_GET['status'] ?? 'all';
if (!isset($filters[$status])) $status = 'all';

if ($status === 'unrated') {
    $s = db()->prepare("SELECT COUNT(*) FROM rides WHERE passenger_id = ? AND status = 'completed' AND rating IS NULL AND driver_id IS NOT NULL");
    $s->execute([$p['id']]);
    $total = (int)$s->fetchColumn();
} else {
    $total = count_passenger_rides((int)$p['id'], $status === 'all' ? null : $status);
}
[$page, $perPage, $offset, $pages] = paginate($total, 10);

if ($status === 'unrated') {
    $s = db()->prepare("
        SELECT r.*, d.name AS driver_name, d.profile_photo AS driver_photo, d.vehicle_model, d.vehicle_color, d.plate_no, d.rating AS driver_rating, d.vehicle_type
        FROM rides r JOIN drivers d ON d.id = r.driver_id
        WHERE r.passenger_id = ? AND r.status = 'completed' AND r.rating IS NULL
        ORDER BY r.requested_at DESC LIMIT $perPage OFFSET $offset
    ");
    $s->execute([$p['id']]);
    $rides = $s->fetchAll();
} else {
    $rides = passenger_rides((int)$p['id'], $status === 'all' ? null : $status, $perPage, $offset);
}
$qs = query_with([]) === '?' ? '' : query_with([]);

passenger_header('My rides', 'rides');
?>

<div class="toolbar">
    <nav class="tabs" aria-label="Filter trips">
        <?php foreach ($filters as $key => $label): ?>
            <a href="<?= e(query_with(['status' => $key === 'all' ? null : $key, 'page' => null])) ?>" class="tab<?= $status === $key ? ' active' : '' ?>"><?= e($label) ?></a>
        <?php endforeach; ?>
    </nav>
</div>

<div class="ride-cards">
    <?php foreach ($rides as $r): ?>
        <article class="card ride-card">
            <header class="ride-card-head">
                <div>
                    <strong><?= e(fmt_datetime($r['requested_at'], 'D, d M Y · h:i A')) ?></strong>
                    <small class="muted block">Trip #<?= (int)$r['id'] ?> · <?= e(number_format($r['distance_km'], 1)) ?> km · <?= e(PAYMENT_METHODS[$r['payment_method']] ?? $r['payment_method']) ?></small>
                </div>
                <div class="ride-fare"><?= e(money($r['fare'])) ?><?= badge($r['status']) ?></div>
            </header>
            <ol class="route-line compact">
                <li><span class="dot start"></span><div><?= e($r['pickup_address']) ?></div></li>
                <li><span class="dot end"></span><div><?= e($r['dropoff_address']) ?></div></li>
            </ol>
            <footer class="ride-card-foot">
                <?= driver_chip($r) ?>
                <div class="ride-card-actions">
                    <?php if ($r['driver_name']): ?><a class="btn btn-sm btn-ghost" href="<?= e(url('passenger/messages.php?ride=' . $r['id'])) ?>"><?= icon('chat', 15) ?> Message driver</a><?php endif; ?>
                    <a class="btn btn-sm btn-ghost" href="<?= e(url('passenger/messages.php?support=1&about=' . $r['id'])) ?>">Get help</a>
                    <?php if ($r['status'] === 'completed' && $r['driver_name']): ?>
                        <?php if ($r['rating']): ?>
                            <small class="muted">You rated</small> <?= stars((int)$r['rating']) ?>
                        <?php else: ?>
                            <?= rating_form($r, 'rides', $qs) ?>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </footer>
        </article>
    <?php endforeach; ?>
    <?php if (!$rides): ?>
        <article class="card"><p class="empty"><?= $status === 'unrated' ? 'You\'ve rated all your trips — thank you!' : 'No trips here yet.' ?></p></article>
    <?php endif; ?>
</div>
<?= render_pagination($page, $pages) ?>

<?php passenger_footer(); ?>
