<?php
/**
 * Bootstrap for every admin page: session, DB, helpers, and the login guard.
 *
 * Pages that need a logged-in admin:   require __DIR__ . '/../includes/admin_auth.php'; require_admin();
 */

if (session_status() === PHP_SESSION_NONE) {
    session_name('crab_admin');
    session_start([
        'cookie_httponly' => true,
        'cookie_samesite' => 'Lax',
    ]);
}

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

function current_admin(): ?array
{
    return $_SESSION['admin'] ?? null;
}

function require_admin(): array
{
    $admin = current_admin();
    if (!$admin) {
        redirect('index.php?as=admin');
    }
    return $admin;
}

function login_admin(string $email, string $password): bool
{
    $stmt = db()->prepare('SELECT id, name, email, password_hash FROM admins WHERE email = ?');
    $stmt->execute([$email]);
    $row = $stmt->fetch();

    if (!$row || !password_verify($password, $row['password_hash'])) {
        return false;
    }

    session_regenerate_id(true); // prevent session fixation
    $_SESSION['admin'] = ['id' => $row['id'], 'name' => $row['name'], 'email' => $row['email']];
    db()->prepare('UPDATE admins SET last_login_at = NOW() WHERE id = ?')->execute([$row['id']]);
    return true;
}

function logout_admin(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}
