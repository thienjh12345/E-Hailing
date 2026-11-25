<?php
/**
 * Driver Profile Management — shared rules used by BOTH the driver portal
 * (driver/*) and the admin console (admin/driver*.php).
 *
 *  - Field labels and validation live here so both sides enforce the same rules.
 *  - "Review" fields (identity, licence, vehicle) can only be changed by an approved
 *    driver through a change request that an admin approves.
 *  - "Free" fields (contact details, photo) the driver can change at any time.
 */

require_once __DIR__ . '/validation.php';

const VEHICLE_TYPES = ['car' => 'Crab Car', 'premium' => 'Crab Premium', 'bike' => 'Crab Bike'];

/** Fields a driver can update immediately. */
const DRIVER_FREE_FIELDS = ['email', 'phone', 'address', 'emergency_name', 'emergency_phone'];

/** Fields that need admin approval once the driver is approved. */
const DRIVER_IDENTITY_FIELDS = ['name', 'ic_no'];
const DRIVER_VEHICLE_FIELDS  = ['license_no', 'license_expiry', 'vehicle_type', 'vehicle_model', 'plate_no', 'vehicle_color', 'vehicle_year'];

/** Max vehicle age in years (Malaysian e-hailing vehicles must be under 10 years old). */
const MAX_VEHICLE_AGE = 10;

function driver_review_fields(): array
{
    return array_merge(DRIVER_IDENTITY_FIELDS, DRIVER_VEHICLE_FIELDS);
}

function driver_field_label(string $field): string
{
    static $labels = [
        'name' => 'Full name', 'ic_no' => 'IC number (MyKad)', 'email' => 'Email', 'phone' => 'Phone',
        'address' => 'Home address', 'emergency_name' => 'Emergency contact', 'emergency_phone' => 'Emergency phone',
        'license_no' => 'Driving licence no.', 'license_expiry' => 'Licence expiry', 'vehicle_type' => 'Service type',
        'vehicle_model' => 'Vehicle make & model', 'plate_no' => 'Plate number', 'vehicle_color' => 'Colour',
        'vehicle_year' => 'Year of manufacture',
    ];
    return $labels[$field] ?? ucfirst(str_replace('_', ' ', $field));
}

/** Human-readable value for display (profile pages, change-request diffs). */
function driver_field_display(string $field, $value): string
{
    if ($value === null || $value === '') return '—';
    switch ($field) {
        case 'vehicle_type':   return VEHICLE_TYPES[$value] ?? (string)$value;
        case 'license_expiry': return date('d M Y', strtotime($value));
        default:               return (string)$value;
    }
}

/* ---------------------------------------------------------------------------
 * Normalisers — turn what people type into one consistent stored format.
 * Each returns null when the input can't be valid. (normalize_phone is in validation.php)
 * ------------------------------------------------------------------------- */

/** "900101145678" → "900101-12-5678" (checks the birth date part too) */
function normalize_ic(string $raw): ?string
{
    $d = preg_replace('/\D/', '', $raw);
    if (strlen($d) !== 12) return null;
    $mm = (int)substr($d, 2, 2);
    $dd = (int)substr($d, 4, 2);
    if ($mm < 1 || $mm > 12 || $dd < 1 || $dd > 31) return null;
    return substr($d, 0, 6) . '-' . substr($d, 6, 2) . '-' . substr($d, 8);
}

/** "saa1234" → "SAA 1234", "vb 88 a" → "VB 88A" */
function normalize_plate(string $raw): ?string
{
    $s = strtoupper(preg_replace('/[\s\-]+/', '', $raw));
    if (!preg_match('/^([A-Z]{1,9})(\d{1,4})([A-Z]?)$/', $s, $m)) return null;
    return $m[1] . ' ' . $m[2] . $m[3];
}

/**
 * Validate and normalise a set of driver fields from user input.
 *
 * @param array    $input     raw input (usually $_POST)
 * @param string[] $fields    which fields to validate
 * @param int|null $driverId  current driver (excluded from uniqueness checks)
 * @return array{0: array, 1: array}  [clean values keyed by field, errors keyed by field]
 */
