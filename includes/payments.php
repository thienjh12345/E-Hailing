<?php
/**
 * Payments for Crab — shared by the passenger, driver and admin portals.
 *
 *  Cash   : the driver collects it when the trip ends.
 *  Card   : charged automatically when the trip ends (simulated gateway — always approves).
 *  Wallet : CrabPay balance, deducted when the trip ends. If the balance is too low by then,
 *           the payment falls back to cash so the driver still gets paid.
 *
 * Every trip has exactly one row in `payments` (created at booking, settled at the end).
 * Card numbers are never stored: only the brand, last 4 digits and expiry.
 */

const PAYMENT_LABELS = ['cash' => 'Cash', 'card' => 'Card', 'wallet' => 'CrabPay wallet'];
const WALLET_TOPUP_MIN = 5;
const WALLET_TOPUP_MAX = 500;
const MAX_SAVED_CARDS = 5;

/* ---------------------------------------------------------------------------
 * Cards
 * ------------------------------------------------------------------------- */

function passenger_cards(int $passengerId): array
{
    $s = db()->prepare('SELECT * FROM payment_cards WHERE passenger_id = ? ORDER BY is_default DESC, id DESC');
    $s->execute([$passengerId]);
    return $s->fetchAll();
}

function find_card(int $cardId, int $passengerId): ?array
{
    $s = db()->prepare('SELECT * FROM payment_cards WHERE id = ? AND passenger_id = ?');
    $s->execute([$cardId, $passengerId]);
    return $s->fetch() ?: null;
}

function card_label(array $c): string
{
    return $c['brand'] . ' •••• ' . $c['last4'];
}

function card_expired(array $c): bool
{
    return ((int)$c['exp_year'] * 100 + (int)$c['exp_month']) < (int)date('Ym');
}

/** Luhn checksum — catches most typos in card numbers. */
function luhn_valid(string $digits): bool
{
    $sum = 0;
    $alt = false;
    for ($i = strlen($digits) - 1; $i >= 0; $i--) {
        $n = (int)$digits[$i];
        if ($alt) { $n *= 2; if ($n > 9) $n -= 9; }
        $sum += $n;
        $alt = !$alt;
    }
    return $sum % 10 === 0;
}

function card_brand(string $digits): ?string
{
    if (preg_match('/^4/', $digits)) return 'Visa';
    if (preg_match('/^(5[1-5]|2(2[2-9]|[3-6]\d|7[01]|720))/', $digits)) return 'Mastercard';
    if (preg_match('/^3[47]/', $digits)) return 'Amex';
    return null;
}

/**
 * Validate a new card. Returns [clean, errors]; clean has brand/last4/exp/holder only.
 * The full number and CVV are checked and then thrown away.
 */
function validate_card(array $in): array
{
    $errors = [];
    $holder = strtoupper(preg_replace('/\s+/', ' ', trim((string)($in['holder_name'] ?? ''))));
    $number = preg_replace('/[\s-]/', '', (string)($in['card_number'] ?? ''));
    $expiry = trim((string)($in['expiry'] ?? ''));
    $cvv = trim((string)($in['cvv'] ?? ''));

    if (mb_strlen($holder) < 3 || mb_strlen($holder) > 100 || !preg_match("/^[\p{L} .'\/@-]+$/u", $holder)) $errors['holder_name'] = 'Enter the name exactly as on the card.';
    $brand = ctype_digit($number) ? card_brand($number) : null;
    if (!ctype_digit($number) || strlen($number) < 13 || strlen($number) > 19 || !luhn_valid($number)) $errors['card_number'] = 'This card number isn’t valid. Check it and try again.';
    elseif (!$brand) $errors['card_number'] = 'We accept Visa, Mastercard and Amex.';

    $m = $y = 0;
    if (!preg_match('/^(0[1-9]|1[0-2])\s*\/\s*(\d{2})$/', $expiry, $mm)) {
        $errors['expiry'] = 'Use MM/YY, e.g. 08/28.';
    } else {
        $m = (int)$mm[1];
        $y = 2000 + (int)$mm[2];
        if ($y * 100 + $m < (int)date('Ym')) $errors['expiry'] = 'This card has expired.';
        elseif ($y > (int)date('Y') + 20) $errors['expiry'] = 'Check the expiry year.';
    }
    $cvvLen = $brand === 'Amex' ? 4 : 3;
    if (!preg_match('/^\d{' . $cvvLen . '}$/', $cvv)) $errors['cvv'] = "Enter the $cvvLen-digit security code.";

    return [['brand' => $brand, 'last4' => substr($number, -4), 'exp_month' => $m, 'exp_year' => $y, 'holder_name' => $holder], $errors];
}

