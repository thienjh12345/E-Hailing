<?php
/**
 * Passenger → Messages: chats with drivers (one per trip) and with Crab Support.
 *
 *  ?c=ID                 open a conversation
 *  ?ride=RIDE_ID         open/start the chat with that trip's driver
 *  ?support=1[&about=ID] open the Crab Support chat (optionally pre-filled about a trip)
 */
require __DIR__ . '/../includes/passenger_auth.php';
require __DIR__ . '/../includes/passenger_layout.php';
require __DIR__ . '/../includes/chat.php';
$p = require_passenger();
$me = ['role' => 'passenger', 'id' => (int)$p['id']];
$page = 'passenger/messages.php';

// Shortcuts from other pages → redirect to the conversation
if (isset($_GET['support'])) {
    $conv = chat_support_for($me['id']);
    $about = (int)($_GET['about'] ?? 0);
    redirect($page . '?c=' . $conv['id'] . ($about ? '&about=' . $about : '') . '#bottom');
}
if (isset($_GET['ride'])) {
    $conv = chat_for_ride((int)$_GET['ride'], $me);
    if (!$conv) {
        flash('error', 'You can message a driver once one has accepted your trip.');
        redirect('passenger/rides.php');
    }
    redirect($page . '?c=' . $conv['id'] . '#bottom');
}

$cid = (int)($_GET['c'] ?? $_POST['c'] ?? 0);
$conv = $cid ? chat_find($cid, $me) : null;
chat_handle_request($conv, $me, $page);     // AJAX send/poll, or no-JS send
if ($cid && !$conv) {
    flash('error', 'That conversation was not found.');
    redirect($page);
}

// "Get help with this trip" pre-fills the support message with the trip details
$prefill = '';
$about = (int)($_GET['about'] ?? 0);
if ($conv && $conv['type'] === 'support' && $about) {
    $s = db()->prepare('SELECT id, pickup_address, dropoff_address, requested_at FROM rides WHERE id = ? AND passenger_id = ?');
    $s->execute([$about, $me['id']]);
    if ($r = $s->fetch()) {
        $prefill = 'About my trip #' . $r['id'] . ' on ' . date('j M', strtotime($r['requested_at'])) . ' (' . $r['pickup_address'] . ' → ' . $r['dropoff_address'] . "): ";
    }
}

$inbox = chat_inbox($me);
$support = null;
foreach ($inbox as $i => $c) if ($c['type'] === 'support') { $support = $c; unset($inbox[$i]); }

// Recent trips with a driver, so the passenger can start a chat even before any messages exist
$s = db()->prepare("
    SELECT r.id, r.dropoff_address, r.requested_at, r.status, d.name AS driver_name, d.profile_photo AS driver_photo
    FROM rides r JOIN drivers d ON d.id = r.driver_id
    WHERE r.passenger_id = ? AND r.id NOT IN (SELECT ride_id FROM chat_conversations WHERE ride_id IS NOT NULL AND last_message_at IS NOT NULL)
    ORDER BY r.requested_at DESC LIMIT 4
");
$s->execute([$me['id']]);
$startable = $s->fetchAll();

passenger_header('Messages', 'messages');
?>

<div class="messenger<?= $conv ? ' has-conv' : '' ?>">
    <aside class="card inbox" aria-label="Conversations">
        <header class="inbox-head"><h2>Messages</h2></header>
        <div class="inbox-list">
            <?php if ($support): ?>
                <?= chat_inbox_item($support, $me, $page, $conv && (int)$conv['id'] === (int)$support['id']) ?>
            <?php else: ?>
                <a class="inbox-item<?= $conv && $conv['type'] === 'support' ? ' active' : '' ?>" href="<?= e(url($page . '?support=1')) ?>">
                    <span class="avatar support-avatar"><?= crab_logo(20) ?></span>
                    <span class="inbox-text"><span class="inbox-top"><strong>Crab Support</strong></span>
                        <span class="inbox-last muted">Questions about a trip or payment? Chat with us.</span></span>
                </a>
            <?php endif; ?>

            <h3 class="inbox-section">Your drivers</h3>
            <?php foreach ($inbox as $c): ?>
                <?= chat_inbox_item($c, $me, $page, $conv && (int)$conv['id'] === (int)$c['id']) ?>
            <?php endforeach; ?>
            <?php if (!$inbox): ?><p class="inbox-none muted">No driver chats yet.</p><?php endif; ?>

            <?php if ($startable): ?>
                <h3 class="inbox-section">Message a recent driver</h3>
                <?php foreach ($startable as $r): ?>
                    <a class="inbox-item start" href="<?= e(url($page . '?ride=' . $r['id'])) ?>">
                        <?= avatar($r['driver_name'], $r['driver_photo']) ?>
                        <span class="inbox-text"><span class="inbox-top"><strong><?= e($r['driver_name']) ?></strong><small class="muted"><?= e(time_ago($r['requested_at'])) ?></small></span>
                            <small class="muted inbox-context">Trip #<?= (int)$r['id'] ?> · <?= e($r['dropoff_address']) ?></small></span>
                        <span class="inbox-start"><?= icon('chat', 16) ?></span>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </aside>

    <div class="card chat-pane">
        <?php if ($conv): ?>
            <?= chat_render($conv, $me, $page, '', $prefill) ?>
        <?php else: ?>
            <div class="chat-placeholder">
                <span class="empty-icon"><?= icon('chat', 26) ?></span>
                <strong>Your messages</strong>
                <p class="muted">Pick a conversation, message one of your drivers, or ask Crab Support for help.</p>
                <a class="btn btn-primary" href="<?= e(url($page . '?support=1')) ?>"><?= icon('chat', 16) ?> Contact Crab Support</a>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php if ($conv): ?><script src="<?= e(asset('assets/js/chat.js')) ?>" defer></script><?php endif; ?>

<?php passenger_footer(); ?>