function validate_driver_input(array $input, array $fields, ?int $driverId = null): array
{
    $clean = [];
    $errors = [];
    $get = fn($k) => trim((string)($input[$k] ?? ''));

    foreach ($fields as $f) {
        $v = $get($f);
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

            case 'ic_no':
                $n = normalize_ic($v);
                if ($n === null) $errors[$f] = 'Enter your 12-digit MyKad number, e.g. 900101-12-5678.';
                else $v = $n;
                break;

            case 'address':
            case 'emergency_name':
                $v = preg_replace('/\s+/', ' ', $v);
                $max = $f === 'address' ? 255 : 100;
                if ($v === '') $v = null;
                elseif (mb_strlen($v) > $max) $errors[$f] = "Keep this under $max characters.";
                break;

            case 'license_no':
                $v = strtoupper(preg_replace('/\s+/', '', $v));
                if (!preg_match('/^[A-Z0-9]{5,20}$/', $v)) $errors[$f] = 'Enter your licence number (5–20 letters/digits).';
                break;

            case 'license_expiry':
                $dt = DateTime::createFromFormat('!Y-m-d', $v);
                if (!$dt || $dt->format('Y-m-d') !== $v) $errors[$f] = 'Enter a valid date.';
                elseif ($dt < new DateTime('today')) $errors[$f] = 'Your licence has expired. Renew it before continuing.';
                break;

            case 'vehicle_type':
                if (!isset(VEHICLE_TYPES[$v])) $errors[$f] = 'Choose a service type.';
                break;

            case 'vehicle_model':
                $v = preg_replace('/\s+/', ' ', $v);
                if (mb_strlen($v) < 2 || mb_strlen($v) > 80) $errors[$f] = 'Enter the vehicle make and model, e.g. Perodua Myvi.';
                break;

            case 'plate_no':
                $n = normalize_plate($v);
                if ($n === null) $errors[$f] = 'Enter a valid plate number, e.g. SAA 1234.';
                else $v = $n;
                break;

            case 'vehicle_color':
                $v = ucwords(strtolower(preg_replace('/\s+/', ' ', $v)));
                if (!preg_match('/^[\p{L} ]{3,30}$/u', $v)) $errors[$f] = 'Enter the vehicle colour, e.g. Silver.';
                break;

            case 'vehicle_year':
                $min = (int)date('Y') - MAX_VEHICLE_AGE;
                $max = (int)date('Y') + 1;
                if (!ctype_digit($v) || (int)$v < $min || (int)$v > $max) $errors[$f] = "Vehicles must be from $min or newer.";
                else $v = (int)$v;
                break;
        }
        $clean[$f] = $v;
    }

    // Uniqueness checks (only if the format is already valid)
    $pdo = db();
    if (isset($clean['email']) && !isset($errors['email'])) {
        $s = $pdo->prepare('SELECT 1 FROM drivers WHERE email = ? AND id <> ?');
        $s->execute([$clean['email'], $driverId ?? 0]);
        if ($s->fetch()) $errors['email'] = 'This email is already registered to another driver.';
    }
    if (isset($clean['plate_no']) && !isset($errors['plate_no'])) {
        $s = $pdo->prepare('SELECT 1 FROM drivers WHERE plate_no = ? AND id <> ?');
        $s->execute([$clean['plate_no'], $driverId ?? 0]);
        if ($s->fetch()) $errors['plate_no'] = 'This plate number is already registered to another driver.';
    }

    return [$clean, $errors];
}


/* ---------------------------------------------------------------------------
 * Reading / writing driver rows
 * ------------------------------------------------------------------------- */

function find_driver(int $id): ?array
{
    $s = db()->prepare('SELECT * FROM drivers WHERE id = ?');
    $s->execute([$id]);
    return $s->fetch() ?: null;
}

/**
 * Update driver columns. Only whitelisted profile columns can be written,
 * so this is safe to call with validated form data.
 */
function update_driver(int $id, array $values): void
{
    $allowed = array_merge(DRIVER_FREE_FIELDS, driver_review_fields(), ['profile_photo']);
    $values = array_intersect_key($values, array_flip($allowed));
    if (!$values) return;
    $sets = implode(', ', array_map(fn($c) => "`$c` = ?", array_keys($values)));
    db()->prepare("UPDATE drivers SET $sets WHERE id = ?")->execute([...array_values($values), $id]);
}

