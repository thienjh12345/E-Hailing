<?php
require __DIR__ . '/../includes/admin_auth.php';
require __DIR__ . '/../includes/admin_layout.php';
require_admin();

$pdo = db();

/* ---------------- Actions ---------------- */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $id     = (int)($_POST['id'] ?? 0);
    $action = (string)($_POST['action'] ?? '');
    $map    = ['suspend' => ['active', 'suspended', 'suspended'], 'activate' => ['suspended', 'active', 'reactivated']];

    $stmt = $pdo->prepare('SELECT name, status FROM passengers WHERE id = ?');
    $stmt->execute([$id]);
    $p = $stmt->fetch();

    if (!$p || !isset($map[$action])) {
        flash('error', 'That passenger or action was not found.');
    } elseif ($p['status'] !== $map[$action][0]) {
        flash('error', "{$p['name']} is already {$p['status']}.");
    } else {
        // Optional reason the passenger sees in their portal (kept only while suspended).
        $note = mb_substr(trim((string)($_POST['note'] ?? '')), 0, 255);
        $note = $map[$action][1] === 'suspended' && $note !== '' ? $note : null;
        $pdo->prepare('UPDATE passengers SET status = ?, status_note = ? WHERE id = ?')->execute([$map[$action][1], $note, $id]);
        flash('success', "{$p['name']} has been {$map[$action][2]}.");
    }
    if (($_POST['back'] ?? '') === 'passenger') redirect('admin/passenger.php?id=' . $id);
    redirect('admin/passengers.php' . safe_qs($_POST['qs'] ?? ''));
}

/* ---------------- Listing ---------------- */

$statuses = ['all' => 'All', 'active' => 'Active', 'suspended' => 'Suspended'];
$status = $_GET['status'] ?? 'all';
if (!isset($statuses[$status])) $status = 'all';
$q = trim((string)($_GET['q'] ?? ''));

$where = [];
$params = [];
if ($status !== 'all') { $where[] = 'p.status = ?'; $params[] = $status; }
if ($q !== '') {
    $where[] = '(p.name LIKE ? OR p.email LIKE ? OR p.phone LIKE ?)';
    array_push($params, "%$q%", "%$q%", "%$q%");
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$counts = $pdo->query('SELECT status, COUNT(*) FROM passengers GROUP BY status')->fetchAll(PDO::FETCH_KEY_PAIR);
$counts['all'] = array_sum($counts);

$total = $pdo->prepare("SELECT COUNT(*) FROM passengers p $whereSql");
$total->execute($params);
[$page, $perPage, $offset, $pages] = paginate((int)$total->fetchColumn());

$stmt = $pdo->prepare("
    SELECT p.*,
           COUNT(r.id)                                              AS rides,
           COALESCE(SUM(CASE WHEN r.status='completed' THEN r.fare END), 0) AS spent,
           MAX(r.requested_at)                                      AS last_ride
    FROM passengers p
    LEFT JOIN rides r ON r.passenger_id = p.id
    $whereSql
    GROUP BY p.id
    ORDER BY p.created_at DESC
    LIMIT $perPage OFFSET $offset
");
$stmt->execute($params);
$passengers = $stmt->fetchAll();
$qs = query_with([]) === '?' ? '' : query_with([]);

admin_header('Passengers', 'passengers');
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
        <input type="search" name="q" value="<?= e($q) ?>" placeholder="Search name, email, phone…" aria-label="Search passengers">
    </form>
</div>

<article class="card flush">
    <div class="table-wrap">
        <table class="table">
            <thead>
            <tr><th>Passenger</th><th>Phone</th><th class="num">Rides</th><th class="num">Total spent</th><th>Last ride</th><th>Status</th><th class="actions-col">Actions</th></tr>
            </thead>
            <tbody>
            <?php foreach ($passengers as $p): ?>
                <tr class="clickable" data-href="<?= e(url('admin/passenger.php?id=' . $p['id'])) ?>">
                    <td>
                        <div class="person">
                            <?= avatar($p['name'], $p['profile_photo']) ?>
                            <div><a class="strong-link" href="<?= e(url('admin/passenger.php?id=' . $p['id'])) ?>"><?= e($p['name']) ?></a><small class="muted block"><?= e($p['email']) ?></small></div>
                        </div>
                    </td>
                    <td><?= e($p['phone']) ?></td>
                    <td class="num"><?= (int)$p['rides'] ?></td>
                    <td class="num"><?= e(money($p['spent'])) ?></td>
                    <td><?= e(time_ago($p['last_ride'])) ?></td>
                    <td><?= badge($p['status']) ?><small class="muted block">Joined <?= e(fmt_datetime($p['created_at'], 'd M Y')) ?></small></td>
                    <td class="actions-col">
                        <form method="post" class="row-actions">
                            <?= csrf_field() ?>
                            <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                            <input type="hidden" name="qs" value="<?= e($qs) ?>">
                            <a class="btn btn-sm btn-ghost" href="<?= e(url('admin/rides.php?passenger=' . $p['id'])) ?>">Rides</a>
                            <?php if ($p['status'] === 'active'): ?>
                                <button class="btn btn-sm btn-ghost" name="action" value="suspend" data-confirm="Suspend <?= e($p['name']) ?>? They won't be able to book rides.">Suspend</button>
                            <?php else: ?>
                                <button class="btn btn-sm btn-outline" name="action" value="activate">Reactivate</button>
                            <?php endif; ?>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$passengers): ?>
                <tr><td colspan="7" class="empty">No passengers match<?= $q !== '' ? ' “' . e($q) . '”' : ' this filter' ?>.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</article>
<?= render_pagination($page, $pages) ?>

<?php admin_footer(); ?>
