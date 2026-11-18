<?php
require __DIR__ . '/../includes/admin_auth.php';
require __DIR__ . '/../includes/admin_layout.php';
require __DIR__ . '/../includes/charts.php';
require_admin();

$pdo = db();

/* ---------------- KPI tiles ---------------- */

$kpi = $pdo->query("
    SELECT
      SUM(DATE(requested_at) = CURDATE())                                                  AS rides_today,
      SUM(DATE(requested_at) = CURDATE() - INTERVAL 1 DAY)                                 AS rides_yday,
      COALESCE(SUM(CASE WHEN status='completed' AND DATE(completed_at) = CURDATE() THEN fare END), 0)                    AS rev_today,
      COALESCE(SUM(CASE WHEN status='completed' AND DATE(completed_at) = CURDATE() - INTERVAL 1 DAY THEN fare END), 0)   AS rev_yday,
      SUM(status IN ('requested','accepted','ongoing'))                                    AS live_now,
      SUM(status = 'requested')                                                            AS waiting
    FROM rides
")->fetch();

$drv = $pdo->query("
    SELECT SUM(status='approved' AND is_online=1) AS online,
           SUM(status='approved')                 AS approved,
           SUM(status='pending')                  AS pending
    FROM drivers
")->fetch();

$paxTotal = (int)$pdo->query("SELECT COUNT(*) FROM passengers")->fetchColumn();
$paxNew   = (int)$pdo->query("SELECT COUNT(*) FROM passengers WHERE created_at >= CURDATE() - INTERVAL 6 DAY")->fetchColumn();

/** "+12% vs yesterday" style change chip. */
function delta(float $now, float $before): string
{
    if ($before <= 0) {
        return $now > 0 ? '<span class="delta up">New today</span>' : '<span class="delta">No change</span>';
    }
    $pct = ($now - $before) / $before * 100;
    $cls = $pct >= 0 ? 'up' : 'down';
    $arrow = $pct >= 0 ? '▲' : '▼';
    return '<span class="delta ' . $cls . '">' . $arrow . ' ' . abs(round($pct)) . '% <span class="muted">vs yesterday</span></span>';
}

/* ---------------- 14-day series ---------------- */

$days = 14;
$rows = $pdo->query("
    SELECT DATE(requested_at) AS d,
           COUNT(*) AS rides,
           COALESCE(SUM(CASE WHEN status='completed' THEN fare END), 0) AS revenue
    FROM rides
    WHERE requested_at >= CURDATE() - INTERVAL " . ($days - 1) . " DAY
    GROUP BY DATE(requested_at)
")->fetchAll();
$byDay = array_column($rows, null, 'd');

$ridesSeries = $revSeries = [];
for ($i = $days - 1; $i >= 0; $i--) {
    $ts  = strtotime("-$i day");
    $key = date('Y-m-d', $ts);
    $label = $i === 0 ? 'Today' : date('j M', $ts);
    $full  = date('l, j M', $ts);
    $ridesSeries[] = ['label' => $label, 'full' => $full, 'value' => (int)($byDay[$key]['rides'] ?? 0)];
    $revSeries[]   = ['label' => $label, 'full' => $full, 'value' => (float)($byDay[$key]['revenue'] ?? 0)];
}
$rides14 = array_sum(array_column($ridesSeries, 'value'));
$rev14   = array_sum(array_column($revSeries, 'value'));

/* ---------------- Breakdown panels (last 14 days) ---------------- */

$payRows = $pdo->query("
    SELECT payment_method AS m, COUNT(*) AS n, SUM(fare) AS amt
    FROM rides
    WHERE status='completed' AND requested_at >= CURDATE() - INTERVAL " . ($days - 1) . " DAY
    GROUP BY payment_method ORDER BY n DESC
")->fetchAll();
$payLabels = ['cash' => 'Cash', 'card' => 'Card', 'wallet' => 'CrabPay wallet'];
$payShare = array_map(fn($r) => [
    'label' => $payLabels[$r['m']] ?? $r['m'],
    'value' => (int)$r['n'],
    'note'  => $r['n'] . ' rides · ' . money($r['amt']),
], $payRows);

$statusCounts = $pdo->query("
    SELECT status, COUNT(*) AS n FROM rides
    WHERE requested_at >= CURDATE() - INTERVAL " . ($days - 1) . " DAY
    GROUP BY status
")->fetchAll(PDO::FETCH_KEY_PAIR);
$completed = (int)($statusCounts['completed'] ?? 0);
$cancelled = (int)($statusCounts['cancelled'] ?? 0);
$completionRate = ($completed + $cancelled) > 0 ? round($completed / ($completed + $cancelled) * 100, 1) : 0;
$avgRating = $pdo->query("SELECT ROUND(AVG(rating),2) FROM rides WHERE rating IS NOT NULL AND requested_at >= CURDATE() - INTERVAL " . ($days - 1) . " DAY")->fetchColumn();

/* ---------------- Tables ---------------- */

$liveRides = $pdo->query("
    SELECT r.id, r.pickup_address, r.dropoff_address, r.fare, r.status, r.requested_at,
           p.name AS passenger, d.name AS driver
    FROM rides r
    JOIN passengers p ON p.id = r.passenger_id
    LEFT JOIN drivers d ON d.id = r.driver_id
    ORDER BY FIELD(r.status,'ongoing','accepted','requested') DESC, r.requested_at DESC
    LIMIT 7
")->fetchAll();

$pendingDrivers = $pdo->query("
    SELECT id, name, vehicle_model, plate_no, vehicle_type, created_at
    FROM drivers WHERE status='pending' ORDER BY created_at ASC LIMIT 5
")->fetchAll();

$topDrivers = $pdo->query("
    SELECT d.id, d.name, d.vehicle_model, d.rating, COUNT(r.id) AS trips, SUM(r.fare) AS earned
    FROM drivers d
    JOIN rides r ON r.driver_id = d.id AND r.status='completed'
                 AND r.completed_at >= CURDATE() - INTERVAL 6 DAY
    GROUP BY d.id ORDER BY earned DESC LIMIT 5
")->fetchAll();

admin_header('Dashboard', 'dashboard');
?>

<section class="kpis">
    <article class="kpi">
        <div class="kpi-top"><span class="kpi-label">Revenue today</span><span class="kpi-icon"><?= icon('money') ?></span></div>
        <div class="kpi-value"><?= e(money($kpi['rev_today'])) ?></div>
        <?= delta((float)$kpi['rev_today'], (float)$kpi['rev_yday']) ?>
    </article>
    <article class="kpi">
        <div class="kpi-top"><span class="kpi-label">Rides today</span><span class="kpi-icon"><?= icon('rides') ?></span></div>
        <div class="kpi-value"><?= (int)$kpi['rides_today'] ?></div>
        <?= delta((float)$kpi['rides_today'], (float)$kpi['rides_yday']) ?>
    </article>
    <article class="kpi">
        <div class="kpi-top"><span class="kpi-label">Live rides</span><span class="kpi-icon"><?= icon('clock') ?></span></div>
        <div class="kpi-value"><?= (int)$kpi['live_now'] ?></div>
        <span class="delta"><?= (int)$kpi['waiting'] ?> waiting for a driver</span>
    </article>
    <article class="kpi">
        <div class="kpi-top"><span class="kpi-label">Drivers online</span><span class="kpi-icon"><?= icon('driver') ?></span></div>
        <div class="kpi-value"><?= (int)$drv['online'] ?><span class="kpi-of"> / <?= (int)$drv['approved'] ?></span></div>
        <span class="delta"><?= $paxTotal ?> passengers · <?= $paxNew ?> new this week</span>
    </article>
</section>

<?php if ((int)$drv['pending'] > 0): ?>
    <a class="callout" href="<?= e(url('admin/drivers.php?status=pending')) ?>">
        <?= icon('alert') ?>
        <span><strong><?= (int)$drv['pending'] ?> driver application<?= $drv['pending'] > 1 ? 's' : '' ?></strong> waiting for review</span>
        <span class="callout-go">Review <?= icon('arrow', 16) ?></span>
    </a>
<?php endif; ?>

<section class="grid-main">
    <article class="card chart-card">
        <header class="card-head">
            <div>
                <h2 data-chart-title>Rides per day</h2>
                <p class="muted" data-chart-sub
                   data-rides="<?= $rides14 ?> rides in the last 14 days"
                   data-revenue="<?= e(money($rev14)) ?> earned in the last 14 days"><?= $rides14 ?> rides in the last 14 days</p>
            </div>
            <div class="segmented" role="tablist" aria-label="Chart metric">
                <button role="tab" aria-selected="true" data-show="rides">Rides</button>
                <button role="tab" aria-selected="false" data-show="revenue">Revenue</button>
            </div>
        </header>
        <div class="chart-wrap" data-chart="rides">
            <?= bar_chart($ridesSeries, fn($v) => number_format($v) . ' rides', 'Rides per day, last 14 days', fn($v) => number_format($v)) ?>
        </div>
        <div class="chart-wrap" data-chart="revenue" hidden>
            <?= bar_chart($revSeries, fn($v) => money($v), 'Revenue per day, last 14 days', fn($v) => number_format($v)) ?>
        </div>
        <div class="tooltip" role="tooltip" hidden></div>
    </article>

    <article class="card">
        <header class="card-head"><div><h2>Trip health</h2><p class="muted">Last 14 days</p></div></header>
        <div class="stat-pair">
            <div><span class="stat-big"><?= $completionRate ?>%</span><span class="muted">completion rate</span></div>
            <div><span class="stat-big"><?= $avgRating ? e($avgRating) : '—' ?><?= icon('star', 18) ?></span><span class="muted">average rating</span></div>
        </div>
        <h3 class="sub-head">Payment methods</h3>
        <?= $payShare ? share_bars($payShare) : '<p class="empty">No completed rides yet.</p>' ?>
    </article>
</section>

<section class="grid-main">
    <article class="card">
        <header class="card-head">
            <div><h2>Recent rides</h2><p class="muted">Live trips are listed first</p></div>
            <a class="link" href="<?= e(url('admin/rides.php')) ?>">View all <?= icon('arrow', 16) ?></a>
        </header>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Ride</th><th>Route</th><th>Passenger</th><th>Driver</th><th class="num">Fare</th><th>Status</th></tr></thead>
                <tbody>
                <?php foreach ($liveRides as $r): ?>
                    <tr>
                        <td><a class="mono link" href="<?= e(url('admin/ride.php?id=' . $r['id'])) ?>">#<?= (int)$r['id'] ?></a><small class="muted block ago"><?= e(time_ago($r['requested_at'])) ?></small></td>
                        <td class="route"><span><?= e($r['pickup_address']) ?></span><span class="muted">→ <?= e($r['dropoff_address']) ?></span></td>
                        <td><?= e($r['passenger']) ?></td>
                        <td><?= $r['driver'] ? e($r['driver']) : '<span class="muted">Unassigned</span>' ?></td>
                        <td class="num"><?= e(money($r['fare'])) ?></td>
                        <td><?= badge($r['status']) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$liveRides): ?><tr><td colspan="6" class="empty">No rides yet.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </article>

    <div class="stack">
        <article class="card">
            <header class="card-head"><div><h2>Pending approvals</h2><p class="muted">Oldest first</p></div></header>
            <?php if ($pendingDrivers): ?>
                <ul class="people">
                    <?php foreach ($pendingDrivers as $d): ?>
                        <li>
                            <span class="avatar"><?= e(initials($d['name'])) ?></span>
                            <div class="people-text">
                                <strong><?= e($d['name']) ?></strong>
                                <small class="muted"><?= e($d['vehicle_model']) ?> · <?= e($d['plate_no']) ?> · applied <?= e(time_ago($d['created_at'])) ?></small>
                            </div>
                            <form method="post" action="<?= e(url('admin/drivers.php')) ?>" class="inline-actions">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= (int)$d['id'] ?>">
                                <input type="hidden" name="back" value="dashboard">
                                <button class="icon-btn ok" name="action" value="approve" title="Approve <?= e($d['name']) ?>" aria-label="Approve <?= e($d['name']) ?>"><?= icon('check', 18) ?></button>
                                <button class="icon-btn no" name="action" value="reject" title="Reject <?= e($d['name']) ?>" aria-label="Reject <?= e($d['name']) ?>" data-confirm="Reject <?= e($d['name']) ?>'s application?"><?= icon('x', 18) ?></button>
                            </form>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p class="empty">All caught up — no applications waiting.</p>
            <?php endif; ?>
        </article>

        <article class="card">
            <header class="card-head"><div><h2>Top drivers</h2><p class="muted">By earnings, last 7 days</p></div></header>
            <?php if ($topDrivers): ?>
                <ol class="people ranked">
                    <?php foreach ($topDrivers as $d): ?>
                        <li>
                            <span class="avatar"><?= e(initials($d['name'])) ?></span>
                            <div class="people-text">
                                <strong><?= e($d['name']) ?></strong>
                                <small class="muted"><?= (int)$d['trips'] ?> trips · <?= icon('star', 12) ?> <?= e($d['rating'] ?? '—') ?></small>
                            </div>
                            <span class="people-val"><?= e(money($d['earned'])) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ol>
            <?php else: ?>
                <p class="empty">No completed trips this week.</p>
            <?php endif; ?>
        </article>
    </div>
</section>

<?php admin_footer(); ?>
