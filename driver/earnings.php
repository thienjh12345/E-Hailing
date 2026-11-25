<?php
/**
 * Driver → Earnings: totals for a period (vs the period before), a daily/monthly chart,
 * cash vs cashless split, and a trip-by-trip breakdown.
 * Earnings = fares of completed trips.
 */
require __DIR__ . '/../includes/driver_auth.php';
require __DIR__ . '/../includes/driver_layout.php';
require __DIR__ . '/../includes/charts.php';
$d = require_driver();
$pdo = db();
$id = (int)$d['id'];

/* ---------- Period ---------- */
$periods = [
    '7d'  => ['label' => '7 days',    'days' => 7,  'unit' => 'day'],
    '30d' => ['label' => '30 days',   'days' => 30, 'unit' => 'day'],
    '12m' => ['label' => '12 months', 'days' => 0,  'unit' => 'month'],
];
$period = $_GET['period'] ?? '30d';
if (!isset($periods[$period])) $period = '30d';
$P = $periods[$period];

if ($P['unit'] === 'day') {
    $start     = date('Y-m-d', strtotime('-' . ($P['days'] - 1) . ' days'));
    $prevStart = date('Y-m-d', strtotime($start . ' -' . $P['days'] . ' days'));
    $prevLabel = 'previous ' . $P['label'];
} else {
    $start     = date('Y-m-01', strtotime('-11 months'));
    $prevStart = date('Y-m-01', strtotime($start . ' -12 months'));
    $prevLabel = 'previous 12 months';
}

