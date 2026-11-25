<?php
require __DIR__ . '/../includes/passenger_auth.php';
require __DIR__ . '/../includes/ui.php';

if (current_passenger()) {
    redirect('passenger/index.php');
}

$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $email    = trim((string)($_POST['email'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    $tries = $_SESSION['login_tries'] ?? ['count' => 0, 'at' => 0];
    if ($tries['count'] >= 5 && time() - $tries['at'] < 60) {
        $error = 'Too many attempts. Please wait a minute and try again.';
    } elseif ($email === '' || $password === '') {
        $error = 'Please enter your email and password.';
    } elseif (login_passenger($email, $password)) {
        unset($_SESSION['login_tries']);
        redirect('passenger/index.php');
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
    <title>Sign in · <?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="<?= e(asset('assets/css/admin.css')) ?>">
    <?= theme_head() ?>
</head>
<body class="login-page">
    <div class="login-art" aria-hidden="true">
        <div class="login-art-inner">
            <?= crab_logo(56) ?>
            <h2>Get there with Crab.</h2>
            <p>Safe, affordable rides across Malaysia — with drivers you can see and rate.</p>
        </div>
    </div>

    <main class="login-panel">
        <?= theme_toggle('floating') ?>
        <form class="login-card" method="post" novalidate>
            <a class="back-home" href="<?= e(url('index.php?as=passenger')) ?>">&larr; Back to home</a>
            <div class="login-brand">
                <?= crab_logo(36) ?>
                <span class="brand-name"><?= e(strtolower(APP_NAME)) ?></span>
            </div>
            <h1>Sign in</h1>
            <p class="muted">Welcome back! Sign in to manage your rides and profile.</p>

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
            <p class="login-alt">New to Crab? <a class="link" href="<?= e(url('passenger/register.php')) ?>">Create an account</a></p>
            <p class="login-alt small">Want to drive? <a class="link" href="<?= e(url('driver/register.php')) ?>">Become a Crab driver</a></p>
        </form>
    </main>
<script src="<?= e(asset('assets/js/admin.js')) ?>"></script>
</body>
</html>
