<?php
/**
 * Passenger sign-up. New passengers appear in the admin's Passengers list straight away.
 */
require __DIR__ . '/../includes/passenger_auth.php';
require __DIR__ . '/../includes/ui.php';

if (current_passenger()) {
    redirect('passenger/index.php');
}

$fields = ['name', 'email', 'phone'];
$v = array_fill_keys($fields, '');
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    [$clean, $errors] = validate_passenger_input($_POST, $fields);
    $v = array_merge($v, $clean);

    $pwError = validate_new_password((string)($_POST['password'] ?? ''), (string)($_POST['password_confirm'] ?? ''));
    if ($pwError) $errors['password'] = $pwError;
    if (empty($_POST['agree'])) $errors['agree'] = 'Please accept the terms to continue.';

    if (!$errors) {
        try {
            db()->prepare('INSERT INTO passengers (name, email, phone, password_hash) VALUES (?, ?, ?, ?)')
                ->execute([$clean['name'], $clean['email'], $clean['phone'], password_hash($_POST['password'], PASSWORD_DEFAULT)]);
            login_passenger($clean['email'], $_POST['password']);
            flash('success', 'Welcome to Crab, ' . strtok($clean['name'], ' ') . '! Add your Home and Work places to book faster.');
            redirect('passenger/index.php');
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') $errors['email'] = 'This email was just registered. Try signing in instead.';
            else throw $e;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Create account · <?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="<?= e(asset('assets/css/admin.css')) ?>">
    <?= theme_head() ?>
</head>
<body class="login-page">
    <div class="login-art" aria-hidden="true">
        <div class="login-art-inner">
            <?= crab_logo(56) ?>
            <h2>Your rides, your way.</h2>
            <ol class="art-steps">
                <li>Save your Home and Work addresses</li>
                <li>See your driver's photo, car and plate</li>
                <li>Rate every trip to keep Crab safe</li>
            </ol>
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
            <h1>Create your account</h1>
            <p class="muted">Already have one? <a class="link" href="<?= e(url('passenger/login.php')) ?>">Sign in</a></p>

            <?php if ($errors): ?>
                <div class="flash flash-error" role="alert">Please fix the highlighted fields below.</div>
            <?php endif; ?>
            <?= csrf_field() ?>

            <?= form_field('name', 'Full name', $v['name'], $errors, ['required' => true, 'autocomplete' => 'name']) ?>
            <?= form_field('email', 'Email', $v['email'], $errors, ['type' => 'email', 'required' => true, 'autocomplete' => 'email']) ?>
            <?= form_field('phone', 'Mobile number', $v['phone'], $errors, ['type' => 'tel', 'required' => true, 'placeholder' => '012-3456789', 'autocomplete' => 'tel', 'help' => 'Your driver will call this number if they can\'t find you.']) ?>
            <?= form_field('password', 'Password', '', $errors, ['type' => 'password', 'required' => true, 'autocomplete' => 'new-password', 'help' => 'At least 8 characters, letters and numbers']) ?>
            <?= form_field('password_confirm', 'Confirm password', '', $errors, ['type' => 'password', 'required' => true, 'autocomplete' => 'new-password']) ?>

            <label class="check<?= isset($errors['agree']) ? ' has-error' : '' ?>">
                <input type="checkbox" name="agree" value="1"<?= !empty($_POST['agree']) ? ' checked' : '' ?>>
                <span>I agree to Crab's terms of use and privacy policy.</span>
            </label>
            <?php if (isset($errors['agree'])): ?><small class="field-error"><?= e($errors['agree']) ?></small><?php endif; ?>

            <button type="submit" class="btn btn-primary btn-block">Create account</button>
        </form>
    </main>
<script src="<?= e(asset('assets/js/admin.js')) ?>"></script>
</body>
</html>