/** Only the fields whose value actually differs from the driver's current row. */
function driver_diff(array $driver, array $values): array
{
    $diff = [];
    foreach ($values as $k => $v) {
        if ((string)($driver[$k] ?? '') !== (string)($v ?? '')) $diff[$k] = $v;
    }
    return $diff;
}

/** Simple stats used on both the driver home page and the admin profile page. */
function driver_stats(int $driverId): array
{
    $s = db()->prepare("
        SELECT
          SUM(status = 'completed')                                         AS trips,
          COALESCE(SUM(CASE WHEN status='completed' THEN fare END), 0)      AS earned,
          COALESCE(SUM(CASE WHEN status='completed' AND completed_at >= CURDATE() - INTERVAL 6 DAY THEN fare END), 0) AS earned_week,
          SUM(status = 'completed' AND completed_at >= CURDATE() - INTERVAL 6 DAY) AS trips_week,
          SUM(status = 'cancelled')                                         AS cancelled,
          COUNT(rating)                                                     AS ratings
        FROM rides WHERE driver_id = ?
    ");
    $s->execute([$driverId]);
    $r = $s->fetch();
    $done = (int)$r['trips'] + (int)$r['cancelled'];
    return [
        'trips'       => (int)$r['trips'],
        'earned'      => (float)$r['earned'],
        'trips_week'  => (int)$r['trips_week'],
        'earned_week' => (float)$r['earned_week'],
        'ratings'     => (int)$r['ratings'],
        'cancel_rate' => $done ? round((int)$r['cancelled'] / $done * 100, 1) : 0,
    ];
}

/** Days until the licence expires (negative = expired), or null if unknown. */
function license_days_left(?string $expiry): ?int
{
    if (!$expiry) return null;
    return (int)(new DateTime('today'))->diff(new DateTime($expiry))->format('%r%a');
}

/* ---------------------------------------------------------------------------
 * Change requests (driver ⇄ admin)
 * ------------------------------------------------------------------------- */

function pending_change_request(int $driverId): ?array
{
    $s = db()->prepare("SELECT * FROM driver_change_requests WHERE driver_id = ? AND status = 'pending' ORDER BY id DESC LIMIT 1");
    $s->execute([$driverId]);
    $r = $s->fetch();
    if (!$r) return null;
    $r['changes'] = json_decode($r['changes'], true) ?: [];
    return $r;
}

function change_requests_for(int $driverId, int $limit = 10): array
{
    $s = db()->prepare("
        SELECT r.*, a.name AS admin_name
        FROM driver_change_requests r LEFT JOIN admins a ON a.id = r.reviewed_by
        WHERE r.driver_id = ? ORDER BY r.id DESC LIMIT $limit
    ");
    $s->execute([$driverId]);
    return array_map(function ($r) {
        $r['changes'] = json_decode($r['changes'], true) ?: [];
        return $r;
    }, $s->fetchAll());
}

/**
 * Save review-field changes from the driver portal.
 *
 * - Not yet approved (pending/rejected): changes apply straight away. A rejected
 *   driver who edits their details is automatically re-submitted for review.
 * - Approved/suspended: changes are stored as ONE pending change request
 *   (merged with any request that's already waiting) for an admin to approve.
 *
 * @return string message for the flash notice
 */
function submit_driver_review_changes(array $driver, array $changes): string
{
    $pdo = db();
    if (in_array($driver['status'], ['pending', 'rejected'], true)) {
        update_driver((int)$driver['id'], $changes);
        if ($driver['status'] === 'rejected') {
            $pdo->prepare("UPDATE drivers SET status = 'pending', status_note = NULL WHERE id = ?")->execute([$driver['id']]);
            return 'Details saved and your application has been re-submitted for review.';
        }
        return 'Details saved. They will be checked as part of your application.';
    }

    $existing = pending_change_request((int)$driver['id']);
    if ($existing) {
        // Merge: newest value wins; drop fields that are now back to the current value.
        $merged = array_merge($existing['changes'], $changes);
        $merged = driver_diff($driver, $merged);
        if (!$merged) {
            $pdo->prepare("UPDATE driver_change_requests SET status = 'cancelled', reviewed_at = NOW() WHERE id = ?")->execute([$existing['id']]);
            return 'Your details match what is on file, so the pending request was withdrawn.';
        }
        $pdo->prepare('UPDATE driver_change_requests SET changes = ?, created_at = NOW() WHERE id = ?')
            ->execute([json_encode($merged), $existing['id']]);
        return 'Your pending change request has been updated. An admin will review it shortly.';
    }

    $pdo->prepare('INSERT INTO driver_change_requests (driver_id, changes) VALUES (?, ?)')
        ->execute([$driver['id'], json_encode($changes)]);
    return 'Change request sent. Your current details stay active until an admin approves it.';
}

/**
 * Handle a submitted review form (identity or licence/vehicle) from the driver portal.
 * Review forms are pre-filled with any pending values, so a field the driver leaves
 * untouched keeps its pending change, and typing the old value back withdraws it.
 *
 * @param array $clean validated values for the fields on that form
 * @return string flash message
 */
function save_review_fields(array $driver, array $clean): string
{
    $changes = driver_diff($driver, $clean);
    $pending = pending_change_request((int)$driver['id']);
    if ($pending) {
        $onForm = array_intersect_key($pending['changes'], $clean);
        $changes = array_merge(
            // fields edited relative to what's on file...
            $changes,
            // ...plus pending fields the driver moved back to the value on file (withdrawals)
            array_filter(array_intersect_key($clean, $onForm), fn($val, $k) => (string)$val !== (string)$onForm[$k], ARRAY_FILTER_USE_BOTH)
        );
        // Nothing differs from the pending request → nothing to do.
        $new = array_filter($changes, fn($val, $k) => !array_key_exists($k, $pending['changes']) || (string)$pending['changes'][$k] !== (string)$val, ARRAY_FILTER_USE_BOTH);
        if (!$new) return 'No changes to save.';
    } elseif (!$changes) {
        return 'No changes to save.';
    }
    return submit_driver_review_changes($driver, $changes);
}

/** Driver row with any pending change-request values laid over it (to pre-fill review forms). */
function driver_with_pending(array $driver): array
{
    $p = pending_change_request((int)$driver['id']);
    return $p ? array_merge($driver, $p['changes']) : $driver;
}

/**
 * Admin decision on a change request.
 * @return array{0: bool, 1: string} [success, message]
 */
function review_change_request(int $requestId, int $driverId, int $adminId, bool $approve, ?string $note = null): array
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $s = $pdo->prepare("SELECT * FROM driver_change_requests WHERE id = ? AND driver_id = ? FOR UPDATE");
        $s->execute([$requestId, $driverId]);
        $req = $s->fetch();
        if (!$req || $req['status'] !== 'pending') {
            $pdo->rollBack();
            return [false, 'This request was not found or has already been handled.'];
        }

        if ($approve) {
            $changes = array_intersect_key(json_decode($req['changes'], true) ?: [], array_flip(driver_review_fields()));
            // Re-check uniqueness at approval time — another driver may have taken the plate since.
            if (isset($changes['plate_no'])) {
                $u = $pdo->prepare('SELECT 1 FROM drivers WHERE plate_no = ? AND id <> ?');
                $u->execute([$changes['plate_no'], $req['driver_id']]);
                if ($u->fetch()) {
                    $pdo->rollBack();
                    return [false, "Plate {$changes['plate_no']} now belongs to another driver. Reject this request instead."];
                }
            }
            update_driver((int)$req['driver_id'], $changes);
        }

        $pdo->prepare("UPDATE driver_change_requests SET status = ?, admin_note = ?, reviewed_by = ?, reviewed_at = NOW() WHERE id = ?")
            ->execute([$approve ? 'approved' : 'rejected', $note ?: null, $adminId, $requestId]);
        $pdo->commit();
        return [true, $approve ? 'Changes approved and applied to the driver profile.' : 'Change request rejected.'];
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}
