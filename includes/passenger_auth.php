<?php
/**
 * Bootstrap for every passenger portal page: session, DB, helpers, and the login guard.
 * Passengers have their own session cookie ("crab_passenger"), separate from admins and drivers.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_name('crab_passenger');
    session_start([
        'cookie_httponly' => true,
        'cookie_samesite' => 'Lax',
    ]);
}

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/passenger_profile.php';

/** Logged-in passenger's row, re-read each request so admin suspensions apply immediately. */
function current_passenger(bool $refresh = false): ?array
{
    static $p = false;
    if ($p === false || $refresh) {
        $id = $_SESSION['passenger_id'] ?? null;
        $p = $id ? find_passenger((int)$id) : null;
        if ($id && !$p) unset($_SESSION['passenger_id']);
    }
    return $p;
}

function require_passenger(): array
{
    $p = current_passenger();
    if (!$p) redirect('index.php?as=passenger');
    return $p;
}

function login_passenger(string $email, string $password): bool
{
    $s = db()->prepare('SELECT id, password_hash FROM passengers WHERE email = ?');
    $s->execute([strtolower(trim($email))]);
    $row = $s->fetch();
    if (!$row || !password_verify($password, $row['password_hash'])) return false;

    return passenger_sign_in((int)$row['id']);
}

/** Start the passenger session for an account whose password has already been checked. */
function passenger_sign_in(int $id): bool
{
    session_regenerate_id(true);
    $_SESSION['passenger_id'] = $id;
    // updated_at = updated_at stops the login from counting as a "profile update".
    db()->prepare('UPDATE passengers SET last_login_at = NOW(), updated_at = updated_at WHERE id = ?')->execute([$id]);
    return true;
}

function logout_passenger(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}
