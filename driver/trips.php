<?php
/**
 * Driver → Ride requests & current trip.
 *  - Online + no trip: open requests in your city for your vehicle type (refreshes itself).
 *  - On a trip: start / complete (settles payment) / hand back before pick-up.
 */
require __DIR__ . '/../includes/driver_auth.php';
require __DIR__ . '/../includes/driver_layout.php';
require __DIR__ . '/../includes/booking.php';
$d = require_driver();
booking_expire_stale();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    $rideId = (int)($_POST['ride_id'] ?? 0);
    $result = null;

    switch ($action) {
        case 'accept':   $result = driver_accept($d, $rideId); break;
        case 'start':    $result = driver_start_trip($d, $rideId); break;
        case 'complete': $result = driver_complete_trip($d, $rideId); break;
        case 'release':  $result = driver_release_trip($d, $rideId); break;
        case 'city':
            $city = (string)($_POST['city'] ?? '');
            if (isset(CITIES[$city])) {
                if (driver_active_trip((int)$d['id'])) {
                    $result = [false, 'Finish your current trip before changing city.'];
                } else {
                    db()->prepare('UPDATE drivers SET city = ?, updated_at = updated_at WHERE id = ?')->execute([$city, $d['id']]);
                    $result = [true, 'You\'ll now get ride requests in ' . CITIES[$city] . '.'];
                }
            }
            break;
        case 'online':
            if ($d['status'] === 'approved') {
                $days = license_days_left($d['license_expiry']);
                if (!$d['is_online'] && $days !== null && $days < 0) {
                    $result = [false, 'Your driving licence has expired. Update it under Licence & vehicle before going online.'];
                } elseif ($d['is_online'] && driver_active_trip((int)$d['id'])) {
                    $result = [false, 'Finish your current trip before going offline.'];
                } else {
                    db()->prepare("UPDATE drivers SET is_online = 1 - is_online, updated_at = updated_at WHERE id = ? AND status = 'approved'")->execute([$d['id']]);
                    $result = [true, $d['is_online'] ? 'You\'re offline.' : 'You\'re online — new requests will appear here.'];
                }
            }
            break;
    }
    if ($result) flash($result[0] ? 'success' : 'error', $result[1]);
    redirect('driver/trips.php');
}

$trip = driver_active_trip((int)$d['id']);
$block = driver_block_reason($d);
$requests = (!$trip && !$block) ? driver_open_requests($d) : [];

