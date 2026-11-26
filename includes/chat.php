<?php
/**
 * In-app chat — shared by the passenger, driver and admin portals.
 *
 *  - Ride chat:    passenger <-> driver, one conversation per ride (needs a driver on the ride).
 *  - Support chat: passenger <-> Crab Support, one ongoing conversation per passenger;
 *                  any admin can reply. Admins can mark it resolved; a new passenger
 *                  message re-opens it.
 *
 * Both sides can always reply — there's no limit on the number of messages.
 * Each message is capped at CHAT_MAX_LENGTH characters to keep the inbox readable.
 *
 * Who is "me" is passed in as a role + id: ['role' => 'passenger'|'driver'|'admin', 'id' => int].
 */

const CHAT_MAX_LENGTH = 2000;

/* ---------------------------------------------------------------------------
 * Finding / creating conversations (with access checks)
 * ------------------------------------------------------------------------- */

/** Base query: a conversation with the people involved (names + photos) and its ride. */
function chat_select_sql(): string
{
    return "
        SELECT c.*,
               p.name AS passenger_name, p.profile_photo AS passenger_photo, p.phone AS passenger_phone, p.email AS passenger_email,
               d.name AS driver_name, d.profile_photo AS driver_photo, d.vehicle_model, d.vehicle_color, d.plate_no,
               r.pickup_address, r.dropoff_address, r.status AS ride_status, r.requested_at AS ride_requested_at
        FROM chat_conversations c
        JOIN passengers p ON p.id = c.passenger_id
        LEFT JOIN drivers d ON d.id = c.driver_id
        LEFT JOIN rides r ON r.id = c.ride_id";
}

/** Can this user see/post in this conversation? */
function chat_can_access(array $conv, array $me): bool
{
    switch ($me['role']) {
        case 'passenger': return (int)$conv['passenger_id'] === (int)$me['id'];
        case 'driver':    return $conv['type'] === 'ride' && (int)$conv['driver_id'] === (int)$me['id'];
        case 'admin':     return $conv['type'] === 'support';
    }
    return false;
}

/** Load a conversation the user is allowed to see, or null. */
function chat_find(int $id, array $me): ?array
{
    $s = db()->prepare(chat_select_sql() . ' WHERE c.id = ?');
    $s->execute([$id]);
    $c = $s->fetch();
    return $c && chat_can_access($c, $me) ? $c : null;
}

/**
 * Open (or create) the chat for a ride. Only the ride's passenger or its assigned driver may.
 * Returns null if the ride has no driver yet or belongs to someone else.
 */
function chat_for_ride(int $rideId, array $me): ?array
{
    $s = db()->prepare('SELECT id, passenger_id, driver_id FROM rides WHERE id = ?');
    $s->execute([$rideId]);
    $ride = $s->fetch();
    if (!$ride || !$ride['driver_id']) return null;
    $mine = ($me['role'] === 'passenger' && (int)$ride['passenger_id'] === (int)$me['id'])
         || ($me['role'] === 'driver' && (int)$ride['driver_id'] === (int)$me['id']);
    if (!$mine) return null;

    // INSERT IGNORE + unique ride_id = safe even if both sides open the chat at the same moment.
    db()->prepare("INSERT IGNORE INTO chat_conversations (type, passenger_id, driver_id, ride_id) VALUES ('ride', ?, ?, ?)")
        ->execute([$ride['passenger_id'], $ride['driver_id'], $rideId]);
    $s = db()->prepare(chat_select_sql() . ' WHERE c.ride_id = ?');
    $s->execute([$rideId]);
    $c = $s->fetch();
    return $c && chat_can_access($c, $me) ? $c : null;
}

/** The passenger's support conversation (created on first use). */
function chat_support_for(int $passengerId): array
{
    db()->prepare("INSERT IGNORE INTO chat_conversations (type, passenger_id) VALUES ('support', ?)")->execute([$passengerId]);
    $s = db()->prepare(chat_select_sql() . " WHERE c.type = 'support' AND c.passenger_id = ?");
    $s->execute([$passengerId]);
    return $s->fetch();
}

/* ---------------------------------------------------------------------------
 * Messages
 * ------------------------------------------------------------------------- */

