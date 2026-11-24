<?php
require __DIR__ . '/../includes/admin_auth.php';
require __DIR__ . '/../includes/admin_layout.php';
require_admin();

$pdo = db();
$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);

/* Cancel a live ride (e.g. passenger called support). */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'cancel') {
    verify_csrf();
    $upd = $pdo->prepare("UPDATE rides SET status = 'cancelled' WHERE id = ? AND status IN ('requested','accepted','ongoing')");
    $upd->execute([$id]);
    $upd->rowCount()
        ? flash('success', "Ride #$id has been cancelled.")
        : flash('error', "Ride #$id can't be cancelled — it is no longer live.");
    redirect('admin/ride.php?id=' . $id);
}

$stmt = $pdo->prepare("
    SELECT r.*,
           p.name AS p_name, p.phone AS p_phone, p.email AS p_email, p.profile_photo AS p_photo,
           d.name AS d_name, d.phone AS d_phone, d.vehicle_model, d.plate_no, d.rating AS d_rating, d.profile_photo AS d_photo
    FROM rides r
    JOIN passengers p ON p.id = r.passenger_id
    LEFT JOIN drivers d ON d.id = r.driver_id
    WHERE r.id = ?
");
$stmt->execute([$id]);
$ride = $stmt->fetch();

if (!$ride) {
    http_response_code(404);
    admin_header('Ride not found', 'rides');
    echo '<article class="card"><p class="empty">Ride #' . (int)$id . ' does not exist. <a class="link" href="' . e(url('admin/rides.php')) . '">Back to rides</a></p></article>';
    admin_footer();
    exit;
}

$isLive = in_array($ride['status'], ['requested', 'accepted', 'ongoing'], true);
$payLabels = ['cash' => 'Cash', 'card' => 'Card', 'wallet' => 'CrabPay wallet'];

// Google Maps directions link (opens in a new tab; no API key needed)
$mapsUrl = null;
if ($ride['pickup_lat'] && $ride['dropoff_lat']) {
    $mapsUrl = 'https://www.google.com/maps/dir/?api=1&origin=' . $ride['pickup_lat'] . ',' . $ride['pickup_lng']
        . '&destination=' . $ride['dropoff_lat'] . ',' . $ride['dropoff_lng'];
}

admin_header('Ride #' . $ride['id'], 'rides');
?>

<a class="back-link" href="<?= e(url('admin/rides.php')) ?>">← All rides</a>

<section class="grid-main">
    <article class="card">
        <header class="card-head">
            <div>
                <h2>Trip details</h2>
                <p class="muted">Requested <?= e(fmt_datetime($ride['requested_at'])) ?></p>
            </div>
            <?= badge($ride['status']) ?>
        </header>

        <ol class="route-line">
            <li><span class="dot start"></span><div><small class="muted">Pick-up</small><strong><?= e($ride['pickup_address']) ?></strong></div></li>
            <li><span class="dot end"></span><div><small class="muted">Drop-off</small><strong><?= e($ride['dropoff_address']) ?></strong></div></li>
        </ol>

        <dl class="facts">
            <div><dt>Fare</dt><dd><?= e(money($ride['fare'])) ?></dd></div>
            <div><dt>Distance</dt><dd><?= e(number_format($ride['distance_km'], 1)) ?> km</dd></div>
            <div><dt>Payment</dt><dd><?= e($payLabels[$ride['payment_method']] ?? $ride['payment_method']) ?></dd></div>
            <div><dt>Completed</dt><dd><?= e(fmt_datetime($ride['completed_at'])) ?></dd></div>
            <div><dt>Passenger rating</dt><dd><?= $ride['rating'] ? str_repeat('★', (int)$ride['rating']) . '<span class="muted">' . str_repeat('★', 5 - (int)$ride['rating']) . '</span>' : '<span class="muted">Not rated</span>' ?></dd></div>
        </dl>

        <div class="card-actions">
            <?php if ($mapsUrl): ?>
                <a class="btn btn-outline" href="<?= e($mapsUrl) ?>" target="_blank" rel="noopener"><?= icon('pin', 16) ?> View route on map</a>
            <?php endif; ?>
            <?php if ($isLive): ?>
                <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= (int)$ride['id'] ?>">
                    <button class="btn btn-primary" name="action" value="cancel" data-confirm="Cancel ride #<?= (int)$ride['id'] ?>? The passenger and driver will see it as cancelled.">Cancel ride</button>
                </form>
            <?php endif; ?>
        </div>
    </article>

    <div class="stack">
        <article class="card">
            <header class="card-head"><h2>Passenger</h2></header>
            <div class="person big">
                <?= avatar($ride['p_name'], $ride['p_photo'], 'lg') ?>
                <div><a class="strong-link" href="<?= e(url('admin/passenger.php?id=' . $ride['passenger_id'])) ?>"><?= e($ride['p_name']) ?></a><small class="muted block"><?= e($ride['p_phone']) ?></small><small class="muted block"><?= e($ride['p_email']) ?></small></div>
            </div>
            <a class="link" href="<?= e(url('admin/passenger.php?id=' . $ride['passenger_id'])) ?>">View passenger profile <?= icon('arrow', 16) ?></a>
        </article>

        <article class="card">
            <header class="card-head"><h2>Driver</h2></header>
            <?php if ($ride['d_name']): ?>
                <div class="person big">
                    <?= avatar($ride['d_name'], $ride['d_photo'], 'lg') ?>
                    <div>
                        <a class="strong-link" href="<?= e(url('admin/driver.php?id=' . $ride['driver_id'])) ?>"><?= e($ride['d_name']) ?></a>
                        <small class="muted block"><?= e($ride['d_phone']) ?></small>
                        <small class="muted block"><?= e($ride['vehicle_model']) ?> · <span class="plate"><?= e($ride['plate_no']) ?></span></small>
                    </div>
                </div>
                <?php if ($ride['d_rating']): ?><span class="rating"><?= icon('star', 14) ?> <?= e($ride['d_rating']) ?> overall rating</span><?php endif; ?>
            <?php else: ?>
                <p class="empty">No driver assigned<?= $ride['status'] === 'requested' ? ' yet — searching nearby drivers.' : '.' ?></p>
            <?php endif; ?>
        </article>
    </div>
</section>

<?php admin_footer(); ?>
