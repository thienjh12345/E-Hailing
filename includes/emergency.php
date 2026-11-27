<?php
/**
 * Emergency (SOS) button during a ride — for BOTH the passenger and the driver.
 *
 * !! SIMULATION ONLY !!  Nothing here contacts a real emergency service. Pressing the
 * button plays a pretend call to EMERGENCY_NUMBER and shows a made-up "help is on the way"
 * countdown, so the feature can be demonstrated. (Malaysia's real number is 999.)
 *
 * How it works:
 *  - Only on a live trip with a driver (accepted = driver heading to pick-up, ongoing = on the trip).
 *  - The first press on a trip leg creates a ride_emergencies row with a fake arrival time
 *    (EMERGENCY_HELP_MIN..MAX seconds away). Help is sent to where the trip road shows the car:
 *    the pick-up point before the trip starts, the destination once it has started.
 *  - If the other person presses it too, their time is recorded and the SAME help is used.
 *  - Both pages poll passenger/emergency.php or driver/emergency.php (JSON) every few seconds,
 *    so each side sees when the other one called and whether help is on the way or has arrived.
 *  - The pretend call itself (ringing, operator lines, countdown) is in assets/js/emergency.js.
 */

const EMERGENCY_NUMBER = '911';
const EMERGENCY_HELP_MIN_SECONDS = 30;   // fake arrival time is picked between these two
const EMERGENCY_HELP_MAX_SECONDS = 60;   // (short, so the arrival can be seen in a demo)

/** A trip the emergency button can be used on: has a driver and is accepted or ongoing. */
function emergency_ride_is_live(?array $ride): bool
{
    return $ride && !empty($ride['driver_id']) && in_array($ride['status'], ['accepted', 'ongoing'], true);
}

/** Where help is sent: the point the trip road shows the car at for this part of the trip. */
function emergency_location(array $ride): string
{
    return $ride['status'] === 'ongoing' ? $ride['dropoff_address'] : $ride['pickup_address'];
}

