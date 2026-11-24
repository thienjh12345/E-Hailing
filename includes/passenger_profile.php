<?php
/**
 * Passenger Profile Management — shared rules used by BOTH the passenger portal
 * (passenger/*) and the admin console (admin/passenger*.php).
 *
 * Links to the other modules:
 *  - Admin: suspend/reactivate with a reason (status_note) the passenger sees.
 *  - Drivers: a passenger's trip rating updates that driver's overall rating
 *    (drivers.rating), and passengers see their driver's photo, vehicle and plate.
 */

require_once __DIR__ . '/validation.php';

const PAYMENT_METHODS = ['cash' => 'Cash', 'card' => 'Card', 'wallet' => 'CrabPay wallet'];
const GENDERS = ['' => 'Prefer not to say', 'male' => 'Male', 'female' => 'Female'];

/** Profile fields a passenger can edit themselves. */
const PASSENGER_PROFILE_FIELDS = ['name', 'email', 'phone', 'date_of_birth', 'gender', 'preferred_payment', 'emergency_name', 'emergency_phone'];

const MAX_SAVED_PLACES = 6;
const MIN_PASSENGER_AGE = 13;

/**
 * Validate and normalise passenger fields from user input.
 *
 * @return array{0: array, 1: array}  [clean values keyed by field, errors keyed by field]
 */
function validate_passenger_input(array $input, array $fields, ?int $passengerId = null): array
{
    $clean = [];
    $errors = [];
    foreach ($fields as $f) {
        $v = trim((string)($input[$f] ?? ''));
        switch ($f) {
            case 'name':
                $v = preg_replace('/\s+/', ' ', $v);
                if ($err = validate_person_name($v)) $errors[$f] = $err;
                break;

            case 'email':
                $v = strtolower($v);
                if (!filter_var($v, FILTER_VALIDATE_EMAIL) || strlen($v) > 150) $errors[$f] = 'Enter a valid email address.';
                break;

            case 'phone':
                $n = normalize_phone($v);
                if ($n === null) $errors[$f] = 'Enter a Malaysian mobile number, e.g. 012-3456789.';
                else $v = $n;
                break;

            case 'emergency_phone':
                if ($v === '') { $v = null; break; }
                $n = normalize_phone($v);
                if ($n === null) $errors[$f] = 'Enter a Malaysian mobile number, e.g. 012-3456789.';
                else $v = $n;
                break;

            case 'emergency_name':
                $v = preg_replace('/\s+/', ' ', $v);
                if ($v === '') $v = null;
                elseif (mb_strlen($v) > 100) $errors[$f] = 'Keep this under 100 characters.';
                break;

            case 'date_of_birth':
                if ($v === '') { $v = null; break; }
                $dt = DateTime::createFromFormat('!Y-m-d', $v);
                if (!$dt || $dt->format('Y-m-d') !== $v) $errors[$f] = 'Enter a valid date.';
                elseif ($dt > new DateTime('-' . MIN_PASSENGER_AGE . ' years')) $errors[$f] = 'You must be at least ' . MIN_PASSENGER_AGE . ' years old to use Crab.';
                elseif ($dt < new DateTime('-110 years')) $errors[$f] = 'Please check the year.';
                break;

            case 'gender':
                if ($v === '') { $v = null; break; }
                if (!isset(GENDERS[$v])) $errors[$f] = 'Choose an option.';
                break;

            case 'preferred_payment':
                if (!isset(PAYMENT_METHODS[$v])) $errors[$f] = 'Choose a payment method.';
                break;
        }
        $clean[$f] = $v;
    }

    if (isset($clean['email']) && !isset($errors['email'])) {
        $s = db()->prepare('SELECT 1 FROM passengers WHERE email = ? AND id <> ?');
        $s->execute([$clean['email'], $passengerId ?? 0]);
        if ($s->fetch()) $errors['email'] = 'This email is already registered to another account.';
    }
    return [$clean, $errors];
}

function find_passenger(int $id): ?array
{
    $s = db()->prepare('SELECT * FROM passengers WHERE id = ?');
    $s->execute([$id]);
    return $s->fetch() ?: null;
}

