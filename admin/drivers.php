<?php
require __DIR__ . '/../includes/admin_auth.php';
require __DIR__ . '/../includes/admin_layout.php';
require_admin();

$pdo = db();

/* ---------------- Actions (POST → redirect → GET) ---------------- */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $id     = (int)($_POST['id'] ?? 0);
    $action = (string)($_POST['action'] ?? '');

    // action => [allowed current statuses, new status, success message]
    $transitions = [
        'approve'    => [['pending', 'rejected'], 'approved',  'approved'],
        'reject'     => [['pending'],             'rejected',  'rejected'],
        'suspend'    => [['approved'],            'suspended', 'suspended'],
        'reactivate' => [['suspended'],           'approved',  'reactivated'],
    ];

    $stmt = $pdo->prepare('SELECT name, status FROM drivers WHERE id = ?');
    $stmt->execute([$id]);
    $driver = $stmt->fetch();

    if (!$driver || !isset($transitions[$action])) {
        flash('error', 'That driver or action was not found.');
    } elseif (!in_array($driver['status'], $transitions[$action][0], true)) {
        flash('error', "{$driver['name']} is currently {$driver['status']}, so they can't be {$transitions[$action][2]}.");
    } else {
        [, $newStatus, $verb] = $transitions[$action];
        // Suspended/rejected drivers are forced offline.
        $pdo->prepare('UPDATE drivers SET status = ?, is_online = IF(? = "approved", is_online, 0) WHERE id = ?')
            ->execute([$newStatus, $newStatus, $id]);
        flash('success', "{$driver['name']} has been $verb.");
    }

    if (($_POST['back'] ?? '') === 'dashboard') redirect('admin/index.php');
    redirect('admin/drivers.php' . safe_qs($_POST['qs'] ?? ''));
}

/* ---------------- Listing ---------------- */

$statuses = ['all' => 'All', 'pending' => 'Pending', 'approved' => 'Approved', 'suspended' => 'Suspended', 'rejected' => 'Rejected'];
$status = $_GET['status'] ?? 'all';
if (!isset($statuses[$status])) $status = 'all';
$q = trim((string)($_GET['q'] ?? ''));

$where = [];
$params = [];
if ($status !== 'all') { $where[] = 'd.status = ?'; $params[] = $status; }
if ($q !== '') {
    $where[] = '(d.name LIKE ? OR d.email LIKE ? OR d.phone LIKE ? OR d.plate_no LIKE ?)';
    array_push($params, "%$q%", "%$q%", "%$q%", "%$q%");
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$counts = $pdo->query('SELECT status, COUNT(*) FROM drivers GROUP BY status')->fetchAll(PDO::FETCH_KEY_PAIR);
$counts['all'] = array_sum($counts);

$total = $pdo->prepare("SELECT COUNT(*) FROM drivers d $whereSql");
$total->execute($params);
[$page, $perPage, $offset, $pages] = paginate((int)$total->fetchColumn());

$stmt = $pdo->prepare("
    SELECT d.*,
           (SELECT COUNT(*) FROM rides r WHERE r.driver_id = d.id AND r.status = 'completed') AS trips,
           (SELECT COALESCE(SUM(fare),0) FROM rides r WHERE r.driver_id = d.id AND r.status = 'completed') AS earned
    FROM drivers d
    $whereSql
    ORDER BY FIELD(d.status, 'pending') DESC, d.created_at DESC
    LIMIT $perPage OFFSET $offset
");
$stmt->execute($params);
$drivers = $stmt->fetchAll();

$vehicleLabels = ['car' => 'Crab Car', 'premium' => 'Crab Premium', 'bike' => 'Crab Bike'];
$qs = query_with([]) === '?' ? '' : query_with([]);

admin_header('Drivers', 'drivers');
?>

<div class="toolbar">
    <nav class="tabs" aria-label="Filter by status">
        <?php foreach ($statuses as $key => $label): ?>
            <a href="<?= e(query_with(['status' => $key === 'all' ? null : $key, 'page' => null])) ?>"
               class="tab<?= $status === $key ? ' active' : '' ?>"><?= e($label) ?> <span class="tab-count"><?= (int)($counts[$key] ?? 0) ?></span></a>
        <?php endforeach; ?>
    </nav>
    <form class="search" method="get">
        <?php if ($status !== 'all'): ?><input type="hidden" name="status" value="<?= e($status) ?>"><?php endif; ?>
        <?= icon('search', 18) ?>
        <input type="search" name="q" value="<?= e($q) ?>" placeholder="Search name, phone, plate…" aria-label="Search drivers">
    </form>
</div>

<article class="card flush">
    <div class="table-wrap">
        <table class="table">
            <thead>
            <tr><th>Driver</th><th>Vehicle</th><th>Licence</th><th class="num">Trips</th><th class="num">Earned</th><th>Rating</th><th>Status</th><th class="actions-col">Actions</th></tr>
            </thead>
            <tbody>
            <?php foreach ($drivers as $d): ?>
                <tr>
                    <td>
                        <div class="person">
                            <span class="avatar"><?= e(initials($d['name'])) ?><?php if ($d['is_online'] && $d['status'] === 'approved'): ?><i class="online-dot" title="Online"></i><?php endif; ?></span>
                            <div><strong><?= e($d['name']) ?></strong><small class="muted block"><?= e($d['phone']) ?> · <?= e($d['email']) ?></small></div>
                        </div>
                    </td>
                    <td><?= e($d['vehicle_model']) ?><small class="muted block"><span class="plate"><?= e($d['plate_no']) ?></span> <?= e($vehicleLabels[$d['vehicle_type']] ?? $d['vehicle_type']) ?></small></td>
                    <td class="mono"><?= e($d['license_no']) ?></td>
                    <td class="num"><?= (int)$d['trips'] ?></td>
                    <td class="num"><?= e(money($d['earned'])) ?></td>
                    <td><?= $d['rating'] ? '<span class="rating">' . icon('star', 14) . ' ' . e($d['rating']) . '</span>' : '<span class="muted">—</span>' ?></td>
                    <td><?= badge($d['status']) ?><small class="muted block">Joined <?= e(fmt_datetime($d['created_at'], 'd M Y')) ?></small></td>
                    <td class="actions-col">
                        <form method="post" class="row-actions">
                            <?= csrf_field() ?>
                            <input type="hidden" name="id" value="<?= (int)$d['id'] ?>">
                            <input type="hidden" name="qs" value="<?= e($qs) ?>">
                            <?php if ($d['status'] === 'pending'): ?>
                                <button class="btn btn-sm btn-primary" name="action" value="approve">Approve</button>
                                <button class="btn btn-sm btn-ghost" name="action" value="reject" data-confirm="Reject <?= e($d['name']) ?>'s application?">Reject</button>
                            <?php elseif ($d['status'] === 'approved'): ?>
                                <button class="btn btn-sm btn-ghost" name="action" value="suspend" data-confirm="Suspend <?= e($d['name']) ?>? They will be taken offline immediately.">Suspend</button>
                            <?php elseif ($d['status'] === 'suspended'): ?>
                                <button class="btn btn-sm btn-outline" name="action" value="reactivate">Reactivate</button>
                            <?php elseif ($d['status'] === 'rejected'): ?>
                                <button class="btn btn-sm btn-outline" name="action" value="approve">Approve</button>
                            <?php endif; ?>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$drivers): ?>
                <tr><td colspan="8" class="empty">No drivers match<?= $q !== '' ? ' “' . e($q) . '”' : ' this filter' ?>.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</article>
<?= render_pagination($page, $pages) ?>

<?php admin_footer(); ?>
