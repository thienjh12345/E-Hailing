<?php
/**
 * Passenger → Book a ride.
 *  - No live trip: booking form (places, ride type, payment) with live fare estimates.
 *  - Live trip:    status tracker (finding driver → on the way → on trip) that refreshes itself.
 *  ?pickup=…&dropoff=…  pre-fills the form ("Book again" from ride history)
 *  ?poll=1              JSON {status, driver} used by the tracker to know when to refresh
 */
require __DIR__ . '/../includes/passenger_auth.php';
require __DIR__ . '/../includes/passenger_layout.php';
require __DIR__ . '/../includes/booking.php';
require __DIR__ . '/../includes/trip_road.php';
$p = require_passenger();
$pid = (int)$p['id'];

booking_expire_stale();
$live = passenger_live_ride($pid);

if (isset($_GET['poll'])) {
    header('Content-Type: application/json');
    header('Cache-Control: no-store');
    echo json_encode(['status' => $live['status'] ?? 'none', 'driver' => (int)($live['driver_id'] ?? 0), 'ride' => (int)($live['id'] ?? 0)]);
    exit;
}

$errors = [];
$v = [
    'pickup'         => '',
    'dropoff'        => '',
    'pickup_lat' => '', 'pickup_lng' => '', 'pickup_address' => '',
    'dropoff_lat' => '', 'dropoff_lng' => '', 'dropoff_address' => '',
    'pickup_note'    => '',
    'service_type'   => 'car',
    'payment_method' => $p['preferred_payment'],
    'card_id'        => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'book') {
        [$rideId, $errors] = booking_create($p, $_POST);
        if ($rideId) {
            flash('success', 'Trip booked! We\'re finding you a driver.');
            redirect('passenger/book.php');
        }
        if (isset($errors['form'])) {   // e.g. a trip is already live — the form isn't shown then, so use a notice
            flash('error', $errors['form']);
            redirect('passenger/book.php');
        }
        $v = array_merge($v, array_intersect_key($_POST, $v));
    } elseif ($action === 'cancel') {
        $ok = booking_cancel_by_passenger($pid, (int)($_POST['ride_id'] ?? 0));
        flash($ok ? 'success' : 'error', $ok ? 'Trip cancelled. You have not been charged.' : 'This trip can\'t be cancelled any more — it has already started.');
        redirect('passenger/book.php');
    }
}

// "Book again" links: ?pickup=Name&plat=..&plng=..&dropoff=Name&dlat=..&dlng=.. (coordinates optional for list places)
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    foreach (['pickup' => 'p', 'dropoff' => 'd'] as $field => $k) {
        $name = clean_address((string)($_GET[$field] ?? ''));
        $lat = $_GET[$k . 'lat'] ?? null; $lng = $_GET[$k . 'lng'] ?? null;
        if (is_numeric($lat) && is_numeric($lng) && city_for_point((float)$lat, (float)$lng)) {
            $v[$field . '_lat'] = (string)(float)$lat; $v[$field . '_lng'] = (string)(float)$lng; $v[$field . '_address'] = $name;
        } elseif ($place = find_location($name)) {
            $v[$field . '_lat'] = (string)$place[1]; $v[$field . '_lng'] = (string)$place[2]; $v[$field . '_address'] = $name;
        }
    }
}
// Open the map on the passenger's usual city
$s = db()->prepare('SELECT city FROM rides WHERE passenger_id = ? ORDER BY id DESC LIMIT 1');
$s->execute([$pid]);
$defaultCity = $s->fetchColumn() ?: 'klang_valley';

$cards = passenger_cards($pid);
$usableCards = array_values(array_filter($cards, fn($c) => !card_expired($c)));
if ($v['card_id'] === '' && $usableCards) $v['card_id'] = (string)$usableCards[0]['id'];
if ($v['payment_method'] === 'card' && !$usableCards) $v['payment_method'] = 'cash';
$balance = wallet_balance($pid);