/* ---------------------------------------------------------------------------
 * CrabPay wallet
 * ------------------------------------------------------------------------- */

function wallet_balance(int $passengerId): float
{
    $s = db()->prepare('SELECT wallet_balance FROM passengers WHERE id = ?');
    $s->execute([$passengerId]);
    return (float)$s->fetchColumn();
}

/**
 * Add (+) or remove (-) money and record it. Call INSIDE a transaction.
 * Returns the new balance, or null if a deduction would make the balance negative.
 */
function wallet_apply(int $passengerId, float $amount, string $type, string $description, ?int $rideId = null): ?float
{
    $pdo = db();
    $s = $pdo->prepare('SELECT wallet_balance FROM passengers WHERE id = ? FOR UPDATE');   // lock the row
    $s->execute([$passengerId]);
    $balance = round((float)$s->fetchColumn() + $amount, 2);
    if ($balance < 0) return null;
    // updated_at = updated_at: wallet activity isn't a profile edit
    $pdo->prepare('UPDATE passengers SET wallet_balance = ?, updated_at = updated_at WHERE id = ?')->execute([$balance, $passengerId]);
    $pdo->prepare('INSERT INTO wallet_transactions (passenger_id, type, amount, balance_after, ride_id, description) VALUES (?, ?, ?, ?, ?, ?)')
        ->execute([$passengerId, $type, $amount, $balance, $rideId, $description]);
    return $balance;
}

function wallet_history(int $passengerId, int $limit = 50): array
{
    $s = db()->prepare("SELECT * FROM wallet_transactions WHERE passenger_id = ? ORDER BY created_at DESC, id DESC LIMIT $limit");
    $s->execute([$passengerId]);
    return $s->fetchAll();
}

/* ---------------------------------------------------------------------------
 * Trip payments
 * ------------------------------------------------------------------------- */

function payment_reference(int $rideId): string
{
    return 'CRB' . date('ymd') . str_pad((string)$rideId, 7, '0', STR_PAD_LEFT);
}