/** Messages after $afterId (0 = from the start), oldest first. */
function chat_messages(int $convId, int $afterId = 0, int $limit = 500): array
{
    $s = db()->prepare("
        SELECT m.id, m.sender_role, m.sender_id, m.body, m.created_at, a.name AS admin_name
        FROM chat_messages m LEFT JOIN admins a ON m.sender_role = 'admin' AND a.id = m.sender_id
        WHERE m.conversation_id = ? AND m.id > ?
        ORDER BY m.id ASC LIMIT $limit
    ");
    $s->execute([$convId, $afterId]);
    return $s->fetchAll();
}

/** Clean up and check a message. Returns [body, error]. */
function chat_clean_body(string $raw): array
{
    $body = trim(str_replace(["\r\n", "\r"], "\n", $raw));
    $body = preg_replace("/\n{3,}/", "\n\n", $body);           // at most one blank line in a row
    if ($body === '') return ['', 'Type a message first.'];
    if (mb_strlen($body) > CHAT_MAX_LENGTH) return ['', 'Messages can be up to ' . CHAT_MAX_LENGTH . ' characters. Split it into two messages.'];
    return [$body, null];
}

/**
 * Send a message. Marks everything up to it as read for the sender.
 * A passenger writing to support re-opens a resolved conversation.
 * @return array the stored message row
 */
function chat_send(array $conv, array $me, string $body): array
{
    $pdo = db();
    $pdo->prepare('INSERT INTO chat_messages (conversation_id, sender_role, sender_id, body) VALUES (?, ?, ?, ?)')
        ->execute([$conv['id'], $me['role'], $me['id'], $body]);
    $msgId = (int)$pdo->lastInsertId();

    $col = $me['role'] . '_last_read';                    // whitelisted by role values
    $reopen = $conv['type'] === 'support' && $me['role'] === 'passenger' ? ", status = 'open'" : '';
    $pdo->prepare("UPDATE chat_conversations SET last_message_at = NOW(), $col = ? $reopen WHERE id = ?")
        ->execute([$msgId, $conv['id']]);

    return chat_messages((int)$conv['id'], $msgId - 1, 1)[0];
}

/** Mark messages up to $lastId as read by me (never moves backwards). */
function chat_mark_read(array $conv, array $me, int $lastId): void
{
    if (!in_array($me['role'], ['passenger', 'driver', 'admin'], true) || $lastId <= 0) return;
    $col = $me['role'] . '_last_read';
    db()->prepare("UPDATE chat_conversations SET $col = GREATEST($col, ?) WHERE id = ?")->execute([$lastId, $conv['id']]);
}

/** The other side's read pointer — used to show "Seen" under my last message. */
function chat_other_read(array $conv, array $me): int
{
    if ($me['role'] === 'passenger') return (int)($conv['type'] === 'support' ? $conv['admin_last_read'] : $conv['driver_last_read']);
    return (int)$conv['passenger_last_read'];
}

/* ---------------------------------------------------------------------------
 * Inboxes and unread counts
 * ------------------------------------------------------------------------- */

/** Total unread messages for the sidebar badge. */
function chat_unread_total(array $me): int
{
    $sql = [
        'passenger' => "SELECT COUNT(*) FROM chat_messages m JOIN chat_conversations c ON c.id = m.conversation_id
                        WHERE c.passenger_id = ? AND m.sender_role <> 'passenger' AND m.id > c.passenger_last_read",
        'driver'    => "SELECT COUNT(*) FROM chat_messages m JOIN chat_conversations c ON c.id = m.conversation_id
                        WHERE c.type = 'ride' AND c.driver_id = ? AND m.sender_role <> 'driver' AND m.id > c.driver_last_read",
        'admin'     => "SELECT COUNT(*) FROM chat_messages m JOIN chat_conversations c ON c.id = m.conversation_id
                        WHERE c.type = 'support' AND m.sender_role = 'passenger' AND m.id > c.admin_last_read",
    ][$me['role']];
    $s = db()->prepare($sql);
    $s->execute($me['role'] === 'admin' ? [] : [$me['id']]);   // admins share one support inbox
    return (int)$s->fetchColumn();
}

/**
 * Conversation list for an inbox, newest activity first, with last message + unread count.
 * $filter (admin only): 'open' | 'resolved' | 'all'
 */
function chat_inbox(array $me, string $filter = 'all', string $search = ''): array
{
    $role = $me['role'];
    $readCol = "c.{$role}_last_read";
    $where = [];
    $params = [];
    if ($role === 'passenger') { $where[] = 'c.passenger_id = ?'; $params[] = $me['id']; }
    if ($role === 'driver')    { $where[] = "c.type = 'ride' AND c.driver_id = ?"; $params[] = $me['id']; }
    if ($role === 'admin') {
        $where[] = "c.type = 'support'";
        if (in_array($filter, ['open', 'resolved'], true)) { $where[] = 'c.status = ?'; $params[] = $filter; }
    }
    if ($search !== '') {
        $where[] = '(p.name LIKE ? OR p.email LIKE ? OR p.phone LIKE ?)';
        array_push($params, "%$search%", "%$search%", "%$search%");
    }
    // Only list conversations that actually have messages (opening a chat doesn't clutter the inbox).
    $where[] = 'c.last_message_at IS NOT NULL';

    $s = db()->prepare(chat_select_sql() . ' WHERE ' . implode(' AND ', $where) . ' ORDER BY c.last_message_at DESC LIMIT 200');
    $s->execute($params);
    $rows = $s->fetchAll();
    if (!$rows) return [];

    // Last message + unread count per conversation (one query each, batched by id list).
    $ids = array_column($rows, 'id');
    $in = implode(',', array_map('intval', $ids));
    $last = db()->query("
        SELECT m.conversation_id, m.body, m.sender_role, m.created_at
        FROM chat_messages m
        JOIN (SELECT conversation_id, MAX(id) AS mid FROM chat_messages WHERE conversation_id IN ($in) GROUP BY conversation_id) t
          ON t.mid = m.id
    ")->fetchAll(PDO::FETCH_UNIQUE);
    $mineRole = $role === 'admin' ? "m.sender_role = 'passenger'" : "m.sender_role <> " . db()->quote($role);
    $unread = db()->query("
        SELECT c.id, COUNT(m.id) FROM chat_conversations c
        JOIN chat_messages m ON m.conversation_id = c.id AND m.id > $readCol AND $mineRole
        WHERE c.id IN ($in) GROUP BY c.id
    ")->fetchAll(PDO::FETCH_KEY_PAIR);

    foreach ($rows as &$r) {
        $r['last_body']   = $last[$r['id']]['body'] ?? '';
        $r['last_sender'] = $last[$r['id']]['sender_role'] ?? '';
        $r['unread']      = (int)($unread[$r['id']] ?? 0);
    }
    return $rows;
}

/* ---------------------------------------------------------------------------
 * Display helpers
 * ------------------------------------------------------------------------- */

/** Who I'm talking to: [name, photo, subtitle]. */
function chat_counterpart(array $conv, array $me): array
{
    if ($me['role'] === 'passenger') {
        if ($conv['type'] === 'support') return ['Crab Support', null, 'We usually reply within a few minutes'];
        return [$conv['driver_name'] ?? 'Driver', $conv['driver_photo'], trim(($conv['vehicle_color'] ?? '') . ' ' . ($conv['vehicle_model'] ?? '')) . ' · ' . ($conv['plate_no'] ?? '')];
    }
    return [$conv['passenger_name'], $conv['passenger_photo'], $conv['type'] === 'ride' ? 'Passenger' : $conv['passenger_email']];
}

/** Short trip label, e.g. "Trip #159 · KL Sentral → Bangsar South". */
function chat_ride_label(array $conv): string
{
    if (!$conv['ride_id']) return '';
    return 'Trip #' . (int)$conv['ride_id'] . ' · ' . $conv['pickup_address'] . ' → ' . $conv['dropoff_address'];
}

/** "10:32 AM" today, "Yesterday 10:32 AM", otherwise "3 Nov, 10:32 AM". */
function chat_time(string $dt): string
{
    $ts = strtotime($dt);
    if (date('Y-m-d', $ts) === date('Y-m-d')) return date('g:i A', $ts);
    if (date('Y-m-d', $ts) === date('Y-m-d', strtotime('-1 day'))) return 'Yesterday ' . date('g:i A', $ts);
    return date('j M, g:i A', $ts);
}

/** Label shown above a message from someone else. */
function chat_sender_label(array $m, array $conv): string
{
    switch ($m['sender_role']) {
        case 'admin':     return 'Crab Support' . ($m['admin_name'] ? ' · ' . strtok($m['admin_name'], ' ') : '');
        case 'driver':    return $conv['driver_name'] ?? 'Driver';
        default:          return $conv['passenger_name'];
    }
}

/** A message as plain data for the JSON polling API (the browser builds the bubble safely with textContent). */
function chat_message_json(array $m, array $conv, array $me): array
{
    return [
        'id'     => (int)$m['id'],
        'mine'   => $m['sender_role'] === $me['role'],
        'sender' => chat_sender_label($m, $conv),
        'role'   => $m['sender_role'],
        'body'   => $m['body'],
        'time'   => chat_time($m['created_at']),
    ];
}

/**
 * Handle the AJAX side of a chat page (send / poll). Call at the top of a page after
 * loading $conv. Exits with JSON when the request is an AJAX one; otherwise returns.
 *  - GET  ?c=ID&poll=1&after=LASTID  → new messages + the other side's read pointer
 *  - POST action=send&body=…        → stores the message (JS) or redirects back (no-JS)
 */
function chat_handle_request(?array $conv, array $me, string $pageUrl): void
{
    $isAjax = ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'fetch';
    $json = function (array $data, int $code = 200) {
        http_response_code($code);
        header('Content-Type: application/json');
        header('Cache-Control: no-store');
        echo json_encode($data);
        exit;
    };

    if (isset($_GET['poll'])) {
        if (!$conv) $json(['error' => 'Conversation not found.'], 404);
        $after = max(0, (int)($_GET['after'] ?? 0));
        $msgs = chat_messages((int)$conv['id'], $after);
        if ($msgs) chat_mark_read($conv, $me, (int)end($msgs)['id']);
        $fresh = chat_find((int)$conv['id'], $me);  // re-read pointers/status
        $json([
            'messages'  => array_map(fn($m) => chat_message_json($m, $conv, $me), $msgs),
            'otherRead' => chat_other_read($fresh, $me),
            'status'    => $fresh['status'],
        ]);
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'send') {
        verify_csrf();
        if (!$conv) {
            if ($isAjax) $json(['error' => 'Conversation not found.'], 404);
            flash('error', 'That conversation was not found.');
            redirect($pageUrl);
        }
        [$body, $err] = chat_clean_body((string)($_POST['body'] ?? ''));
        if ($err) {
            if ($isAjax) $json(['error' => $err], 422);
            flash('error', $err);
            redirect($pageUrl . (str_contains($pageUrl, '?') ? '&' : '?') . 'c=' . (int)$conv['id']);
        }
        $m = chat_send($conv, $me, $body);
        if ($isAjax) $json(['message' => chat_message_json($m, $conv, $me)]);
        redirect($pageUrl . (str_contains($pageUrl, '?') ? '&' : '?') . 'c=' . (int)$conv['id'] . '#bottom');
    }
}

/**
 * Render the conversation pane (header + messages + composer).
 * $pageUrl is the page's own path (e.g. 'passenger/messages.php'); $headerExtra is extra HTML for the header (buttons).
 */
function chat_render(array $conv, array $me, string $pageUrl, string $headerExtra = '', string $prefill = ''): string
{
    $msgs = chat_messages((int)$conv['id']);
    if ($msgs) chat_mark_read($conv, $me, (int)end($msgs)['id']);
    [$name, $photo, $sub] = chat_counterpart($conv, $me);
    $otherRead = chat_other_read($conv, $me);
    // "Seen" only makes sense under my message when it's the latest one in the conversation.
    $last = $msgs ? end($msgs) : null;
    $myLastId = $last && $last['sender_role'] === $me['role'] ? (int)$last['id'] : 0;

    $avatar = $conv['type'] === 'support' && $me['role'] === 'passenger'
        ? '<span class="avatar support-avatar">' . crab_logo(22) . '</span>'
        : avatar($name, $photo);

    $h = '<section class="chat" data-chat data-conv="' . (int)$conv['id'] . '" data-me="' . e($me['role']) . '"'
        . ' data-poll="' . e(url($pageUrl) . '?c=' . (int)$conv['id'] . '&poll=1') . '"'
        . ' data-send="' . e(url($pageUrl)) . '" data-last="' . ($msgs ? (int)end($msgs)['id'] : 0) . '"'
        . ' data-other-read="' . $otherRead . '">';

    // Header
    $h .= '<header class="chat-head">'
        . '<a class="icon-btn chat-back" href="' . e(url($pageUrl)) . '" aria-label="Back to conversations">' . icon('arrow', 18) . '</a>'
        . $avatar
        . '<div class="chat-who"><strong>' . e($name) . '</strong><small class="muted">' . e($sub) . '</small>'
        . ($conv['ride_id'] ? '<small class="muted chat-trip">' . e(chat_ride_label($conv)) . '</small>' : '')
        . '</div>' . $headerExtra . '</header>';

    // Messages
    $h .= '<div class="chat-log" data-chat-log role="log" aria-live="polite" aria-label="Messages">';
    if (!$msgs) {
        $h .= '<p class="chat-empty muted" data-chat-empty>' . ($conv['type'] === 'support'
            ? 'Tell us what you need help with — a trip, a payment, or anything else. A Crab Support agent will reply here.'
            : 'Say hello! Messages here go straight to ' . e($name) . '.') . '</p>';
    }
    foreach ($msgs as $m) {
        $mine = $m['sender_role'] === $me['role'];
        $h .= '<div class="msg ' . ($mine ? 'mine' : 'theirs') . ' role-' . e($m['sender_role']) . '" data-id="' . (int)$m['id'] . '">'
            . (!$mine ? '<span class="msg-sender">' . e(chat_sender_label($m, $conv)) . '</span>' : '')
            . '<div class="msg-bubble">' . nl2br(e($m['body'])) . '</div>'
            . '<span class="msg-time">' . e(chat_time($m['created_at'])) . '</span></div>';
    }
    $h .= '<span class="msg-seen" data-seen' . ($myLastId && $otherRead >= $myLastId ? '' : ' hidden') . '>Seen</span>';
    $h .= '<span id="bottom"></span></div>';

    // Composer (works without JS as a normal form post)
    $h .= '<form class="chat-compose" method="post" action="' . e(url($pageUrl) . '?c=' . (int)$conv['id']) . '" data-chat-form>'
        . csrf_field()
        . '<input type="hidden" name="action" value="send"><input type="hidden" name="c" value="' . (int)$conv['id'] . '">'
        . '<label class="sr-only" for="chat-body">Message</label>'
        . '<textarea id="chat-body" name="body" rows="1" maxlength="' . CHAT_MAX_LENGTH . '" placeholder="Type a message…" required data-chat-input>' . e($prefill) . '</textarea>'
        . '<button class="btn btn-primary chat-send" aria-label="Send">' . icon('arrow', 18) . '<span>Send</span></button>'
        . '</form><p class="chat-error" data-chat-error hidden></p>';

    return $h . '</section>';
}

/** One row in an inbox list. */
function chat_inbox_item(array $c, array $me, string $pageUrl, bool $active): string
{
    [$name, $photo, $sub] = chat_counterpart($c, $me);
    $avatar = $c['type'] === 'support' && $me['role'] === 'passenger'
        ? '<span class="avatar support-avatar">' . crab_logo(20) . '</span>'
        : avatar($name, $photo);
    $prefix = $c['last_sender'] === $me['role'] ? 'You: ' : '';
    $context = $c['ride_id'] ? 'Trip #' . (int)$c['ride_id'] . ' · ' . $c['dropoff_address'] : ($me['role'] === 'admin' ? 'Support' : '');
    return '<a class="inbox-item' . ($active ? ' active' : '') . ($c['unread'] ? ' unread' : '') . '" href="' . e(url($pageUrl) . '?c=' . (int)$c['id']) . '">'
        . $avatar
        . '<span class="inbox-text"><span class="inbox-top"><strong>' . e($name) . '</strong><small class="muted">' . e(time_ago($c['last_message_at'])) . '</small></span>'
        . ($context ? '<small class="muted inbox-context">' . e($context) . '</small>' : '')
        . '<span class="inbox-last">' . e($prefix . mb_strimwidth(preg_replace('/\s+/', ' ', $c['last_body']), 0, 80, '…')) . '</span></span>'
        . ($c['unread'] ? '<span class="nav-count" aria-label="' . $c['unread'] . ' unread">' . $c['unread'] . '</span>' : '')
        . ($me['role'] === 'admin' && $c['status'] === 'resolved' ? '<span class="badge badge-completed">Resolved</span>' : '')
        . '</a>';
}