/** The emergency for the trip's current leg (or null). Times are worked out by MySQL so clocks always agree. */
function emergency_for_ride(array $ride): ?array
{
    $s = db()->prepare('
        SELECT e.*,
               GREATEST(0, TIMESTAMPDIFF(SECOND, NOW(), e.help_arrives_at)) AS seconds_left,
               TIMESTAMPDIFF(SECOND, e.created_at, e.help_arrives_at)       AS help_seconds
        FROM ride_emergencies e
        WHERE e.ride_id = ? AND e.driver_id = ? AND e.trip_status = ?');
    $s->execute([$ride['id'], $ride['driver_id'], $ride['status']]);
    return $s->fetch() ?: null;
}

/**
 * The passenger or driver presses the button. The first press dispatches (fake) help;
 * a later press — by the other person, or the same one again — keeps the same help.
 * Safe if both press at the same moment (unique key + ON DUPLICATE KEY).
 */
function emergency_call(array $ride, string $role): array
{
    $col = $role === 'driver' ? 'driver_called_at' : 'passenger_called_at';
    db()->prepare("
        INSERT INTO ride_emergencies (ride_id, driver_id, trip_status, location, $col, help_arrives_at)
        VALUES (?, ?, ?, ?, NOW(), NOW() + INTERVAL ? SECOND)
        ON DUPLICATE KEY UPDATE $col = COALESCE($col, NOW())")
        ->execute([$ride['id'], $ride['driver_id'], $ride['status'], emergency_location($ride),
                   random_int(EMERGENCY_HELP_MIN_SECONDS, EMERGENCY_HELP_MAX_SECONDS)]);
    return emergency_for_ride($ride);
}

/** What the page needs to show, from the point of view of $viewer ('passenger' or 'driver'). */
function emergency_view(?array $e, string $viewer): ?array
{
    if (!$e) return null;
    $other = $viewer === 'driver' ? 'passenger' : 'driver';
    $time = fn(?string $dt) => $dt ? date('g:i A', strtotime($dt)) : null;
    return [
        'id'              => (int)$e['id'],
        'location'        => $e['location'],
        'you_called_at'   => $time($e[$viewer . '_called_at']),
        'other_called_at' => $time($e[$other . '_called_at']),
        'seconds_left'    => (int)$e['seconds_left'],
        'help_seconds'    => max(1, (int)$e['help_seconds']),
        'arrives_at'      => $time($e['help_arrives_at']),
    ];
}

/* ---------------------------------------------------------------------------
 * Markup — placed inside the trip road card by trip_road() (includes/trip_road.php)
 * ------------------------------------------------------------------------- */

/** data-* attributes for the trip road card: where to call, who is viewing, and the current state. */
function emergency_attrs(array $ride, string $viewer, ?array $view): string
{
    return ' data-sos-url="' . e(url($viewer . '/emergency.php')) . '"'
         . ' data-sos-csrf="' . e(csrf_token()) . '"'
         . ' data-sos-ride="' . (int)$ride['id'] . '"'
         . ' data-sos-viewer="' . e($viewer) . '"'
         . ' data-sos-other="' . ($viewer === 'driver' ? 'Your passenger' : 'Your driver') . '"'
         . ' data-sos-number="' . e(EMERGENCY_NUMBER) . '"'
         . ' data-sos-state="' . e(json_encode($view)) . '"'
         . ($view ? ' data-sos-active="1"' : '');
}

/** The red Emergency button (shown by JS — the pretend call needs it). */
function emergency_button(): string
{
    return '<button type="button" class="btn btn-sos" data-sos-open hidden>' . icon('siren', 17) . ' Emergency</button>';
}

/** Strip shown on the trip card while an emergency is open on this trip. */
function emergency_banner(): string
{
    return '<div class="sos-banner" data-sos-banner hidden>'
        . '<span class="sos-banner-icon">' . icon('siren', 20) . '</span>'
        . '<div class="sos-banner-text"><strong data-sos-banner-title>Emergency — help is on the way</strong><small data-sos-banner-sub></small></div>'
        . '<span class="sos-banner-eta" data-sos-banner-eta aria-hidden="true"></span>'
        . '<button type="button" class="btn btn-sm btn-outline" data-sos-details>Details</button>'
        . '</div>';
}

/** The pop-up: confirm (with countdown) → pretend call → help tracker. Filled in by emergency.js. */
function emergency_dialog(array $ride): string
{
    $n = e(EMERGENCY_NUMBER);
    return '<dialog class="sos-dialog" data-sos-dialog aria-label="Emergency">'
        // 1. Confirm: calls automatically after a short countdown, so it can't be pressed by accident
        . '<section class="sos-step sos-confirm" data-sos-step="confirm">'
        .   '<div class="sos-count" aria-hidden="true"><svg viewBox="0 0 120 120"><circle class="sos-count-track" cx="60" cy="60" r="52"/><circle class="sos-count-ring" data-sos-ring cx="60" cy="60" r="52"/></svg><span data-sos-count>3</span></div>'
        .   '<h2>Call ' . $n . '?</h2>'
        .   '<p class="muted">We\'ll call emergency services and share this trip — where you are, the car and its plate number.</p>'
        .   '<p class="sos-count-text" aria-live="polite">Calling automatically in <strong data-sos-count-text>3</strong> s…</p>'
        .   '<div class="sos-actions"><button type="button" class="btn btn-sos btn-lg" data-sos-call>' . icon('phone', 18) . ' Call ' . $n . ' now</button>'
        .   '<button type="button" class="btn btn-ghost" data-sos-cancel>Cancel — I\'m safe</button></div>'
        . '</section>'
        // 2. The pretend phone call
        . '<section class="sos-step sos-call" data-sos-step="call" hidden>'
        .   '<div class="sos-phone"><div class="sos-avatar">' . icon('phone', 30) . '<span class="sos-wave"></span><span class="sos-wave"></span></div>'
        .   '<strong class="sos-number">' . $n . '</strong><span class="sos-call-status" data-sos-call-status>Calling…</span>'
        .   '<small class="sos-call-sub">Emergency services · Trip #' . (int)$ride['id'] . '</small></div>'
        .   '<ol class="sos-transcript" data-sos-transcript aria-live="polite"></ol>'
        . '</section>'
        // 3. Status (opened from the banner, or when the other person calls)
        . '<section class="sos-step sos-status" data-sos-step="status" hidden>'
        .   '<span class="sos-status-icon">' . icon('siren', 26) . '</span>'
        .   '<h2 data-sos-status-title>Help is on the way</h2>'
        .   '<ul class="sos-log" data-sos-log></ul>'
        . '</section>'
        // Help tracker (shared by the call and status steps)
        . '<div class="sos-help" data-sos-help hidden>'
        .   '<div class="sos-help-row"><span class="sos-help-icon" data-sos-help-icon>' . icon('siren', 20) . '</span>'
        .   '<div class="sos-help-text"><strong data-sos-help-title>Help is on the way</strong><small data-sos-help-place></small></div>'
        .   '<span class="sos-help-eta" data-sos-help-eta></span></div>'
        .   '<div class="sos-help-bar"><span data-sos-help-fill></span></div>'
        .   '<ol class="sos-help-steps"><li data-sos-stage="called">' . $n . ' called</li><li data-sos-stage="sent">Help sent</li><li data-sos-stage="arrived">Help arrived</li></ol>'
        . '</div>'
        . '<div class="sos-foot">'
        .   '<div class="sos-actions" data-sos-foot-actions hidden>'
        .     '<button type="button" class="btn btn-sos" data-sos-again hidden>' . icon('phone', 16) . ' <span data-sos-again-label>Call ' . $n . ' too</span></button>'
        .     '<button type="button" class="btn btn-ghost sos-sound" data-sos-sound aria-pressed="true">' . icon('volume', 16) . icon('volume-off', 16) . ' <span data-sos-sound-label>Sound on</span></button>'
        .     '<button type="button" class="btn btn-outline" data-sos-close>Close</button>'
        .   '</div>'
        .   '<p class="sos-note">' . icon('alert', 14) . ' <span>Simulation for this project — no real emergency service is contacted. In a real emergency in Malaysia, call <strong>999</strong>.</span></p>'
        . '</div>'
        . '</dialog>';
}

/**
 * JSON endpoint used by assets/js/emergency.js (passenger/emergency.php, driver/emergency.php).
 *   GET               → {ride, emergency}   current state, polled every few seconds
 *   POST action=call  → {ride, emergency}   press the button (needs the CSRF token and the trip id)
 */
function emergency_endpoint(string $viewer, ?array $ride, bool $signedIn): void
{
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    $reply = function (int $code, array $data): void {
        http_response_code($code);
        echo json_encode($data);
        exit;
    };

    if (!$signedIn) $reply(401, ['error' => 'Please sign in again.']);
    if (!emergency_ride_is_live($ride)) $ride = null;

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        if (($_POST['action'] ?? '') !== 'call') $reply(400, ['error' => 'Unknown action.']);
        if (!$ride || (int)$ride['id'] !== (int)($_POST['ride_id'] ?? 0)) {
            $reply(409, ['error' => 'This trip is no longer active.', 'ride' => $ride ? (int)$ride['id'] : null]);
        }
        $e = emergency_call($ride, $viewer);
    } else {
        $e = $ride ? emergency_for_ride($ride) : null;
    }
    $reply(200, ['ride' => $ride ? (int)$ride['id'] : null, 'status' => $ride['status'] ?? null, 'emergency' => emergency_view($e, $viewer)]);
}