function payment_for_ride(int $rideId): ?array
{
    $s = db()->prepare('SELECT p.*, c.brand, c.last4, a.name AS refunded_by_name FROM payments p
                        LEFT JOIN payment_cards c ON c.id = p.card_id LEFT JOIN admins a ON a.id = p.refunded_by
                        WHERE p.ride_id = ?');
    $s->execute([$rideId]);
    return $s->fetch() ?: null;
}

/** "Card • Visa •••• 4242", "CrabPay wallet", "Cash". */
function payment_method_label(array $p): string
{
    if ($p['method'] === 'card' && !empty($p['last4'])) return 'Card · ' . $p['brand'] . ' •••• ' . $p['last4'];
    return PAYMENT_LABELS[$p['method']] ?? $p['method'];
}

/** Create the pending payment when a trip is booked. Call inside the booking transaction. */
function payment_create(int $rideId, int $passengerId, string $method, float $amount, ?int $cardId): void
{
    db()->prepare('INSERT INTO payments (ride_id, passenger_id, method, card_id, amount, reference) VALUES (?, ?, ?, ?, ?, ?)')
        ->execute([$rideId, $passengerId, $method, $cardId, $amount, payment_reference($rideId)]);
}

/**
 * Settle the payment when the driver completes the trip. Call inside the completion transaction.
 * Returns a message for the driver (e.g. "Collect RM 12.50 in cash").
 */
function payment_settle(array $ride): string
{
    $pdo = db();
    $s = $pdo->prepare('SELECT * FROM payments WHERE ride_id = ? FOR UPDATE');
    $s->execute([$ride['id']]);
    $p = $s->fetch();
    if (!$p) {   // trips created before payments existed
        payment_create((int)$ride['id'], (int)$ride['passenger_id'], $ride['payment_method'], (float)$ride['fare'], null);
        $s->execute([$ride['id']]);
        $p = $s->fetch();
    }
    if ($p['status'] !== 'pending') return 'Payment already handled.';
    $amount = money($p['amount']);

    if ($p['method'] === 'wallet') {
        $left = wallet_apply((int)$p['passenger_id'], -(float)$p['amount'], 'ride',
            'Trip #' . $ride['id'] . ' · ' . $ride['pickup_address'] . ' → ' . $ride['dropoff_address'], (int)$ride['id']);
        if ($left === null) {
            // Not enough balance any more: switch to cash so the driver still gets paid.
            $pdo->prepare("UPDATE payments SET method = 'cash', status = 'paid', paid_at = NOW(), note = 'CrabPay balance was too low — paid in cash instead' WHERE id = ?")->execute([$p['id']]);
            $pdo->prepare("UPDATE rides SET payment_method = 'cash' WHERE id = ?")->execute([$ride['id']]);
            return "The passenger's wallet balance was too low — please collect $amount in cash.";
        }
        $pdo->prepare("UPDATE payments SET status = 'paid', paid_at = NOW() WHERE id = ?")->execute([$p['id']]);
        return "$amount paid from the passenger's CrabPay wallet.";
    }

    $pdo->prepare("UPDATE payments SET status = 'paid', paid_at = NOW() WHERE id = ?")->execute([$p['id']]);
    return $p['method'] === 'card' ? "$amount charged to the passenger's card." : "Collect $amount in cash from the passenger.";
}

/** Trip cancelled before it ended: nothing is charged. */
function payment_cancel(int $rideId): void
{
    db()->prepare("UPDATE payments SET status = 'cancelled' WHERE ride_id = ? AND status = 'pending'")->execute([$rideId]);
}

/**
 * Admin refund of a paid trip. The money goes to the passenger's CrabPay wallet
 * (instant, works for every payment method).
 * @return array{0: bool, 1: string}
 */
function payment_refund(int $rideId, int $adminId, string $reason): array
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $s = $pdo->prepare('SELECT * FROM payments WHERE ride_id = ? FOR UPDATE');
        $s->execute([$rideId]);
        $p = $s->fetch();
        if (!$p || $p['status'] !== 'paid') {
            $pdo->rollBack();
            return [false, 'Only paid trips can be refunded.'];
        }
        wallet_apply((int)$p['passenger_id'], (float)$p['amount'], 'refund',
            'Refund for trip #' . $rideId . ($reason !== '' ? ' — ' . $reason : ''), $rideId);
        $pdo->prepare("UPDATE payments SET status = 'refunded', refunded_at = NOW(), refunded_by = ?, note = ? WHERE id = ?")
            ->execute([$adminId, $reason !== '' ? $reason : null, $p['id']]);
        $pdo->commit();
        return [true, money($p['amount']) . ' refunded to the passenger\'s CrabPay wallet.'];
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/** Status pill for a payment, with friendly wording. */
function payment_badge(?array $p): string
{
    if (!$p) return '<span class="muted">—</span>';
    $map = ['pending' => ['pending', 'Pending'], 'paid' => ['completed', 'Paid'], 'failed' => ['suspended', 'Failed'],
            'refunded' => ['accepted', 'Refunded'], 'cancelled' => ['cancelled', 'Not charged']];
    [$cls, $label] = $map[$p['status']] ?? ['cancelled', ucfirst($p['status'])];
    return '<span class="badge badge-' . $cls . '">' . e($label) . '</span>';
}
