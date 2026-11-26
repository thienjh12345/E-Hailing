<?php
/**
 * Driver → Messages: chats with passengers (one per trip).
 *
 *  ?c=ID           open a conversation
 *  ?ride=RIDE_ID   open/start the chat for one of your trips
 */
require __DIR__ . '/../includes/driver_auth.php';
require __DIR__ . '/../includes/driver_layout.php';
require __DIR__ . '/../includes/chat.php';
$d = require_driver();
$me = ['role' => 'driver', 'id' => (int)$d['id']];
$page = 'driver/messages.php';

if (isset($_GET['ride'])) {
    $conv = chat_for_ride((int)$_GET['ride'], $me);
    if (!$conv) {
        flash('error', 'You can only message passengers on your own trips.');
        redirect($page);
    }
    redirect($page . '?c=' . $conv['id'] . '#bottom');
}

$cid = (int)($_GET['c'] ?? $_POST['c'] ?? 0);
$conv = $cid ? chat_find($cid, $me) : null;
chat_handle_request($conv, $me, $page);
if ($cid && !$conv) {
    flash('error', 'That conversation was not found.');
    redirect($page);
}

$inbox = chat_inbox($me);

// Live + recent trips, so the driver can message a passenger before anyone has written
$s = db()->prepare("
    SELECT r.id, r.pickup_address, r.requested_at, r.status, p.name AS passenger_name, p.profile_photo AS passenger_photo
    FROM rides r JOIN passengers p ON p.id = r.passenger_id
    WHERE r.driver_id = ? AND r.id NOT IN (SELECT ride_id FROM chat_conversations WHERE ride_id IS NOT NULL AND last_message_at IS NOT NULL)
    ORDER BY FIELD(r.status, 'ongoing', 'accepted') DESC, r.requested_at DESC LIMIT 4
");
$s->execute([$me['id']]);
$startable = $s->fetchAll();

driver_header('Messages', 'messages');
?>

<div class="messenger<?= $conv ? ' has-conv' : '' ?>">
    <aside class="card inbox" aria-label="Conversations">
        <header class="inbox-head"><h2>Messages</h2></header>
        <div class="inbox-list">
            <?php foreach ($inbox as $c): ?>
                <?= chat_inbox_item($c, $me, $page, $conv && (int)$conv['id'] === (int)$c['id']) ?>
            <?php endforeach; ?>
            <?php if (!$inbox): ?><p class="inbox-none muted">No conversations yet.</p><?php endif; ?>

            <?php if ($startable): ?>
                <h3 class="inbox-section">Message a passenger</h3>
                <?php foreach ($startable as $r): ?>
                    <a class="inbox-item start" href="<?= e(url($page . '?ride=' . $r['id'])) ?>">
                        <?= avatar($r['passenger_name'], $r['passenger_photo']) ?>
                        <span class="inbox-text"><span class="inbox-top"><strong><?= e($r['passenger_name']) ?></strong><?= in_array($r['status'], ['accepted', 'ongoing'], true) ? badge($r['status']) : '<small class="muted">' . e(time_ago($r['requested_at'])) . '</small>' ?></span>
                            <small class="muted inbox-context">Trip #<?= (int)$r['id'] ?> · from <?= e($r['pickup_address']) ?></small></span>
                        <span class="inbox-start"><?= icon('chat', 16) ?></span>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </aside>

    <div class="card chat-pane">
        <?php if ($conv): ?>
            <?= chat_render($conv, $me, $page) ?>
        <?php else: ?>
            <div class="chat-placeholder">
                <span class="empty-icon"><?= icon('chat', 26) ?></span>
                <strong>Your messages</strong>
                <p class="muted">Chat with your passengers — let them know you've arrived, or sort out a lost item after a trip.</p>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php if ($conv): ?><script src="<?= e(asset('assets/js/chat.js')) ?>" defer></script><?php endif; ?>

<?php driver_footer(); ?>
