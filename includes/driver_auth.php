<?php
/**
 * Bootstrap for every driver portal page: session, DB, helpers, and the login guard.
 *
 * Drivers use their own session cookie ("crab_driver"), separate from the admin one,
 * so an admin and a driver can be logged in on the same browser while testing.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_name('crab_driver');
    session_start([
        'cookie_httponly' => true,
        'cookie_samesite' => 'Lax',
    ]);
}

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/driver_profile.php';

/**
 * The logged-in driver's full row, re-read from the database on every request
 * so admin actions (approve, suspend, approved changes) show up immediately.
 */
function current_driver(bool $refresh = false): ?array
{
    static $driver = false;
    if ($driver === false || $refresh) {
        $id = $_SESSION['driver_id'] ?? null;
        $driver = $id ? find_driver((int)$id) : null;
        if ($id && !$driver) {
            unset($_SESSION['driver_id']); // account was deleted
        }
    }
    return $driver;
}

function require_driver(): array
{
    $d = current_driver();
    if (!$d) {
        redirect('index.php?as=driver');
    }
    return $d;
}

function login_driver(string $email, string $password): bool
{
    $s = db()->prepare('SELECT id, password_hash FROM drivers WHERE email = ?');
    $s->execute([strtolower(trim($email))]);
    $row = $s->fetch();
    if (!$row || !password_verify($password, $row['password_hash'])) {
        return false;
    }
    return driver_sign_in((int)$row['id']);
}

/** Start the driver session for an account whose password has already been checked. */
function driver_sign_in(int $id): bool
{
    session_regenerate_id(true);
    $_SESSION['driver_id'] = $id;
    return true;
}

function logout_driver(): void
{
    // Going off duty when logging out.
    if ($id = $_SESSION['driver_id'] ?? null) {
        db()->prepare('UPDATE drivers SET is_online = 0, updated_at = updated_at WHERE id = ?')->execute([$id]);
    }
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}