/* ---------- Totals (this period vs the one before) ---------- */
$sum = $pdo->prepare("
    SELECT COUNT(*) AS trips, COALESCE(SUM(fare), 0) AS earned, COALESCE(SUM(distance_km), 0) AS km,
           COALESCE(SUM(CASE WHEN payment_method = 'cash' THEN fare END), 0) AS cash
    FROM rides WHERE driver_id = ? AND status = 'completed' AND completed_at >= ? AND completed_at < ?
");
$sum->execute([$id, $start, date('Y-m-d', strtotime('+1 day'))]);
$now = $sum->fetch();
$sum->execute([$id, $prevStart, $start]);
$prev = $sum->fetch();

$earned   = (float)$now['earned'];
$trips    = (int)$now['trips'];
$perTrip  = $trips ? $earned / $trips : 0;
$perKm    = (float)$now['km'] > 0 ? $earned / (float)$now['km'] : 0;
$cash     = (float)$now['cash'];
$cashless = $earned - $cash;

function change_chip(float $now, float $before, string $label, bool $isMoney = false): string
{
    if ($before <= 0) return $now > 0 ? '<span class="delta up">New this period</span>' : '<span class="delta">No earlier data</span>';
    if ($now <= 0) return '<span class="delta">No trips yet · ' . e($isMoney ? money($before) : (int)$before . ' trip' . ((int)$before === 1 ? '' : 's')) . ' in the ' . e($label) . '</span>';
    $pct = ($now - $before) / $before * 100;
    return '<span class="delta ' . ($pct >= 0 ? 'up' : 'down') . '">' . ($pct >= 0 ? '▲' : '▼') . ' ' . abs(round($pct)) . '% <span class="muted">vs ' . e($label) . '</span></span>';
}

/* ---------- Chart series ---------- */
$fmtKey = $P['unit'] === 'day' ? '%Y-%m-%d' : '%Y-%m';
$s = $pdo->prepare("
    SELECT DATE_FORMAT(completed_at, '$fmtKey') AS k, SUM(fare) AS earned, COUNT(*) AS trips
    FROM rides WHERE driver_id = ? AND status = 'completed' AND completed_at >= ?
    GROUP BY k
");
$s->execute([$id, $start]);
$byKey = array_column($s->fetchAll(), null, 'k');

$series = [];
if ($P['unit'] === 'day') {
    for ($i = $P['days'] - 1; $i >= 0; $i--) {
        $ts = strtotime("-$i day");
        $k = date('Y-m-d', $ts);
        $t = (int)($byKey[$k]['trips'] ?? 0);
        $series[] = ['label' => $i === 0 ? 'Today' : date('j M', $ts),
                     'full'  => date('l, j M', $ts) . " · $t trip" . ($t === 1 ? '' : 's'),
                     'value' => (float)($byKey[$k]['earned'] ?? 0)];
    }
} else {
    for ($i = 11; $i >= 0; $i--) {
        $ts = strtotime(date('Y-m-01') . " -$i month");
        $k = date('Y-m', $ts);
        $t = (int)($byKey[$k]['trips'] ?? 0);
        $series[] = ['label' => date('M', $ts), 'full' => date('F Y', $ts) . " · $t trip" . ($t === 1 ? '' : 's'),
                     'value' => (float)($byKey[$k]['earned'] ?? 0)];
    }
}
$best = $series ? array_reduce($series, fn($c, $p) => $p['value'] > ($c['value'] ?? -1) ? $p : $c) : null;

/* ---------- Payment split ---------- */
$s = $pdo->prepare("
    SELECT payment_method AS m, COUNT(*) AS n, SUM(fare) AS amt
    FROM rides WHERE driver_id = ? AND status = 'completed' AND completed_at >= ?
    GROUP BY payment_method ORDER BY amt DESC
");
$s->execute([$id, $start]);
$payLabels = ['cash' => 'Cash', 'card' => 'Card', 'wallet' => 'CrabPay wallet'];
$payShare = array_map(fn($r) => ['label' => $payLabels[$r['m']] ?? $r['m'], 'value' => (float)$r['amt'],
    'note' => $r['n'] . ' trip' . ($r['n'] == 1 ? '' : 's') . ' · ' . money($r['amt'])], $s->fetchAll());

/* ---------- Trip breakdown (paginated) ---------- */
[$page, $perPage, $offset, $pages] = paginate($trips, 10);
$s = $pdo->prepare("
    SELECT r.id, r.pickup_address, r.dropoff_address, r.distance_km, r.fare, r.payment_method, r.completed_at, r.rating,
           p.name AS passenger
    FROM rides r JOIN passengers p ON p.id = r.passenger_id
    WHERE r.driver_id = ? AND r.status = 'completed' AND r.completed_at >= ?
    ORDER BY r.completed_at DESC LIMIT $perPage OFFSET $offset
");
$s->execute([$id, $start]);
$rows = $s->fetchAll();

driver_header('Earnings', 'earnings');
?>

<div class="toolbar">
    <nav class="tabs" aria-label="Period">
        <?php foreach ($periods as $key => $p): ?>
            <a href="<?= e(query_with(['period' => $key === '30d' ? null : $key, 'page' => null])) ?>" class="tab<?= $period === $key ? ' active' : '' ?>"><?= e($p['label']) ?></a>
        <?php endforeach; ?>
    </nav>
    <span class="muted small"><?= e(date('j M Y', strtotime($start))) ?> – today</span>
</div>

<section class="kpis">
    <article class="kpi">
        <div class="kpi-top"><span class="kpi-label">Earnings · <?= e($P['label']) ?></span><span class="kpi-icon"><?= icon('money') ?></span></div>
        <div class="kpi-value"><?= e(money($earned)) ?></div>
        <?= change_chip($earned, (float)$prev['earned'], $prevLabel, true) ?>
    </article>
    <article class="kpi">
        <div class="kpi-top"><span class="kpi-label">Completed trips</span><span class="kpi-icon"><?= icon('rides') ?></span></div>
        <div class="kpi-value"><?= $trips ?></div>
        <?= change_chip($trips, (float)$prev['trips'], $prevLabel) ?>
    </article>
    <article class="kpi">
        <div class="kpi-top"><span class="kpi-label">Average per trip</span><span class="kpi-icon"><?= icon('flag') ?></span></div>
        <div class="kpi-value"><?= e(money($perTrip)) ?></div>
        <span class="delta"><?= e(money($perKm)) ?> per km · <?= e(number_format((float)$now['km'], 1)) ?> km driven</span>
    </article>
    <article class="kpi">
        <div class="kpi-top"><span class="kpi-label">Best <?= $P['unit'] === 'day' ? 'day' : 'month' ?></span><span class="kpi-icon"><?= icon('star') ?></span></div>
        <div class="kpi-value"><?= $best && $best['value'] > 0 ? e(money($best['value'])) : '—' ?></div>
        <span class="delta"><?= $best && $best['value'] > 0 ? e(explode(' · ', $best['full'])[0]) : 'No trips in this period' ?></span>
    </article>
</section>

<section class="grid-main">
    <article class="card chart-card">
        <header class="card-head">
            <div>
                <h2>Earnings per <?= $P['unit'] ?></h2>
                <p class="muted"><?= e(money($earned)) ?> from <?= $trips ?> trip<?= $trips === 1 ? '' : 's' ?> in the last <?= e($P['label']) ?></p>
            </div>
        </header>
        <?php if ($earned > 0): ?>
            <div class="chart-wrap">
                <?= bar_chart($series, fn($v) => money($v), 'Earnings per ' . $P['unit'] . ', last ' . $P['label'], fn($v) => number_format($v)) ?>
            </div>
            <div class="tooltip" role="tooltip" hidden></div>
        <?php else: ?>
            <div class="empty-state tall">
                <span class="empty-icon"><?= icon('money', 22) ?></span>
                <strong>Nothing earned in the last <?= e($P['label']) ?></strong>
                <p class="muted"><?= $period !== '12m' ? 'Pick a longer period above to see earlier earnings.' : 'Your earnings will show here once you complete trips.' ?></p>
            </div>
        <?php endif; ?>
    </article>

    <article class="card">
        <header class="card-head"><div><h2>How you were paid</h2><p class="muted">Last <?= e($P['label']) ?></p></div></header>
        <div class="stat-pair">
            <div><span class="stat-big"><?= e(money($cash)) ?></span><span class="muted">cash collected from passengers</span></div>
            <div><span class="stat-big"><?= e(money($cashless)) ?></span><span class="muted">card &amp; wallet, paid out by Crab</span></div>
        </div>
        <h3 class="sub-head">By payment method</h3>
        <?= $payShare ? share_bars($payShare) : '<p class="empty small">No payments in this period.</p>' ?>
    </article>
</section>

<article class="card">
    <header class="card-head">
        <div>
            <h2>Trip breakdown</h2>
            <p class="muted"><?= $trips ? $trips . ' completed trip' . ($trips === 1 ? '' : 's') . ' in the last ' . e($P['label']) . ', newest first' : 'Last ' . e($P['label']) ?></p>
        </div>
    </header>
    <?php if (!$rows): ?>
        <div class="empty-state">
            <span class="empty-icon"><?= icon('rides', 22) ?></span>
            <strong>No completed trips in the last <?= e($P['label']) ?></strong>
            <p class="muted"><?= $period !== '12m'
                ? 'Try a <a class="link" href="' . e(query_with(['period' => $period === '7d' ? null : '12m', 'page' => null])) . '">longer period</a>, or go online to start earning.'
                : 'Go online from your Home page to start receiving ride requests.' ?></p>
        </div>
    <?php else: ?>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Completed</th><th>Route</th><th>Passenger</th><th class="num">Distance</th><th>Payment</th><th>Rating</th><th class="num">Fare</th></tr></thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td class="ago"><?= e(fmt_datetime($r['completed_at'], 'd M, h:i A')) ?><small class="muted block mono">#<?= (int)$r['id'] ?></small></td>
                    <td class="route"><span><?= e($r['pickup_address']) ?></span><span class="muted">→ <?= e($r['dropoff_address']) ?></span></td>
                    <td><?= e($r['passenger']) ?></td>
                    <td class="num"><?= e(number_format($r['distance_km'], 1)) ?> km</td>
                    <td><?= e($payLabels[$r['payment_method']] ?? $r['payment_method']) ?></td>
                    <td><?= $r['rating'] ? '<span class="rating">' . icon('star', 14) . ' ' . (int)$r['rating'] . '</span>' : '<span class="muted">—</span>' ?></td>
                    <td class="num"><strong><?= e(money($r['fare'])) ?></strong></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</article>
<?= render_pagination($page, $pages) ?>

<?php driver_footer(); ?>
