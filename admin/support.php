<?php
/**
 * Admin → Support: customer-service inbox. Every admin can read and reply to every
 * passenger's support conversation, and mark it resolved / reopen it.
 *
 *  ?c=ID               open a conversation
 *  ?passenger=ID       open (or start) the support chat with a passenger
 *  ?status=open|resolved|all, ?q=search
 */
require __DIR__ . '/../includes/admin_auth.php';
require __DIR__ . '/../includes/admin_layout.php';
require __DIR__ . '/../includes/chat.php';
$admin = require_admin();
$me = ['role' => 'admin', 'id' => (int)$admin['id']];
$page = 'admin/support.php';

if (isset($_GET['passenger'])) {
    $pid = (int)$_GET['passenger'];
    $s = db()->prepare('SELECT 1 FROM passengers WHERE id = ?');
    $s->execute([$pid]);
    if (!$s->fetch()) redirect($page);
    redirect($page . '?c=' . chat_support_for($pid)['id'] . '#bottom');
}

$cid = (int)($_GET['c'] ?? $_POST['c'] ?? 0);
$conv = $cid ? chat_find($cid, $me) : null;

// Resolve / reopen
if ($conv && $_SERVER['REQUEST_METHOD'] === 'POST' && in_array($_POST['action'] ?? '', ['resolve', 'reopen'], true)) {
    verify_csrf();
    $new = $_POST['action'] === 'resolve' ? 'resolved' : 'open';
    db()->prepare('UPDATE chat_conversations SET status = ? WHERE id = ?')->execute([$new, $conv['id']]);
    flash('success', $new === 'resolved' ? 'Marked as resolved. It will reopen automatically if the passenger writes again.' : 'Conversation reopened.');
    redirect($page . '?c=' . $conv['id']);
}

chat_handle_request($conv, $me, $page);
if ($cid && !$conv) {
    flash('error', 'That conversation was not found.');
    redirect($page);
}

$filters = ['open' => 'Open', 'resolved' => 'Resolved', 'all' => 'All'];
$status = $_GET['status'] ?? 'open';
if (!isset($filters[$status])) $status = 'open';
$q = trim((string)($_GET['q'] ?? ''));
$inbox = chat_inbox($me, $status, $q);
$counts = db()->query("SELECT status, COUNT(*) FROM chat_conversations WHERE type = 'support' AND last_message_at IS NOT NULL GROUP BY status")->fetchAll(PDO::FETCH_KEY_PAIR);
$counts['all'] = array_sum($counts);

// Passenger context for the open conversation
$recent = [];
if ($conv) {
    $s = db()->prepare("SELECT id, pickup_address, dropoff_address, fare, status, requested_at FROM rides WHERE passenger_id = ? ORDER BY requested_at DESC LIMIT 3");
    $s->execute([$conv['passenger_id']]);
    $recent = $s->fetchAll();
}

$headerExtra = '';
if ($conv) {
    $headerExtra = '<div class="chat-actions">'
        . ($conv['status'] === 'resolved' ? '<span class="badge badge-completed">Resolved</span>' : '<span class="badge badge-pending">Open</span>')
        . '<a class="btn btn-sm btn-ghost" href="' . e(url('admin/passenger.php?id=' . $conv['passenger_id'])) . '">Profile</a>'
        . '<form method="post" action="' . e(url($page . '?c=' . $conv['id'])) . '">' . csrf_field()
        . '<input type="hidden" name="c" value="' . (int)$conv['id'] . '">'
        . ($conv['status'] === 'open'
            ? '<button class="btn btn-sm btn-outline" name="action" value="resolve">' . icon('check', 15) . ' Resolve</button>'
            : '<button class="btn btn-sm btn-ghost" name="action" value="reopen">Reopen</button>')
        . '</form></div>';
}

admin_header('Support', 'support');
?>

<div class="messenger<?= $conv ? ' has-conv' : '' ?>">
    <aside class="card inbox" aria-label="Support conversations">
        <header class="inbox-head">
            <h2>Customer support</h2>
            <nav class="tabs small-tabs" aria-label="Filter">
                <?php foreach ($filters as $key => $label): ?>
                    <a class="tab<?= $status === $key ? ' active' : '' ?>" href="<?= e(url($page) . query_with(['status' => $key === 'open' ? null : $key, 'c' => $conv['id'] ?? null])) ?>"><?= e($label) ?> <span class="tab-count"><?= (int)($counts[$key] ?? 0) ?></span></a>
                <?php endforeach; ?>
            </nav>
            <form class="search" method="get">
                <?php if ($status !== 'open'): ?><input type="hidden" name="status" value="<?= e($status) ?>"><?php endif; ?>
                <?= icon('search', 16) ?><input type="search" name="q" value="<?= e($q) ?>" placeholder="Search passenger…" aria-label="Search passengers">
            </form>
        </header>
        <div class="inbox-list">
            <?php foreach ($inbox as $c): ?>
                <?= chat_inbox_item($c, $me, $page, $conv && (int)$conv['id'] === (int)$c['id']) ?>
            <?php endforeach; ?>
            <?php if (!$inbox): ?><p class="inbox-none muted"><?= $q !== '' ? 'No conversations match “' . e($q) . '”.' : ($status === 'open' ? 'No open conversations — all caught up!' : 'Nothing here yet.') ?></p><?php endif; ?>
        </div>
    </aside>

    <div class="card chat-pane">
        <?php if ($conv): ?>
            <?= chat_render($conv, $me, $page, $headerExtra) ?>
            <?php if ($recent): ?>
                <details class="chat-context">
                    <summary>Recent trips by <?= e($conv['passenger_name']) ?></summary>
                    <ul>
                        <?php foreach ($recent as $r): ?>
                            <li><a class="link" href="<?= e(url('admin/ride.php?id=' . $r['id'])) ?>">#<?= (int)$r['id'] ?></a>
                                <?= e(fmt_datetime($r['requested_at'], 'j M')) ?> · <?= e($r['pickup_address']) ?> → <?= e($r['dropoff_address']) ?> · <?= e(money($r['fare'])) ?> <?= badge($r['status']) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </details>
            <?php endif; ?>
        <?php else: ?>
            <div class="chat-placeholder">
                <span class="empty-icon"><?= icon('chat', 26) ?></span>
                <strong>Customer support inbox</strong>
                <p class="muted">Passengers contact Crab Support from their Messages page. Pick a conversation to reply — every admin can answer.</p>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php if ($conv): ?><script src="<?= e(asset('assets/js/chat.js')) ?>" defer></script><?php endif; ?>

<?php admin_footer(); ?>
