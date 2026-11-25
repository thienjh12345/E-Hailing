<?php
/**
 * Crab — homepage with a single sign-in form for passengers, drivers and admins.
 *
 * Each role keeps its own session cookie (crab_passenger / crab_driver / crab_admin).
 * This page runs in a small "crab_site" session (for the CSRF token and login
 * throttling), then on a successful login switches into the chosen role's session
 * and uses that role's own login function, so behaviour matches the portal login pages.
 */

// Start the site session BEFORE the auth files, so they don't open their own sessions here.
session_name('crab_site');
session_start(['cookie_httponly' => true, 'cookie_samesite' => 'Lax']);

require __DIR__ . '/includes/admin_auth.php';
require __DIR__ . '/includes/driver_auth.php';
require __DIR__ . '/includes/passenger_auth.php';
require __DIR__ . '/includes/ui.php';

const ROLES = [
    'passenger' => ['label' => 'Passenger', 'session' => 'crab_passenger', 'home' => 'passenger/index.php', 'login' => 'login_passenger'],
    'driver'    => ['label' => 'Driver',    'session' => 'crab_driver',    'home' => 'driver/index.php',    'login' => 'login_driver'],
    'admin'     => ['label' => 'Admin',     'session' => 'crab_admin',     'home' => 'admin/index.php',     'login' => 'login_admin'],
];

/** Close the current session and open the named one (re-using its cookie if the browser has one). */
function switch_session(string $name): void
{
    session_write_close();
    session_name($name);
    $cookie = $_COOKIE[$name] ?? '';
    session_id(preg_match('/^[a-zA-Z0-9,-]{22,256}$/', $cookie) ? $cookie : session_create_id());
    session_start(['cookie_httponly' => true, 'cookie_samesite' => 'Lax']);
}

$role = $_POST['role'] ?? $_GET['as'] ?? 'passenger';
if (!isset(ROLES[$role])) $role = 'passenger';
$email = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $email    = trim((string)($_POST['email'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    $tries = $_SESSION['login_tries'] ?? ['count' => 0, 'at' => 0];
    if ($tries['count'] >= 5 && time() - $tries['at'] < 60) {
        $error = 'Too many attempts. Please wait a minute and try again.';
    } elseif ($email === '' || $password === '') {
        $error = 'Please enter your email and password.';
    } else {
        // Check the password first (no session change needed for a wrong password).
        $table = ['passenger' => 'passengers', 'driver' => 'drivers', 'admin' => 'admins'][$role];
        $s = db()->prepare("SELECT password_hash FROM $table WHERE email = ?");
        $s->execute([strtolower($email)]);
        $hash = $s->fetchColumn();

        if ($hash && password_verify($password, $hash)) {
            unset($_SESSION['login_tries']);
            switch_session(ROLES[$role]['session']);
            ROLES[$role]['login']($email, $password); // regenerates the role session id + stores the user
            redirect(ROLES[$role]['home']);
        }
        $_SESSION['login_tries'] = ['count' => $tries['count'] + 1, 'at' => time()];
        $error = 'Incorrect email or password for a ' . strtolower(ROLES[$role]['label']) . ' account.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e(APP_NAME) ?> · Rides across Malaysia</title>
    <meta name="description" content="Crab e-hailing — book rides, drive with Crab, or manage the platform.">
    <link rel="stylesheet" href="<?= e(asset('assets/css/admin.css')) ?>">
    <?= theme_head() ?>
</head>
<body class="login-page home-page">
    <div class="login-art">
        <div class="login-art-inner">
            <div class="home-brand"><?= crab_logo(48) ?><span>crab</span></div>
            <h2>Get there, the Crab way.</h2>
            <p>Safe, affordable rides across Malaysia — from the Klang Valley to Kota Kinabalu — with verified drivers you can see, track and rate.</p>
            <ul class="home-points">
                <li><?= icon('check', 18) ?> Every driver's licence and vehicle checked by our team</li>
                <li><?= icon('check', 18) ?> See your driver's photo, car and plate before pick-up</li>
                <li><?= icon('check', 18) ?> Pay by cash, card or CrabPay wallet</li>
            </ul>
        </div>
    </div>

    <main class="login-panel">
        <?= theme_toggle('floating') ?>
        <form class="login-card" method="post" novalidate>
            <div class="login-brand">
                <?= crab_logo(36) ?>
                <span class="brand-name"><?= e(strtolower(APP_NAME)) ?></span>
            </div>
            <h1>Sign in</h1>
            <p class="muted">Choose how you use Crab, then sign in.</p>

            <?= csrf_field() ?>
            <fieldset class="role-switch">
                <legend class="sr-only">I am signing in as</legend>
                <?php foreach (ROLES as $key => $r): ?>
                    <input type="radio" name="role" id="role_<?= $key ?>" value="<?= $key ?>"<?= $role === $key ? ' checked' : '' ?>>
                    <label for="role_<?= $key ?>"><?= icon(['passenger' => 'user', 'driver' => 'rides', 'admin' => 'dashboard'][$key], 16) ?> <?= e($r['label']) ?></label>
                <?php endforeach; ?>
            </fieldset>

            <?php if ($error): ?>
                <div class="flash flash-error" role="alert"><?= e($error) ?></div>
            <?php endif; ?>

            <label class="field">
                <span>Email</span>
                <input type="email" name="email" value="<?= e($email) ?>" autocomplete="username" required autofocus>
            </label>
            <label class="field">
                <span>Password</span>
                <input type="password" name="password" autocomplete="current-password" required>
            </label>
            <button type="submit" class="btn btn-primary btn-block">Sign in</button>

            <div class="signup-links">
                <a class="signup-link" href="<?= e(url('passenger/register.php')) ?>">
                    <?= icon('user', 18) ?><span><strong>New passenger?</strong><small class="muted">Create an account</small></span>
                </a>
                <a class="signup-link" href="<?= e(url('driver/register.php')) ?>">
                    <?= icon('rides', 18) ?><span><strong>Want to drive?</strong><small class="muted">Become a Crab driver</small></span>
                </a>
            </div>
        </form>
    </main>
<script src="<?= e(asset('assets/js/admin.js')) ?>"></script>
</body>
</html>
