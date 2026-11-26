<?php
/**
 * Crab — homepage with ONE sign-in form for passengers, drivers and admins.
 *
 * The user just enters email + password; the system works out the role:
 *  - the email/password is checked against admins, drivers and passengers;
 *  - one match  → signed in to that portal and sent to its dashboard;
 *  - two or more (the same email + password used for e.g. a driver AND a passenger
 *    account) → a quick "Continue as …" choice, so neither account is hidden;
 *  - no match   → "Incorrect email or password" (it never says which part was wrong).
 *
 * Each role keeps its own session cookie (crab_passenger / crab_driver / crab_admin).
 * This page runs in a small "crab_site" session (CSRF token, login throttling, pending
 * choice), then switches into the right role's session to sign the user in.
 */

// Start the site session BEFORE the auth files, so they don't open their own sessions here.
session_name('crab_site');
session_start(['cookie_httponly' => true, 'cookie_samesite' => 'Lax']);

require __DIR__ . '/includes/admin_auth.php';
require __DIR__ . '/includes/driver_auth.php';
require __DIR__ . '/includes/passenger_auth.php';
require __DIR__ . '/includes/ui.php';

const ROLES = [
    'admin'     => ['label' => 'Admin',     'table' => 'admins',     'session' => 'crab_admin',     'home' => 'admin/index.php',     'sign_in' => 'admin_sign_in',     'icon' => 'dashboard', 'desc' => 'Crab admin console'],
    'driver'    => ['label' => 'Driver',    'table' => 'drivers',    'session' => 'crab_driver',    'home' => 'driver/index.php',    'sign_in' => 'driver_sign_in',    'icon' => 'rides',     'desc' => 'Take ride requests and see your earnings'],
    'passenger' => ['label' => 'Passenger', 'table' => 'passengers', 'session' => 'crab_passenger', 'home' => 'passenger/index.php', 'sign_in' => 'passenger_sign_in', 'icon' => 'user',      'desc' => 'Book rides and manage your trips'],
];
const CHOICE_MINUTES = 5;   // how long the "Continue as …" choice stays valid

/** Close the current session and open the named one (re-using its cookie if the browser has one). */
function switch_session(string $name): void
{
    session_write_close();
    session_name($name);
    $cookie = $_COOKIE[$name] ?? '';
    session_id(preg_match('/^[a-zA-Z0-9,-]{22,256}$/', $cookie) ? $cookie : session_create_id());
    session_start(['cookie_httponly' => true, 'cookie_samesite' => 'Lax']);
}

/** Every role whose account has this email AND this password: [role => account id]. */
function matching_accounts(string $email, string $password): array
{
    $found = [];
    foreach (ROLES as $role => $r) {
        $s = db()->prepare("SELECT id, password_hash FROM {$r['table']} WHERE email = ?");
        $s->execute([strtolower($email)]);
        $row = $s->fetch();
        if ($row && password_verify($password, $row['password_hash'])) $found[$role] = (int)$row['id'];
    }
    return $found;
}

/** Sign in to one role's portal and go to its dashboard. */
function sign_in_as(string $role, int $id): void
{
    unset($_SESSION['login_tries'], $_SESSION['login_choice']);
    switch_session(ROLES[$role]['session']);
    ROLES[$role]['sign_in']($id);   // regenerates that portal's session id and stores the user
    redirect(ROLES[$role]['home']);
}

$email = '';
$error = '';
$choice = null;   // accounts to choose from, when one email belongs to several roles

if (isset($_GET['different'])) {
    unset($_SESSION['login_choice']);
    redirect('index.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    if (($_POST['action'] ?? '') === 'choose') {
        // Second step: the user picked which account to use
        $pending = $_SESSION['login_choice'] ?? null;
        $role = (string)($_POST['role'] ?? '');
        if ($pending && time() - $pending['at'] <= CHOICE_MINUTES * 60 && isset($pending['accounts'][$role], ROLES[$role])) {
            sign_in_as($role, $pending['accounts'][$role]);
        }
        unset($_SESSION['login_choice']);
        $error = 'Please sign in again.';   // choice expired, or no valid choice was pending
    } else {
        $email    = trim((string)($_POST['email'] ?? ''));
        $password = (string)($_POST['password'] ?? '');

        $tries = $_SESSION['login_tries'] ?? ['count' => 0, 'at' => 0];
        if ($tries['count'] >= 5 && time() - $tries['at'] < 60) {
            $error = 'Too many attempts. Please wait a minute and try again.';
        } elseif ($email === '' || $password === '') {
            $error = 'Please enter your email and password.';
        } else {
            $accounts = matching_accounts($email, $password);
            if (count($accounts) === 1) {
                sign_in_as(array_key_first($accounts), reset($accounts));
            } elseif (count($accounts) > 1) {
                unset($_SESSION['login_tries']);
                $_SESSION['login_choice'] = ['accounts' => $accounts, 'email' => strtolower($email), 'at' => time()];
                $choice = $_SESSION['login_choice'];
            } else {
                $_SESSION['login_tries'] = ['count' => $tries['count'] + 1, 'at' => time()];
                $error = 'Incorrect email or password.';
            }
        }
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
        <?php if ($choice): ?>
            <form class="login-card" method="post">
                <div class="login-brand">
                    <?= crab_logo(36) ?>
                    <span class="brand-name"><?= e(strtolower(APP_NAME)) ?></span>
                </div>
                <h1>Continue as…</h1>
                <p class="muted"><strong><?= e($choice['email']) ?></strong> has more than one Crab account. Which one would you like to use?</p>
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="choose">
                <div class="account-choices">
                    <?php foreach (ROLES as $role => $r): if (!isset($choice['accounts'][$role])) continue; ?>
                        <button class="account-choice" name="role" value="<?= e($role) ?>">
                            <span class="account-icon"><?= icon($r['icon'], 20) ?></span>
                            <span><strong><?= e($r['label']) ?></strong><small class="muted"><?= e($r['desc']) ?></small></span>
                            <?= icon('arrow', 16) ?>
                        </button>
                    <?php endforeach; ?>
                </div>
                <p class="login-alt"><a class="link" href="<?= e(url('index.php?different=1')) ?>">Sign in with a different email</a></p>
            </form>
        <?php else: ?>
            <form class="login-card" method="post" novalidate>
                <div class="login-brand">
                    <?= crab_logo(36) ?>
                    <span class="brand-name"><?= e(strtolower(APP_NAME)) ?></span>
                </div>
                <h1>Sign in</h1>
                <p class="muted">Passengers, drivers and admins all sign in here — we'll take you to the right place.</p>

                <?= csrf_field() ?>
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
        <?php endif; ?>
    </main>
<script src="<?= e(asset('assets/js/admin.js')) ?>"></script>
</body>
</html>
