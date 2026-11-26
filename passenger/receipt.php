<?php
/**
 * Passenger → trip receipt (fare breakdown + payment). Only the trip's own passenger can view it.
 */
require __DIR__ . '/../includes/passenger_auth.php';
require __DIR__ . '/../includes/passenger_layout.php';
require __DIR__ . '/../includes/booking.php';
$p = require_passenger();

$s = db()->prepare("SELECT r.*, d.name AS driver_name, d.plate_no, d.vehicle_model, d.vehicle_color
                    FROM rides r LEFT JOIN drivers d ON d.id = r.driver_id WHERE r.id = ? AND r.passenger_id = ?");
$s->execute([(int)($_GET['id'] ?? 0), $p['id']]);
$r = $s->fetch();
if (!$r) {
    flash('error', 'That receipt was not found.');
    redirect('passenger/rides.php');
}
$pay = payment_for_ride((int)$r['id']);
$type = $r['service_type'] ?? 'car';
$f = trip_fare($type, (float)$r['distance_km']);
// Older trips may have been priced slightly differently; the stored fare is always what was paid.
$adjust = round((float)$r['fare'] - $f['total'], 2);

passenger_header('Receipt', 'rides');
?>

<a class="back-link" href="<?= e(url('passenger/rides.php')) ?>">← My rides</a>

<article class="card receipt">
    <header class="receipt-head">
        <div class="login-brand"><?= crab_logo(30) ?><span class="brand-name"><?= e(strtolower(APP_NAME)) ?></span></div>
        <div class="receipt-ref">
            <strong>Receipt <?= e($pay['reference'] ?? ('#' . $r['id'])) ?></strong>
            <small class="muted"><?= e(fmt_datetime($r['completed_at'] ?? $r['requested_at'], 'l, j F Y · g:i A')) ?></small>
        </div>
    </header>

    <div class="receipt-total">
        <span class="muted"><?= $r['status'] === 'completed' ? 'Total paid' : 'Trip ' . e($r['status']) ?></span>
        <strong><?= e(money($r['fare'])) ?></strong>
        <?= payment_badge($pay) ?>
    </div>

    <ol class="route-line">
        <li><span class="dot start"></span><div><small class="muted">Pick-up<?= $r['started_at'] ? ' · ' . e(date('g:i A', strtotime($r['started_at']))) : '' ?></small><strong><?= e($r['pickup_address']) ?></strong></div></li>
        <li><span class="dot end"></span><div><small class="muted">Drop-off<?= $r['completed_at'] ? ' · ' . e(date('g:i A', strtotime($r['completed_at']))) : '' ?></small><strong><?= e($r['dropoff_address']) ?></strong></div></li>
    </ol>

    <dl class="summary-list">
        <div><dt><?= e(FARES[$type]['label']) ?> · base fare</dt><dd><?= e(money($f['base'])) ?></dd></div>
        <div><dt>Distance <?= e(number_format((float)$r['distance_km'], 1)) ?> km × <?= e(money($f['per_km'])) ?></dt><dd><?= e(money($f['distance'])) ?></dd></div>
        <?php if (abs($adjust) >= 0.01): ?><div><dt>Rounding / adjustment</dt><dd><?= e(money($adjust)) ?></dd></div><?php endif; ?>
        <div class="total"><dt>Total</dt><dd><?= e(money($r['fare'])) ?></dd></div>
    </dl>

    <dl class="facts">
        <div><dt>Paid with</dt><dd><?= e($pay ? payment_method_label($pay) : (PAYMENT_LABELS[$r['payment_method']] ?? '')) ?></dd></div>
        <div><dt>Driver</dt><dd><?= e($r['driver_name'] ?? '—') ?><?php if ($r['plate_no']): ?> <span class="plate"><?= e($r['plate_no']) ?></span><?php endif; ?></dd></div>
        <div><dt>Trip</dt><dd>#<?= (int)$r['id'] ?> · <?= e(CITIES[$r['city']] ?? '') ?></dd></div>
        <?php if ($pay && $pay['status'] === 'refunded'): ?>
            <div><dt>Refunded</dt><dd><?= e(fmt_datetime($pay['refunded_at'])) ?><small class="muted block">To your CrabPay wallet</small></dd></div>
        <?php endif; ?>
    </dl>
    <?php if ($pay && $pay['note']): ?><p class="small muted"><?= e($pay['note']) ?></p><?php endif; ?>

    <footer class="receipt-actions">
        <button type="button" class="btn btn-outline btn-sm" onclick="window.print()">Print / save as PDF</button>
        <a class="btn btn-ghost btn-sm" href="<?= e(url('passenger/messages.php?support=1&about=' . $r['id'])) ?>">Report a problem</a>
        <a class="btn btn-ghost btn-sm" href="<?= e(url('passenger/book.php?pickup=' . rawurlencode($r['pickup_address']) . '&dropoff=' . rawurlencode($r['dropoff_address']))) ?>">Book this trip again</a>
    </footer>
</article>

<?php passenger_footer(); ?>
