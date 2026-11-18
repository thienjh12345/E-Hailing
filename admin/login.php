<?php
require __DIR__ . '/../includes/admin_auth.php';
require __DIR__ . '/../includes/admin_layout.php'; // for crab_logo()

if (current_admin()) {
    redirect('admin/index.php');
}

$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $email    = trim((string)($_POST['email'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    // Basic brute-force slow-down: lock for 60s after 5 failed tries in this session.
    $tries = $_SESSION['login_tries'] ?? ['count' => 0, 'at' => 0];
    if ($tries['count'] >= 5 && time() - $tries['at'] < 60) {
        $error = 'Too many attempts. Please wait a minute and try again.';
    } elseif ($email === '' || $password === '') {
        $error = 'Please enter your email and password.';
    } elseif (login_admin($email, $password)) {
        unset($_SESSION['login_tries']);
        redirect('admin/index.php');
    } else {
        $_SESSION['login_tries'] = ['count' => $tries['count'] + 1, 'at' => time()];
        $error = 'Incorrect email or password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin Login · <?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="<?= e(url('assets/css/admin.css')) ?>">
</head>
<body class="login-page">
    <div class="login-art" aria-hidden="true">
        <div class="login-art-inner">
            <?= crab_logo(56) ?>
            <h2>Every ride, at a glance.</h2>
            <p>Monitor trips, approve drivers and keep <?= e(APP_NAME) ?> moving — all from one console.</p>
        </div>
    </div>

    <main class="login-panel">
        <form class="login-card" method="post" novalidate>
            <div class="login-brand">
                <?= crab_logo(36) ?>
                <span class="brand-name"><?= e(strtolower(APP_NAME)) ?></span>
                <span class="brand-tag">Admin</span>
            </div>
            <h1>Sign in</h1>
            <p class="muted">Use your administrator account to continue.</p>

            <?php if ($error): ?>
                <div class="flash flash-error" role="alert"><?= e($error) ?></div>
            <?php endif; ?>

            <?= csrf_field() ?>
            <label class="field">
                <span>Email</span>
                <input type="email" name="email" value="<?= e($email) ?>" autocomplete="username" required autofocus>
            </label>
            <label class="field">
                <span>Password</span>
                <input type="password" name="password" autocomplete="current-password" required>
            </label>
            <button type="submit" class="btn btn-primary btn-block">Sign in</button>
        </form>
    </main>
</body>
</html>