/** Update whitelisted passenger columns (safe to call with validated form data). */
function update_passenger(int $id, array $values): void
{
    $allowed = array_merge(PASSENGER_PROFILE_FIELDS, ['profile_photo']);
    $values = array_intersect_key($values, array_flip($allowed));
    if (!$values) return;
    $sets = implode(', ', array_map(fn($c) => "`$c` = ?", array_keys($values)));
    db()->prepare("UPDATE passengers SET $sets WHERE id = ?")->execute([...array_values($values), $id]);
}

/** Only fields whose value differs from the current row. */
function passenger_diff(array $row, array $values): array
{
    return array_filter($values, fn($v, $k) => (string)($row[$k] ?? '') !== (string)($v ?? ''), ARRAY_FILTER_USE_BOTH);
}

function passenger_age(?string $dob): ?int
{
    return $dob ? (new DateTime($dob))->diff(new DateTime('today'))->y : null;
}

/** Ride stats for the passenger home page and the admin profile page. */
function passenger_stats(int $passengerId): array
{
    $s = db()->prepare("
        SELECT
          COUNT(*)                                                             AS rides,
          SUM(status = 'completed')                                            AS completed,
          SUM(status = 'cancelled')                                            AS cancelled,
          COALESCE(SUM(CASE WHEN status='completed' THEN fare END), 0)         AS spent,
          COALESCE(SUM(CASE WHEN status='completed' AND completed_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01') THEN fare END), 0) AS spent_month,
          SUM(status = 'completed' AND completed_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')) AS rides_month,
          COALESCE(SUM(CASE WHEN status='completed' THEN distance_km END), 0)  AS km,
          ROUND(AVG(rating), 1)                                                AS avg_rating_given,
          SUM(status = 'completed' AND rating IS NULL AND driver_id IS NOT NULL) AS unrated
        FROM rides WHERE passenger_id = ?
    ");
    $s->execute([$passengerId]);
    $r = $s->fetch();
    $done = (int)$r['completed'] + (int)$r['cancelled'];
    return [
        'rides'       => (int)$r['rides'],
        'completed'   => (int)$r['completed'],
        'spent'       => (float)$r['spent'],
        'spent_month' => (float)$r['spent_month'],
        'rides_month' => (int)$r['rides_month'],
        'km'          => (float)$r['km'],
        'avg_given'   => $r['avg_rating_given'],
        'unrated'     => (int)$r['unrated'],
        'cancel_rate' => $done ? round((int)$r['cancelled'] / $done * 100, 1) : 0,
    ];
}

/** Favourite payment method by actual usage (for the admin profile). */
function passenger_top_payment(int $passengerId): ?string
{
    $s = db()->prepare("SELECT payment_method FROM rides WHERE passenger_id = ? AND status = 'completed' GROUP BY payment_method ORDER BY COUNT(*) DESC LIMIT 1");
    $s->execute([$passengerId]);
    return $s->fetchColumn() ?: null;
}

/* ---------------------------------------------------------------------------
 * Rides & ratings (the link to the driver system)
 * ------------------------------------------------------------------------- */

/**
 * A passenger's rides with the driver's public profile (photo, vehicle, plate, rating).
 */
function passenger_rides(int $passengerId, ?string $status, int $limit, int $offset = 0): array
{
    $where = 'r.passenger_id = ?';
    $params = [$passengerId];
    if ($status === 'live') {
        $where .= " AND r.status IN ('requested','accepted','ongoing')";
    } elseif ($status) {
        $where .= ' AND r.status = ?';
        $params[] = $status;
    }
    $s = db()->prepare("
        SELECT r.*, d.name AS driver_name, d.profile_photo AS driver_photo, d.vehicle_model, d.vehicle_color,
               d.plate_no, d.rating AS driver_rating, d.vehicle_type
        FROM rides r LEFT JOIN drivers d ON d.id = r.driver_id
        WHERE $where ORDER BY r.requested_at DESC LIMIT $limit OFFSET $offset
    ");
    $s->execute($params);
    return $s->fetchAll();
}

function count_passenger_rides(int $passengerId, ?string $status): int
{
    $where = 'passenger_id = ?';
    $params = [$passengerId];
    if ($status === 'live') {
        $where .= " AND status IN ('requested','accepted','ongoing')";
    } elseif ($status) {
        $where .= ' AND status = ?';
        $params[] = $status;
    }
    $s = db()->prepare("SELECT COUNT(*) FROM rides WHERE $where");
    $s->execute($params);
    return (int)$s->fetchColumn();
}

/**
 * Passenger rates a completed trip (1–5). The driver's overall rating is then
 * recalculated from all their rated trips, so it shows up immediately in the
 * driver portal and the admin console.
 *
 * @return array{0: bool, 1: string} [success, message]
 */
function rate_ride(int $passengerId, int $rideId, int $stars): array
{
    if ($stars < 1 || $stars > 5) return [false, 'Choose between 1 and 5 stars.'];
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $upd = $pdo->prepare("UPDATE rides SET rating = ? WHERE id = ? AND passenger_id = ? AND status = 'completed' AND rating IS NULL AND driver_id IS NOT NULL");
        $upd->execute([$stars, $rideId, $passengerId]);
        if ($upd->rowCount() === 0) {
            $pdo->rollBack();
            return [false, 'This trip can\'t be rated (it may already have a rating).'];
        }
        $pdo->prepare("
            UPDATE drivers d
            SET d.updated_at = d.updated_at,  -- a new rating is not a profile edit
                d.rating = (SELECT ROUND(AVG(r.rating), 2) FROM rides r WHERE r.driver_id = d.id AND r.rating IS NOT NULL)
            WHERE d.id = (SELECT driver_id FROM rides WHERE id = ?)
        ")->execute([$rideId]);
        $pdo->commit();
        return [true, 'Thanks for rating your trip!'];
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/* ---------------------------------------------------------------------------
 * Saved places
 * ------------------------------------------------------------------------- */

function saved_places(int $passengerId): array
{
    $s = db()->prepare("SELECT * FROM passenger_saved_places WHERE passenger_id = ? ORDER BY FIELD(label, 'Work', 'Home') DESC, label");
    $s->execute([$passengerId]);
    return $s->fetchAll();
}

/**
 * Validate a saved place. Label must be unique per passenger.
 * @return array{0: array, 1: array} [clean, errors]
 */
function validate_place(array $input, int $passengerId, ?int $placeId = null): array
{
    $errors = [];
    $label = preg_replace('/\s+/', ' ', trim((string)($input['label'] ?? '')));
    if (in_array(strtolower($label), ['home', 'work'], true)) $label = ucfirst(strtolower($label));
    $address = preg_replace('/\s+/', ' ', trim((string)($input['address'] ?? '')));
    $note = preg_replace('/\s+/', ' ', trim((string)($input['note'] ?? '')));

    if (mb_strlen($label) < 2 || mb_strlen($label) > 40) $errors['label'] = 'Give this place a name (2–40 characters).';
    if (mb_strlen($address) < 5 || mb_strlen($address) > 255) $errors['address'] = 'Enter the full address.';
    if (mb_strlen($note) > 120) $errors['note'] = 'Keep the note under 120 characters.';

    if (!isset($errors['label'])) {
        $s = db()->prepare('SELECT 1 FROM passenger_saved_places WHERE passenger_id = ? AND label = ? AND id <> ?');
        $s->execute([$passengerId, $label, $placeId ?? 0]);
        if ($s->fetch()) $errors['label'] = "You already have a place called \"$label\".";
    }
    return [['label' => $label, 'address' => $address, 'note' => $note === '' ? null : $note], $errors];
}

/** Star display, e.g. ★★★★☆ (filled stars in the brand amber). */
function stars(?int $n): string
{
    if (!$n) return '<span class="muted">—</span>';
    return '<span class="stars" aria-label="' . $n . ' out of 5 stars">' . str_repeat('★', $n) . '<span class="off">' . str_repeat('★', 5 - $n) . '</span></span>';
}