// Just finished? Show the "you've arrived" summary for 30 minutes after the trip.
$justDone = null;
if (!$live) {
    $s = db()->prepare("SELECT r.*, d.name AS driver_name FROM rides r LEFT JOIN drivers d ON d.id = r.driver_id
                        WHERE r.passenger_id = ? AND r.status = 'completed' AND r.completed_at >= NOW() - INTERVAL 30 MINUTE
                        ORDER BY r.completed_at DESC LIMIT 1");
    $s->execute([$pid]);
    $justDone = $s->fetch() ?: null;
}
$livePayment = $live ? payment_for_ride((int)$live['id']) : null;
$donePayment = $justDone ? payment_for_ride((int)$justDone['id']) : null;

passenger_header($live ? 'Your trip' : 'Book a ride', 'book');
?>

<?php if ($live): ?>
    <?php
    $steps = ['requested' => 'Finding a driver', 'accepted' => 'Driver on the way', 'ongoing' => 'On your way', 'completed' => 'Arrived'];
    $order = array_keys($steps);
    $now = array_search($live['status'], $order, true);
    $headline = [
        'requested' => 'Finding you a driver…',
        'accepted'  => ($live['driver_name'] ?? 'Your driver') . ' is on the way',
        'ongoing'   => 'Enjoy your ride!',
    ][$live['status']];
    $sub = [
        'requested' => 'We\'re sending your request to nearby ' . FARES[$live['service_type']]['label'] . ' drivers. If nobody accepts within ' . REQUEST_TIMEOUT_MINUTES . ' minutes it\'s cancelled automatically — you won\'t be charged.',
        'accepted'  => 'Meet your driver at ' . $live['pickup_address'] . '. Check the plate number before you get in.',
        'ongoing'   => 'Heading to ' . $live['dropoff_address'] . ' — about ' . trip_minutes((float)$live['distance_km']) . ' min.',
    ][$live['status']];
    ?>
    <?php if ($live['driver_id']): ?><?= trip_road($live, 'passenger') ?><?php endif; ?>
    <section class="grid-main" data-live-trip data-poll="<?= e(url('passenger/book.php?poll=1')) ?>" data-status="<?= e($live['status']) ?>" data-driver="<?= (int)$live['driver_id'] ?>">
        <article class="card trip-live">
            <ol class="stepper" aria-label="Trip progress">
                <?php foreach ($steps as $key => $label): $i = array_search($key, $order, true); ?>
                    <li class="<?= $i < $now ? 'done' : ($i === $now ? 'current' : '') ?>"<?= $i === $now ? ' aria-current="step"' : '' ?>><span class="step-dot"></span><?= e($label) ?></li>
                <?php endforeach; ?>
            </ol>

            <div class="trip-headline">
                <?php if ($live['status'] === 'requested'): ?><span class="searching" aria-hidden="true"></span><?php endif; ?>
                <div><h2><?= e($headline) ?></h2><p class="muted"><?= e($sub) ?></p></div>
            </div>

            <?php if ($live['driver_id']): ?>
                <div class="driver-panel">
                    <?= driver_chip($live) ?>
                    <div class="driver-panel-actions">
                        <a class="btn btn-primary btn-sm" href="<?= e(url('passenger/messages.php?ride=' . $live['id'])) ?>"><?= icon('chat', 15) ?> Message</a>
                        <?php if ($live['driver_phone']): ?><span class="muted small">Call <?= e($live['driver_phone']) ?></span><?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <ol class="route-line">
                <li><span class="dot start"></span><div><small class="muted">Pick-up</small><strong><?= e($live['pickup_address']) ?></strong><?php if ($live['pickup_note']): ?><small class="muted block">“<?= e($live['pickup_note']) ?>”</small><?php endif; ?></div></li>
                <li><span class="dot end"></span><div><small class="muted">Destination</small><strong><?= e($live['dropoff_address']) ?></strong></div></li>
            </ol>

            <?php if (in_array($live['status'], ['requested', 'accepted'], true)): ?>
                <form method="post" class="card-actions">
                    <?= csrf_field() ?>
                    <input type="hidden" name="ride_id" value="<?= (int)$live['id'] ?>">
                    <button class="btn btn-ghost" name="action" value="cancel" data-confirm="Cancel this trip? You won't be charged.">Cancel trip</button>
                </form>
            <?php endif; ?>
        </article>

        <article class="card">
            <header class="card-head"><div><h2>Trip details</h2><p class="muted">Booked <?= e(time_ago($live['requested_at'])) ?> · Trip #<?= (int)$live['id'] ?></p></div></header>
            <dl class="facts one-col">
                <div><dt>Ride type</dt><dd><?= e(FARES[$live['service_type']]['label']) ?></dd></div>
                <div><dt>Distance</dt><dd><?= e(number_format((float)$live['distance_km'], 1)) ?> km · about <?= trip_minutes((float)$live['distance_km']) ?> min</dd></div>
                <div><dt>Fare</dt><dd class="fare-big"><?= e(money($live['fare'])) ?></dd></div>
                <div><dt>Payment</dt><dd><?= e($livePayment ? payment_method_label($livePayment) : PAYMENT_LABELS[$live['payment_method']]) ?>
                    <small class="muted block"><?= $live['payment_method'] === 'cash' ? 'Pay your driver when you arrive.' : 'Charged automatically when the trip ends.' ?></small></dd></div>
            </dl>
            <a class="link small" href="<?= e(url('passenger/messages.php?support=1&about=' . $live['id'])) ?>">Need help with this trip?</a>
        </article>
    </section>

<?php else: ?>

    <?php if ($p['status'] === 'suspended'): ?>
        <div class="flash flash-error">Your account is suspended, so booking is turned off. Contact <a class="link" href="<?= e(url('passenger/messages.php?support=1')) ?>">Crab Support</a>.</div>
    <?php endif; ?>

    <?php if ($justDone): ?>
        <article class="card arrived-card">
            <div class="arrived-icon"><?= icon('check', 26) ?></div>
            <div class="arrived-text">
                <h2>You've arrived at <?= e($justDone['dropoff_address']) ?></h2>
                <p class="muted">Trip #<?= (int)$justDone['id'] ?> with <?= e($justDone['driver_name'] ?? 'your driver') ?> · <?= e(money($justDone['fare'])) ?> · <?= e($donePayment ? payment_method_label($donePayment) : '') ?> <?= payment_badge($donePayment) ?></p>
                <?php if ($donePayment && $donePayment['note']): ?><p class="small muted"><?= e($donePayment['note']) ?></p><?php endif; ?>
            </div>
            <div class="arrived-actions">
                <a class="btn btn-outline btn-sm" href="<?= e(url('passenger/receipt.php?id=' . $justDone['id'])) ?>">View receipt</a>
                <?php if (!$justDone['rating']): ?><a class="btn btn-primary btn-sm" href="<?= e(url('passenger/rides.php?status=unrated')) ?>"><?= icon('star', 15) ?> Rate trip</a><?php endif; ?>
            </div>
        </article>
    <?php endif; ?>

    <?php if (!empty($errors['form'])): ?><div class="flash flash-error" role="alert"><?= e($errors['form']) ?></div><?php endif; ?>

    <form method="post" class="grid-main booking" novalidate data-booking
          data-locations="<?= e(json_encode(LOCATIONS)) ?>" data-fares="<?= e(json_encode(FARES)) ?>" data-balance="<?= e((string)$balance) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="book">

        <div class="stack">
            <article class="card">
                <header class="card-head"><div><h2>Where to?</h2><p class="muted">Crab is available in the Klang Valley and Kota Kinabalu</p></div></header>
                <div class="place-picker" data-place-picker
                     data-areas="<?= e(json_encode(SERVICE_AREAS)) ?>" data-places="<?= e(json_encode(LOCATIONS)) ?>"
                     data-cities="<?= e(json_encode(CITIES)) ?>" data-default-city="<?= e($defaultCity) ?>">
                    <?php foreach (['pickup', 'dropoff'] as $field): foreach (['lat', 'lng', 'address'] as $part): ?>
                        <input type="hidden" name="<?= $field . '_' . $part ?>" value="<?= e($v[$field . '_' . $part]) ?>" data-pp-value="<?= $field . '-' . $part ?>">
                    <?php endforeach; endforeach; ?>

                    <div class="pp-points">
                        <?php foreach (['pickup' => 'Pick-up', 'dropoff' => 'Destination'] as $field => $label): $addr = $v[$field . '_address']; ?>
                            <button type="button" class="pp-point" data-pp-tab="<?= $field ?>" aria-pressed="false">
                                <span class="dot <?= $field === 'pickup' ? 'start' : 'end' ?> inline"></span>
                                <span class="pp-point-text"><small><?= e($label) ?></small>
                                    <strong data-pp-label="<?= $field ?>" class="<?= $addr === '' ? 'muted' : '' ?>"><?= e($addr !== '' ? $addr : 'Choose on the map') ?></strong></span>
                                <span class="pp-edit"><?= icon('edit', 14) ?></span>
                            </button>
                            <small class="field-error" data-pp-error="<?= $field ?>"<?= isset($errors[$field]) ? '' : ' hidden' ?>><?= e($errors[$field] ?? '') ?></small>
                        <?php endforeach; ?>
                        <button type="button" class="icon-btn pp-swap" data-pp-swap title="Swap pick-up and destination" aria-label="Swap pick-up and destination"><?= icon('arrow', 16) ?></button>
                    </div>

                    <div class="pp-map-wrap" data-pp-mapwrap hidden>
                        <div class="pp-map" data-pp-map aria-label="Map — drag to move the pin"></div>
                        <div class="pp-pin pp-pin-pickup" data-pp-pin aria-hidden="true">
                            <svg viewBox="0 0 32 44" width="32" height="44"><path class="pin-body" d="M16 0C7.2 0 0 7 0 15.7 0 27.5 16 44 16 44s16-16.5 16-28.3C32 7 24.8 0 16 0z"/><circle cx="16" cy="15.5" r="6" fill="#fff"/></svg>
                            <span class="pp-pin-shadow"></span>
                        </div>
                        <div class="pp-search">
                            <button type="button" class="btn btn-sm pp-search-btn" data-pp-search-open><?= icon('search', 16) ?> Search</button>
                            <div class="pp-search-box" data-pp-search-box hidden>
                                <div class="pp-search-row">
                                    <?= icon('search', 16) ?>
                                    <input type="search" data-pp-search-input placeholder="Search a place or address" aria-label="Search a place or address" autocomplete="off">
                                    <button type="button" class="btn btn-sm btn-primary" data-pp-search-go>Search</button>
                                    <button type="button" class="icon-btn" data-pp-search-close aria-label="Close search"><?= icon('x', 16) ?></button>
                                </div>
                                <ul class="pp-results" data-pp-results></ul>
                            </div>
                        </div>
                        <div class="pp-sheet">
                            <div class="pp-sheet-text"><strong data-pp-address>Drag the map to place the pin</strong><small class="muted" data-pp-note></small></div>
                            <button type="button" class="btn btn-primary btn-sm" data-pp-confirm disabled>Set pick-up here</button>
                        </div>
                    </div>
                <div class="where pp-fallback" data-pp-fallback>
                    <p class="small muted">The map couldn’t load, so choose from Crab’s popular places instead.</p>
                    <?php foreach (['pickup' => 'Pick-up point', 'dropoff' => 'Destination'] as $field => $label): ?>
                        <label class="field<?= isset($errors[$field]) ? ' has-error' : '' ?>">
                            <span><span class="dot <?= $field === 'pickup' ? 'start' : 'end' ?> inline"></span> <?= e($label) ?></span>
                            <select name="<?= $field ?>" required data-place="<?= $field ?>">
                                <option value=""><?= $field === 'pickup' ? 'Where should the driver pick you up?' : 'Where are you going?' ?></option>
                                <?php foreach (LOCATIONS as $city => $places): ?>
                                    <optgroup label="<?= e(CITIES[$city]) ?>" data-city="<?= e($city) ?>">
                                        <?php foreach ($places as $name => $_): ?>
                                            <option value="<?= e($name) ?>"<?= $v[$field] === $name ? ' selected' : '' ?>><?= e($name) ?></option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                <?php endforeach; ?>
                            </select>
                            <?php if (isset($errors[$field])): ?><small class="field-error"><?= e($errors[$field]) ?></small><?php endif; ?>
                        </label>
                    <?php endforeach; ?>
                </div>
                </div>
                <?= form_field('pickup_note', 'Note for your driver', $v['pickup_note'], $errors, ['placeholder' => 'e.g. Main entrance, I\'m wearing a red shirt', 'attrs' => 'maxlength="' . PICKUP_NOTE_MAX . '"']) ?>
            </article>

            <article class="card">
                <header class="card-head"><div><h2>Choose a ride</h2><p class="muted" data-trip-meta>Choose pick-up and destination to see prices</p></div></header>
                <div class="ride-types<?= isset($errors['service_type']) ? ' has-error' : '' ?>">
                    <?php foreach (FARES as $type => $f): ?>
                        <label class="ride-type">
                            <input type="radio" name="service_type" value="<?= e($type) ?>"<?= $v['service_type'] === $type ? ' checked' : '' ?>>
                            <span class="ride-type-body">
                                <span class="ride-type-icon"><?= icon($type === 'bike' ? 'driver' : 'rides', 22) ?></span>
                                <span class="ride-type-text"><strong><?= e($f['label']) ?></strong><small class="muted"><?= e($f['seats']) ?> · <?= e($f['desc']) ?></small></span>
                                <span class="ride-type-price" data-price="<?= e($type) ?>">from <?= e(money($f['base'])) ?></span>
                            </span>
                        </label>
                    <?php endforeach; ?>
                </div>
                <?php if (isset($errors['service_type'])): ?><small class="field-error"><?= e($errors['service_type']) ?></small><?php endif; ?>
            </article>
        </div>

        <div class="stack">
            <article class="card">
                <header class="card-head"><div><h2>Payment</h2><p class="muted">Cards and wallet are charged when the trip ends</p></div></header>
                <div class="pay-options<?= isset($errors['payment_method']) ? ' has-error' : '' ?>">
                    <label class="pay-option">
                        <input type="radio" name="payment_method" value="cash"<?= $v['payment_method'] === 'cash' ? ' checked' : '' ?>>
                        <span><?= icon('money', 18) ?> <strong>Cash</strong><small class="muted">Pay the driver at the end</small></span>
                    </label>
                    <label class="pay-option<?= $usableCards ? '' : ' disabled' ?>">
                        <input type="radio" name="payment_method" value="card"<?= $v['payment_method'] === 'card' ? ' checked' : '' ?><?= $usableCards ? '' : ' disabled' ?>>
                        <span><?= icon('id', 18) ?> <strong>Card</strong>
                            <?php if ($usableCards): ?>
                                <select name="card_id" aria-label="Which card">
                                    <?php foreach ($usableCards as $c): ?><option value="<?= (int)$c['id'] ?>"<?= (string)$c['id'] === (string)$v['card_id'] ? ' selected' : '' ?>><?= e(card_label($c)) ?></option><?php endforeach; ?>
                                </select>
                            <?php else: ?>
                                <small class="muted">No card saved · <a class="link" href="<?= e(url('passenger/wallet.php#cards')) ?>">Add a card</a></small>
                            <?php endif; ?>
                        </span>
                    </label>
                    <label class="pay-option">
                        <input type="radio" name="payment_method" value="wallet"<?= $v['payment_method'] === 'wallet' ? ' checked' : '' ?>>
                        <span><?= icon('wallet', 18) ?> <strong>CrabPay wallet</strong><small class="muted">Balance <?= e(money($balance)) ?> · <a class="link" href="<?= e(url('passenger/wallet.php')) ?>">Top up</a></small>
                            <small class="field-error" data-wallet-warning hidden>Your balance is lower than this fare.</small></span>
                    </label>
                </div>
                <?php if (isset($errors['payment_method'])): ?><small class="field-error"><?= e($errors['payment_method']) ?></small><?php endif; ?>
            </article>

            <article class="card summary">
                <header class="card-head"><h2>Summary</h2></header>
                <dl class="summary-list">
                    <div><dt>Distance</dt><dd data-sum="distance">—</dd></div>
                    <div><dt>Estimated time</dt><dd data-sum="time">—</dd></div>
                    <div><dt>Base fare</dt><dd data-sum="base">—</dd></div>
                    <div><dt>Distance charge</dt><dd data-sum="per_km">—</dd></div>
                    <div class="total"><dt>Total</dt><dd data-sum="total">—</dd></div>
                </dl>
                <button class="btn btn-primary btn-block book-btn"<?= $p['status'] === 'suspended' ? ' disabled' : '' ?>><?= icon('rides', 18) ?> Book ride</button>
                <p class="small muted center">You can cancel for free until your driver picks you up.</p>
            </article>
        </div>
    </form>
<?php endif; ?>
<?php if (!$live): ?>
    <link rel="stylesheet" href="<?= e(asset('assets/vendor/leaflet/leaflet.css')) ?>">
    <script src="<?= e(asset('assets/vendor/leaflet/leaflet.js')) ?>" defer></script>
    <script src="<?= e(asset('assets/js/map-picker.js')) ?>" defer></script>
<?php elseif ($live['driver_id']): ?>
    <script src="<?= e(asset('assets/js/trip-road.js')) ?>" defer></script>
    <script src="<?= e(asset('assets/js/emergency.js')) ?>" defer></script>
<?php endif; ?>
<script src="<?= e(asset('assets/js/booking.js')) ?>" defer></script>

<?php passenger_footer(); ?>
