<?php
/**
 * Admin → Payments: every trip payment, with totals by method and filters.
 * Refunds are done from the trip page (admin/ride.php).
 */
require __DIR__ . '/../includes/admin_auth.php';
require __DIR__ . '/../includes/admin_layout.php';
require __DIR__ . '/../includes/payments.php';
require_admin();
$pdo = db();

$statuses = ['all' => 'All', 'paid' => 'Paid', 'pending' => 'Pending', 'refunded' => 'Refunded', 'cancelled' => 'Not charged', 'failed' => 'Failed'];
$status = $_GET['status'] ?? 'all';
if (!isset($statuses[$status])) $status = 'all';
$method = $_GET['method'] ?? '';
if (!isset(PAYMENT_LABELS[$method])) $method = '';
$q = trim((string)($_GET['q'] ?? ''));

$where = [];
$params = [];
if ($status !== 'all') { $where[] = 'pay.status = ?'; $params[] = $status; }
if ($method !== '')    { $where[] = 'pay.method = ?'; $params[] = $method; }
if ($q !== '') {
    $where[] = '(pay.reference LIKE ? OR p.name LIKE ? OR p.email LIKE ? OR pay.ride_id = ?)';
    array_push($params, "%$q%", "%$q%", "%$q%", ctype_digit(ltrim($q, '#')) ? (int)ltrim($q, '#') : 0);
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
$base = "FROM payments pay JOIN passengers p ON p.id = pay.passenger_id JOIN rides r ON r.id = pay.ride_id
         LEFT JOIN drivers d ON d.id = r.driver_id LEFT JOIN payment_cards c ON c.id = pay.card_id $whereSql";

$count = $pdo->prepare("SELECT COUNT(*) $base");
$count->execute($params);
[$page, $perPage, $offset, $pages] = paginate((int)$count->fetchColumn(), 20);
$s = $pdo->prepare("SELECT pay.*, p.name AS passenger, d.name AS driver, c.brand, c.last4, r.pickup_address, r.dropoff_address
                    $base ORDER BY COALESCE(pay.paid_at, pay.created_at) DESC LIMIT $perPage OFFSET $offset");
$s->execute($params);
$rows = $s->fetchAll();

// Totals (paid only)
$tot = $pdo->query("
    SELECT
      COALESCE(SUM(CASE WHEN status = 'paid' AND paid_at >= CURDATE() THEN amount END), 0) AS today,
      COALESCE(SUM(CASE WHEN status = 'paid' AND paid_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01') THEN amount END), 0) AS month,
      COALESCE(SUM(CASE WHEN status = 'refunded' THEN amount END), 0) AS refunded,
      SUM(status = 'pending') AS pending
    FROM payments")->fetch();
$byMethod = $pdo->query("SELECT method, COUNT(*) AS n, SUM(amount) AS amt FROM payments
                         WHERE status = 'paid' AND paid_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01') GROUP BY method")->fetchAll(PDO::FETCH_UNIQUE);
$walletFloat = (float)$pdo->query('SELECT COALESCE(SUM(wallet_balance), 0) FROM passengers')->fetchColumn();

admin_header('Payments', 'payments');
?>

<section class="kpis">
    <article class="kpi">
        <div class="kpi-top"><span class="kpi-label">Collected today</span><span class="kpi-icon"><?= icon('money') ?></span></div>
        <div class="kpi-value"><?= e(money($tot['today'])) ?></div>
        <span class="delta"><?= (int)$tot['pending'] ?> payment<?= (int)$tot['pending'] === 1 ? '' : 's' ?> pending on live trips</span>
    </article>
    <article class="kpi">
        <div class="kpi-top"><span class="kpi-label">This month</span><span class="kpi-icon"><?= icon('receipt') ?></span></div>
        <div class="kpi-value"><?= e(money($tot['month'])) ?></div>
        <span class="delta"><?= e(date('F Y')) ?></span>
    </article>
    <article class="kpi">
        <div class="kpi-top"><span class="kpi-label">Refunded</span><span class="kpi-icon"><?= icon('arrow') ?></span></div>
        <div class="kpi-value"><?= e(money($tot['refunded'])) ?></div>
        <span class="delta">to CrabPay wallets, all time</span>
    </article>
    <article class="kpi">
        <div class="kpi-top"><span class="kpi-label">CrabPay balances</span><span class="kpi-icon"><?= icon('wallet') ?></span></div>
        <div class="kpi-value"><?= e(money($walletFloat)) ?></div>
        <span class="delta">held in passenger wallets</span>
    </article>
</section>

<article class="card">
    <header class="card-head"><div><h2>Paid this month by method</h2></div></header>
    <div class="method-split">
        <?php foreach (PAYMENT_LABELS as $m => $label): $row = $byMethod[$m] ?? ['n' => 0, 'amt' => 0]; ?>
            <div><span class="muted small"><?= e($label) ?></span><strong><?= e(money($row['amt'])) ?></strong><small class="muted"><?= (int)$row['n'] ?> trips</small></div>
        <?php endforeach; ?>
    </div>
</article>

<div class="toolbar">
    <nav class="tabs" aria-label="Filter by status">
        <?php foreach ($statuses as $key => $label): ?>
            <a class="tab<?= $status === $key ? ' active' : '' ?>" href="<?= e(query_with(['status' => $key === 'all' ? null : $key, 'page' => null])) ?>"><?= e($label) ?></a>
        <?php endforeach; ?>
    </nav>
    <form class="filters" method="get">
        <?php if ($status !== 'all'): ?><input type="hidden" name="status" value="<?= e($status) ?>"><?php endif; ?>
        <select name="method" aria-label="Payment method" onchange="this.form.submit()" class="select-sm">
            <option value="">All methods</option>
            <?php foreach (PAYMENT_LABELS as $m => $label): ?><option value="<?= e($m) ?>"<?= $method === $m ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
        </select>
        <div class="search"><?= icon('search', 18) ?><input type="search" name="q" value="<?= e($q) ?>" placeholder="Receipt, trip #, passenger…" aria-label="Search payments"></div>
    </form>
</div>

<article class="card flush">
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Receipt</th><th>Date</th><th>Passenger</th><th>Driver</th><th>Method</th><th class="num">Amount</th><th>Status</th></tr></thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
                <tr class="clickable" data-href="<?= e(url('admin/ride.php?id=' . $r['ride_id'])) ?>">
                    <td><a class="mono link" href="<?= e(url('admin/ride.php?id=' . $r['ride_id'])) ?>"><?= e($r['reference']) ?></a><small class="muted block">Trip #<?= (int)$r['ride_id'] ?></small></td>
                    <td class="ago"><?= e(fmt_datetime($r['paid_at'] ?? $r['created_at'], 'j M, g:i A')) ?></td>
                    <td><?= e($r['passenger']) ?></td>
                    <td><?= $r['driver'] ? e($r['driver']) : '<span class="muted">—</span>' ?></td>
                    <td><?= e(payment_method_label($r)) ?><?php if ($r['note']): ?><small class="muted block" title="<?= e($r['note']) ?>">Note</small><?php endif; ?></td>
                    <td class="num"><?= e(money($r['amount'])) ?></td>
                    <td><?= payment_badge($r) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$rows): ?><tr><td colspan="7" class="empty">No payments match these filters.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</article>
<?= render_pagination($page, $pages) ?>

<?php admin_footer(); ?>
