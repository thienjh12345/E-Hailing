<?php
require __DIR__ . '/../includes/admin_auth.php';
require __DIR__ . '/../includes/admin_layout.php';
require_admin();

$pdo = db();

$statuses = ['all' => 'All', 'live' => 'Live', 'completed' => 'Completed', 'cancelled' => 'Cancelled'];
$status = $_GET['status'] ?? 'all';
if (!isset($statuses[$status])) $status = 'all';
$q    = trim((string)($_GET['q'] ?? ''));
$from = (string)($_GET['from'] ?? '');
$to   = (string)($_GET['to'] ?? '');
$passengerId = (int)($_GET['passenger'] ?? 0);

$validDate = fn($d) => (bool)preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) && strtotime($d) !== false;

$where = [];
$params = [];
if ($status === 'live') {
    $where[] = "r.status IN ('requested','accepted','ongoing')";
} elseif ($status !== 'all') {
    $where[] = 'r.status = ?'; $params[] = $status;
}
if ($q !== '') {
    $like = "%$q%";
    $where[] = '(p.name LIKE ? OR d.name LIKE ? OR r.pickup_address LIKE ? OR r.dropoff_address LIKE ? OR r.id = ?)';
    array_push($params, $like, $like, $like, $like, ctype_digit(ltrim($q, '#')) ? (int)ltrim($q, '#') : 0);
}
if ($from !== '' && $validDate($from)) { $where[] = 'r.requested_at >= ?'; $params[] = $from . ' 00:00:00'; }
if ($to   !== '' && $validDate($to))   { $where[] = 'r.requested_at <= ?'; $params[] = $to . ' 23:59:59'; }
if ($passengerId > 0) { $where[] = 'r.passenger_id = ?'; $params[] = $passengerId; }
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$base = "FROM rides r JOIN passengers p ON p.id = r.passenger_id LEFT JOIN drivers d ON d.id = r.driver_id $whereSql";

$sum = $pdo->prepare("SELECT COUNT(*) AS n, COALESCE(SUM(CASE WHEN r.status='completed' THEN r.fare END),0) AS revenue $base");
$sum->execute($params);
$summary = $sum->fetch();
[$page, $perPage, $offset, $pages] = paginate((int)$summary['n'], 20);

$stmt = $pdo->prepare("
    SELECT r.*, p.name AS passenger, d.name AS driver, d.plate_no
    $base
    ORDER BY r.requested_at DESC
    LIMIT $perPage OFFSET $offset
");
$stmt->execute($params);
$rides = $stmt->fetchAll();

$passengerName = null;
if ($passengerId > 0) {
    $s = $pdo->prepare('SELECT name FROM passengers WHERE id = ?');
    $s->execute([$passengerId]);
    $passengerName = $s->fetchColumn() ?: null;
}

$payLabels = ['cash' => 'Cash', 'card' => 'Card', 'wallet' => 'Wallet'];

admin_header('Rides', 'rides');
?>

<div class="toolbar">
    <nav class="tabs" aria-label="Filter by status">
        <?php foreach ($statuses as $key => $label): ?>
            <a href="<?= e(query_with(['status' => $key === 'all' ? null : $key, 'page' => null])) ?>"
               class="tab<?= $status === $key ? ' active' : '' ?>"><?= e($label) ?></a>
        <?php endforeach; ?>
    </nav>
    <form class="filters" method="get">
        <?php if ($status !== 'all'): ?><input type="hidden" name="status" value="<?= e($status) ?>"><?php endif; ?>
        <?php if ($passengerId): ?><input type="hidden" name="passenger" value="<?= $passengerId ?>"><?php endif; ?>
        <label class="date-field"><span>From</span><input type="date" name="from" value="<?= e($from) ?>"></label>
        <label class="date-field"><span>To</span><input type="date" name="to" value="<?= e($to) ?>"></label>
        <div class="search">
            <?= icon('search', 18) ?>
            <input type="search" name="q" value="<?= e($q) ?>" placeholder="Ride #, name, place…" aria-label="Search rides">
        </div>
        <button class="btn btn-outline btn-sm" type="submit">Apply</button>
    </form>
</div>

<?php if ($passengerName || $from || $to || $q): ?>
    <div class="filter-note">
        Showing <strong><?= (int)$summary['n'] ?></strong> rides
        <?= $passengerName ? 'for <strong>' . e($passengerName) . '</strong>' : '' ?>
        · <?= e(money($summary['revenue'])) ?> completed revenue
        <a class="link" href="<?= e(url('admin/rides.php')) ?>">Clear filters</a>
    </div>
<?php endif; ?>

<article class="card flush">
    <div class="table-wrap">
        <table class="table">
            <thead>
            <tr><th>Ride</th><th>Requested</th><th>Route</th><th>Passenger</th><th>Driver</th><th class="num">Distance</th><th class="num">Fare</th><th>Payment</th><th>Status</th></tr>
            </thead>
            <tbody>
            <?php foreach ($rides as $r): ?>
                <tr class="clickable" data-href="<?= e(url('admin/ride.php?id=' . $r['id'])) ?>">
                    <td><a class="mono link" href="<?= e(url('admin/ride.php?id=' . $r['id'])) ?>">#<?= (int)$r['id'] ?></a></td>
                    <td><?= e(fmt_datetime($r['requested_at'], 'd M, h:i A')) ?></td>
                    <td class="route"><span><?= e($r['pickup_address']) ?></span><span class="muted">→ <?= e($r['dropoff_address']) ?></span></td>
                    <td><?= e($r['passenger']) ?></td>
                    <td><?= $r['driver'] ? e($r['driver']) . '<small class="muted block">' . e($r['plate_no']) . '</small>' : '<span class="muted">Unassigned</span>' ?></td>
                    <td class="num"><?= e(number_format($r['distance_km'], 1)) ?> km</td>
                    <td class="num"><?= e(money($r['fare'])) ?></td>
                    <td><?= e($payLabels[$r['payment_method']] ?? $r['payment_method']) ?></td>
                    <td><?= badge($r['status']) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$rides): ?>
                <tr><td colspan="9" class="empty">No rides match these filters.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</article>
<?= render_pagination($page, $pages) ?>

<?php admin_footer(); ?>