// Today's numbers for the side panel
$s = db()->prepare("SELECT COUNT(*) AS trips, COALESCE(SUM(fare), 0) AS earned, COALESCE(SUM(CASE WHEN payment_method = 'cash' THEN fare END), 0) AS cash
                    FROM rides WHERE driver_id = ? AND status = 'completed' AND completed_at >= CURDATE()");
$s->execute([$d['id']]);
$today = $s->fetch();

driver_header($trip ? 'Current trip' : 'Ride requests', 'trips');
?>

<?php if ($trip): ?>
    <?php $pickupPhase = $trip['status'] === 'accepted'; ?>
    <section class="grid-main">
        <article class="card trip-live">
            <div class="trip-headline">
                <div>
                    <h2><?= $pickupPhase ? 'Pick up ' . e(strtok($trip['passenger_name'], ' ')) : 'Drive to the destination' ?></h2>
                    <p class="muted"><?= $pickupPhase ? 'Go to ' . e($trip['pickup_address']) . '. Press “Start trip” once the passenger is in the car.' : 'Press “Complete trip” when you arrive at ' . e($trip['dropoff_address']) . '.' ?></p>
                </div>
                <?= badge($trip['status']) ?>
            </div>

            <div class="driver-panel">
                <div class="person"><?= avatar($trip['passenger_name'], $trip['passenger_photo'], 'lg') ?>
                    <div><strong><?= e($trip['passenger_name']) ?></strong><small class="muted block"><?= e($trip['passenger_phone']) ?></small></div></div>
                <div class="driver-panel-actions">
                    <a class="btn btn-outline btn-sm" href="<?= e(url('driver/messages.php?ride=' . $trip['id'])) ?>"><?= icon('chat', 15) ?> Message</a>
                </div>
            </div>

            <ol class="route-line">
                <li><span class="dot start"></span><div><small class="muted">Pick-up</small><strong><?= e($trip['pickup_address']) ?></strong><?php if ($trip['pickup_note']): ?><small class="pickup-note">“<?= e($trip['pickup_note']) ?>”</small><?php endif; ?></div></li>
                <li><span class="dot end"></span><div><small class="muted">Destination</small><strong><?= e($trip['dropoff_address']) ?></strong></div></li>
            </ol>

            <form method="post" class="card-actions">
                <?= csrf_field() ?>
                <input type="hidden" name="ride_id" value="<?= (int)$trip['id'] ?>">
                <?php if ($pickupPhase): ?>
                    <button class="btn btn-primary" name="action" value="start"><?= icon('flag', 16) ?> Start trip</button>
                    <button class="btn btn-ghost" name="action" value="release" data-confirm="Hand this trip back? Another driver will be found for the passenger.">Can't make it — hand back</button>
                <?php else: ?>
                    <button class="btn btn-primary" name="action" value="complete" data-confirm="<?= $trip['payment_method'] === 'cash' ? 'Complete the trip and collect ' . e(money($trip['fare'])) . ' in cash?' : 'Complete the trip? The passenger will be charged ' . e(money($trip['fare'])) . '.' ?>"><?= icon('check', 16) ?> Complete trip</button>
                <?php endif; ?>
            </form>
        </article>

        <article class="card">
            <header class="card-head"><div><h2>Fare</h2><p class="muted">Trip #<?= (int)$trip['id'] ?> · <?= e(FARES[$trip['service_type']]['label']) ?></p></div></header>
            <p class="fare-big"><?= e(money($trip['fare'])) ?></p>
            <dl class="facts one-col">
                <div><dt>Payment</dt><dd><?= e(PAYMENT_LABELS[$trip['payment_method']]) ?>
                    <small class="muted block"><?= $trip['payment_method'] === 'cash' ? 'Collect cash from the passenger at the end.' : 'Charged automatically when you complete the trip — no cash needed.' ?></small></dd></div>
                <div><dt>Distance</dt><dd><?= e(number_format((float)$trip['distance_km'], 1)) ?> km · about <?= trip_minutes((float)$trip['distance_km']) ?> min</dd></div>
            </dl>
        </article>
    </section>

<?php else: ?>
    <section class="grid-main"<?= !$block ? ' data-auto-refresh="10"' : '' ?>>
        <article class="card">
            <header class="card-head">
                <div><h2>Open requests</h2><p class="muted"><?= e(FARES[$d['vehicle_type']]['label']) ?> · <?= e(CITIES[$d['city']]) ?><?= !$block ? ' · updates automatically' : '' ?></p></div>
            </header>
            <?php if ($block): ?>
                <div class="empty-state">
                    <span class="empty-icon"><?= icon('power', 22) ?></span>
                    <strong><?= e($block) ?></strong>
                    <?php if ($d['status'] === 'approved' && !$d['is_online']): ?>
                        <form method="post"><?= csrf_field() ?><button class="btn btn-primary" name="action" value="online"><?= icon('power', 16) ?> Go online</button></form>
                    <?php endif; ?>
                </div>
            <?php elseif (!$requests): ?>
                <div class="empty-state">
                    <span class="searching" aria-hidden="true"></span>
                    <strong>Waiting for ride requests…</strong>
                    <p class="muted">New <?= e(FARES[$d['vehicle_type']]['label']) ?> requests in <?= e(CITIES[$d['city']]) ?> will show up here.</p>
                </div>
            <?php else: ?>
                <ul class="request-list">
                    <?php foreach ($requests as $r): ?>
                        <li>
                            <div class="request-main">
                                <div class="request-top"><strong class="fare-mid"><?= e(money($r['fare'])) ?></strong>
                                    <span class="muted small"><?= e(number_format((float)$r['distance_km'], 1)) ?> km · <?= e(PAYMENT_LABELS[$r['payment_method']]) ?> · <?= e(time_ago($r['requested_at'])) ?></span></div>
                                <ol class="route-line compact">
                                    <li><span class="dot start"></span><div><?= e($r['pickup_address']) ?><?php if ($r['pickup_note']): ?> <small class="muted">· “<?= e($r['pickup_note']) ?>”</small><?php endif; ?></div></li>
                                    <li><span class="dot end"></span><div><?= e($r['dropoff_address']) ?></div></li>
                                </ol>
                                <small class="muted"><?= avatar($r['passenger_name'], $r['passenger_photo'], 'sm') ?> <?= e($r['passenger_name']) ?></small>
                            </div>
                            <form method="post">
                                <?= csrf_field() ?><input type="hidden" name="ride_id" value="<?= (int)$r['id'] ?>">
                                <button class="btn btn-primary" name="action" value="accept">Accept</button>
                            </form>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </article>

        <div class="stack">
            <article class="card">
                <header class="card-head"><div><h2>Status</h2></div>
                    <?php if ($d['status'] === 'approved'): ?><span class="presence <?= $d['is_online'] ? 'on' : '' ?>"><?= $d['is_online'] ? 'Online' : 'Offline' ?></span><?php endif; ?></header>
                <?php if ($d['status'] === 'approved' && $d['is_online']): ?>
                    <form method="post"><?= csrf_field() ?><button class="btn btn-ghost btn-sm" name="action" value="online">Go offline</button></form>
                <?php endif; ?>
                <form method="post" class="city-form">
                    <?= csrf_field() ?><input type="hidden" name="action" value="city">
                    <label class="field"><span>I'm driving in</span>
                        <select name="city" onchange="this.form.submit()">
                            <?php foreach (CITIES as $key => $label): ?><option value="<?= e($key) ?>"<?= $d['city'] === $key ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
                        </select></label>
                    <noscript><button class="btn btn-sm btn-outline">Save</button></noscript>
                </form>
            </article>
            <article class="card">
                <header class="card-head"><div><h2>Today</h2><p class="muted"><?= e(date('l, j M')) ?></p></div></header>
                <div class="stat-pair">
                    <div><span class="stat-big"><?= (int)$today['trips'] ?></span><span class="muted">trips</span></div>
                    <div><span class="stat-big"><?= e(money($today['earned'])) ?></span><span class="muted">earned · <?= e(money($today['cash'])) ?> cash</span></div>
                </div>
                <a class="link small" href="<?= e(url('driver/earnings.php')) ?>">All earnings <?= icon('arrow', 14) ?></a>
            </article>
        </div>
    </section>
<?php endif; ?>
<script src="<?= e(asset('assets/js/booking.js')) ?>" defer></script>

<?php driver_footer(); ?>
