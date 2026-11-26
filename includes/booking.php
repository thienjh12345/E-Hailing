<?php
/**
 * Ride booking — the life of a trip, shared by the passenger, driver and admin portals.
 *
 *   requested ──(driver accepts)──► accepted ──(start)──► ongoing ──(complete)──► completed
 *       │                              │                                   (payment settled)
 *       └──── cancelled ◄──────────────┘  (passenger before pickup, admin, or no driver in 10 min)
 *
 * A driver can also hand an accepted trip back; it then goes back to "requested" for other drivers.
 */

require_once __DIR__ . '/locations.php';
require_once __DIR__ . '/payments.php';

const REQUEST_TIMEOUT_MINUTES = 10;   // unaccepted requests are cancelled after this
const PICKUP_NOTE_MAX = 120;

/** Cancel requests nobody accepted in time (nothing is charged). Cheap to call on every page load. */
function booking_expire_stale(): void
{
    $pdo = db();
    $ids = $pdo->query("SELECT id FROM rides WHERE status = 'requested' AND driver_id IS NULL
                        AND requested_at < NOW() - INTERVAL " . REQUEST_TIMEOUT_MINUTES . " MINUTE")->fetchAll(PDO::FETCH_COLUMN);
    if (!$ids) return;
    $in = implode(',', array_map('intval', $ids));
    $pdo->exec("UPDATE rides SET status = 'cancelled', cancelled_at = NOW(), cancelled_by = 'system' WHERE id IN ($in) AND status = 'requested'");
    $pdo->exec("UPDATE payments SET status = 'cancelled', note = 'No driver was available' WHERE ride_id IN ($in) AND status = 'pending'");
}

/** The passenger's current trip (requested / accepted / ongoing), with driver details. */
function passenger_live_ride(int $passengerId): ?array
{
    $s = db()->prepare("
        SELECT r.*, d.name AS driver_name, d.profile_photo AS driver_photo, d.phone AS driver_phone, d.vehicle_model, d.vehicle_color,
               d.plate_no, d.rating AS driver_rating, d.vehicle_type
        FROM rides r LEFT JOIN drivers d ON d.id = r.driver_id
        WHERE r.passenger_id = ? AND r.status IN ('requested','accepted','ongoing')
        ORDER BY r.id DESC LIMIT 1");
    $s->execute([$passengerId]);
    return $s->fetch() ?: null;
}

/* ---------------------------------------------------------------------------
 * Passenger side
 * ------------------------------------------------------------------------- */

/**
 * Validate and create a booking. Returns [rideId|null, errors].
 * Nothing is charged now — card and wallet payments are taken when the trip ends.
 */
function booking_create(array $passenger, array $in): array
{
    $errors = [];
    $pid = (int)$passenger['id'];
    if ($passenger['status'] !== 'active') return [null, ['form' => 'Your account is suspended, so you can\'t book rides right now. Please contact Crab Support.']];
    if (passenger_live_ride($pid)) return [null, ['form' => 'You already have a trip in progress. Finish or cancel it before booking another.']];

    $pickup  = (string)($in['pickup'] ?? '');
    $dropoff = (string)($in['dropoff'] ?? '');
    $type    = (string)($in['service_type'] ?? '');
    $method  = (string)($in['payment_method'] ?? '');
    $note    = preg_replace('/\s+/', ' ', trim((string)($in['pickup_note'] ?? '')));

    $from = find_location($pickup);
    $to   = find_location($dropoff);
    if (!$from) $errors['pickup'] = 'Choose a pick-up point from the list.';
    if (!$to)   $errors['dropoff'] = 'Choose a destination from the list.';
    if ($from && $to && $from[0] !== $to[0]) $errors['dropoff'] = 'Pick-up and destination must be in the same city.';
    if ($from && $to && $pickup === $dropoff) $errors['dropoff'] = 'Choose a destination different from your pick-up point.';
    if (!isset(FARES[$type])) $errors['service_type'] = 'Choose a ride type.';
    if (mb_strlen($note) > PICKUP_NOTE_MAX) $errors['pickup_note'] = 'Keep the note under ' . PICKUP_NOTE_MAX . ' characters.';
    if (!isset(PAYMENT_LABELS[$method])) $errors['payment_method'] = 'Choose how you\'ll pay.';
    if ($errors) return [null, $errors];

    $km = trip_distance_km($from[1], $from[2], $to[1], $to[2]);
    $fare = trip_fare($type, $km)['total'];

    $cardId = null;
    if ($method === 'card') {
        $card = find_card((int)($in['card_id'] ?? 0), $pid);
        if (!$card) $errors['payment_method'] = 'Choose one of your saved cards, or add a card in Wallet.';
        elseif (card_expired($card)) $errors['payment_method'] = 'That card has expired. Choose another card.';
        else $cardId = (int)$card['id'];
    }
    if ($method === 'wallet' && wallet_balance($pid) < $fare) {
        $errors['payment_method'] = 'Your CrabPay balance (' . money(wallet_balance($pid)) . ') is less than the fare (' . money($fare) . '). Top up or choose another method.';
    }
    if ($errors) return [null, $errors];

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $pdo->prepare("INSERT INTO rides (passenger_id, pickup_address, dropoff_address, pickup_lat, pickup_lng, dropoff_lat, dropoff_lng,
                         distance_km, fare, payment_method, status, service_type, city, pickup_note)
                       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'requested', ?, ?, ?)")
            ->execute([$pid, $pickup, $dropoff, $from[1], $from[2], $to[1], $to[2], $km, $fare, $method, $type, $from[0], $note === '' ? null : $note]);
        $rideId = (int)$pdo->lastInsertId();
        payment_create($rideId, $pid, $method, $fare, $cardId);
        $pdo->commit();
        return [$rideId, []];
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/** Passenger cancels before pick-up (requested or accepted). */
function booking_cancel_by_passenger(int $passengerId, int $rideId): bool
{
    $s = db()->prepare("UPDATE rides SET status = 'cancelled', cancelled_at = NOW(), cancelled_by = 'passenger'
                        WHERE id = ? AND passenger_id = ? AND status IN ('requested','accepted')");
    $s->execute([$rideId, $passengerId]);
    if ($s->rowCount()) payment_cancel($rideId);
    return $s->rowCount() > 0;
}

/* ---------------------------------------------------------------------------
 * Driver side
 * ------------------------------------------------------------------------- */

/** Why this driver can't take requests right now, or null if they can. */
function driver_block_reason(array $d): ?string
{
    if ($d['status'] !== 'approved') return 'Only approved drivers can take ride requests.';
    $days = license_days_left($d['license_expiry']);
    if ($days !== null && $days < 0) return 'Your driving licence has expired. Update it under Licence & vehicle.';
    if (!$d['is_online']) return 'You\'re offline. Go online to see ride requests.';
    return null;
}

/** The driver's current trip (accepted / ongoing) with passenger details. */
function driver_active_trip(int $driverId): ?array
{
    $s = db()->prepare("
        SELECT r.*, p.name AS passenger_name, p.profile_photo AS passenger_photo, p.phone AS passenger_phone, pay.status AS payment_status, pay.note AS payment_note
        FROM rides r JOIN passengers p ON p.id = r.passenger_id LEFT JOIN payments pay ON pay.ride_id = r.id
        WHERE r.driver_id = ? AND r.status IN ('accepted','ongoing') ORDER BY r.id DESC LIMIT 1");
    $s->execute([$driverId]);
    return $s->fetch() ?: null;
}

/** Open requests this driver could take: same city and service type, oldest first. */
function driver_open_requests(array $d): array
{
    $s = db()->prepare("
        SELECT r.*, p.name AS passenger_name, p.profile_photo AS passenger_photo
        FROM rides r JOIN passengers p ON p.id = r.passenger_id
        WHERE r.status = 'requested' AND r.driver_id IS NULL AND r.city = ? AND r.service_type = ?
        ORDER BY r.requested_at ASC LIMIT 20");
    $s->execute([$d['city'], $d['vehicle_type']]);
    return $s->fetchAll();
}

/** Accept a request. Only one driver can win it (the UPDATE checks it's still unassigned). */
function driver_accept(array $d, int $rideId): array
{
    if ($why = driver_block_reason($d)) return [false, $why];
    if (driver_active_trip((int)$d['id'])) return [false, 'Finish your current trip before accepting another.'];

    $s = db()->prepare("UPDATE rides SET driver_id = ?, status = 'accepted', accepted_at = NOW()
                        WHERE id = ? AND status = 'requested' AND driver_id IS NULL AND city = ? AND service_type = ?");
    $s->execute([$d['id'], $rideId, $d['city'], $d['vehicle_type']]);
    if (!$s->rowCount()) return [false, 'Sorry — another driver took this trip, or it was cancelled.'];
    // If the passenger already had a chat for this trip (e.g. a previous driver handed it back), it moves to you.
    db()->prepare('UPDATE chat_conversations SET driver_id = ? WHERE ride_id = ?')->execute([$d['id'], $rideId]);
    return [true, 'Trip accepted. Head to the pick-up point — the passenger can see you\'re on the way.'];
}

function driver_start_trip(array $d, int $rideId): array
{
    $s = db()->prepare("UPDATE rides SET status = 'ongoing', started_at = NOW() WHERE id = ? AND driver_id = ? AND status = 'accepted'");
    $s->execute([$rideId, $d['id']]);
    return $s->rowCount() ? [true, 'Trip started. Drive safely!'] : [false, 'This trip can\'t be started.'];
}

/** Hand an accepted trip back (before pick-up). It returns to the queue for other drivers. */
function driver_release_trip(array $d, int $rideId): array
{
    $s = db()->prepare("UPDATE rides SET status = 'requested', driver_id = NULL, accepted_at = NULL, requested_at = NOW()
                        WHERE id = ? AND driver_id = ? AND status = 'accepted'");
    $s->execute([$rideId, $d['id']]);
    return $s->rowCount() ? [true, 'Trip handed back. We\'re finding the passenger another driver.'] : [false, 'This trip can\'t be handed back now.'];
}

/** Finish the trip and settle the payment, all in one transaction. */
function driver_complete_trip(array $d, int $rideId): array
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $s = $pdo->prepare("SELECT * FROM rides WHERE id = ? AND driver_id = ? AND status = 'ongoing' FOR UPDATE");
        $s->execute([$rideId, $d['id']]);
        $ride = $s->fetch();
        if (!$ride) {
            $pdo->rollBack();
            return [false, 'This trip can\'t be completed.'];
        }
        $pdo->prepare("UPDATE rides SET status = 'completed', completed_at = NOW() WHERE id = ?")->execute([$rideId]);
        $msg = payment_settle($ride);
        $pdo->commit();
        return [true, 'Trip completed. ' . $msg];
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}
