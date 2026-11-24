<?php
/**
 * Driver sign-up. New drivers start as "pending" and appear in the admin's
 * Pending approvals list until an admin approves them.
 */
require __DIR__ . '/../includes/driver_auth.php';
require __DIR__ . '/../includes/ui.php';

if (current_driver()) {
    redirect('driver/index.php');
}

$fields = array_merge(['name', 'ic_no', 'email', 'phone'], DRIVER_VEHICLE_FIELDS);
$v = array_fill_keys($fields, '');
$v['vehicle_type'] = 'car';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    [$clean, $errors] = validate_driver_input($_POST, $fields);
    $v = array_merge($v, array_map(fn($x) => $x ?? '', $clean));

    $pwError = validate_new_password((string)($_POST['password'] ?? ''), (string)($_POST['password_confirm'] ?? ''));
    if ($pwError) $errors['password'] = $pwError;
    if (empty($_POST['agree'])) $errors['agree'] = 'Please confirm your details are true.';

    if (!$errors) {
        $cols = array_keys($clean);
        $sql = 'INSERT INTO drivers (' . implode(', ', $cols) . ', password_hash, status) VALUES ('
            . implode(', ', array_fill(0, count($cols), '?')) . ', ?, \'pending\')';
        try {
            db()->prepare($sql)->execute([...array_values($clean), password_hash($_POST['password'], PASSWORD_DEFAULT)]);
            login_driver($clean['email'], $_POST['password']);
            flash('success', 'Welcome to Crab, ' . $clean['name'] . '! Your application has been sent for review.');
            redirect('driver/index.php');
        } catch (PDOException $e) {
            // Two people registering the same email/plate at the same moment.
            if ($e->getCode() === '23000') $errors['email'] = 'This email or plate number was just registered. Please check and try again.';
            else throw $e;
        }
    }
}
$minYear = (int)date('Y') - MAX_VEHICLE_AGE;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Become a Driver · <?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="<?= e(url('assets/css/admin.css')) ?>">
</head>
<body class="login-page">
    <div class="login-art" aria-hidden="true">
        <div class="login-art-inner">
            <?= crab_logo(56) ?>
            <h2>Start earning in 3 steps.</h2>
            <ol class="art-steps">
                <li>Tell us about you and your vehicle</li>
                <li>Our team verifies your licence and car</li>
                <li>Go online and accept your first ride</li>
            </ol>
        </div>
    </div>

    <main class="login-panel">
        <form class="login-card wide" method="post" novalidate>
            <a class="back-home" href="<?= e(url('index.php?as=driver')) ?>">&larr; Back to home</a>
            <div class="login-brand">
                <?= crab_logo(36) ?>
                <span class="brand-name"><?= e(strtolower(APP_NAME)) ?></span>
                <span class="brand-tag">Driver</span>
            </div>
            <h1>Become a Crab driver</h1>
            <p class="muted">Already registered? <a class="link" href="<?= e(url('driver/login.php')) ?>">Sign in</a></p>

            <?php if ($errors): ?>
                <div class="flash flash-error" role="alert">Please fix the highlighted fields below.</div>
            <?php endif; ?>
            <?= csrf_field() ?>

            <fieldset class="form-section">
                <legend>About you</legend>
                <div class="form-grid">
                    <?= form_field('name', 'Full name (as in MyKad)', $v['name'], $errors, ['required' => true, 'autocomplete' => 'name', 'class' => 'span-2']) ?>
                    <?= form_field('ic_no', 'IC number (MyKad)', $v['ic_no'], $errors, ['required' => true, 'placeholder' => '900101-14-5678']) ?>
                    <?= form_field('phone', 'Mobile number', $v['phone'], $errors, ['type' => 'tel', 'required' => true, 'placeholder' => '012-3456789', 'autocomplete' => 'tel']) ?>
                    <?= form_field('email', 'Email', $v['email'], $errors, ['type' => 'email', 'required' => true, 'autocomplete' => 'email', 'class' => 'span-2']) ?>
                </div>
            </fieldset>

            <fieldset class="form-section">
                <legend>Driving licence</legend>
                <div class="form-grid">
                    <?= form_field('license_no', 'Licence number', $v['license_no'], $errors, ['required' => true]) ?>
                    <?= form_field('license_expiry', 'Expiry date', $v['license_expiry'], $errors, ['type' => 'date', 'required' => true, 'attrs' => 'min="' . date('Y-m-d') . '"']) ?>
                </div>
            </fieldset>

            <fieldset class="form-section">
                <legend>Vehicle</legend>
                <div class="form-grid">
                    <?= form_field('vehicle_type', 'Service type', $v['vehicle_type'], $errors, ['type' => 'select', 'options' => VEHICLE_TYPES, 'required' => true]) ?>
                    <?= form_field('vehicle_model', 'Make & model', $v['vehicle_model'], $errors, ['required' => true, 'placeholder' => 'Perodua Myvi']) ?>
                    <?= form_field('plate_no', 'Plate number', $v['plate_no'], $errors, ['required' => true, 'placeholder' => 'WXY 1234']) ?>
                    <?= form_field('vehicle_color', 'Colour', $v['vehicle_color'], $errors, ['required' => true, 'placeholder' => 'Silver']) ?>
                    <?= form_field('vehicle_year', 'Year', $v['vehicle_year'], $errors, ['type' => 'number', 'required' => true, 'help' => "$minYear or newer", 'attrs' => 'min="' . $minYear . '" max="' . (date('Y') + 1) . '"']) ?>
                </div>
            </fieldset>

            <fieldset class="form-section">
                <legend>Password</legend>
                <div class="form-grid">
                    <?= form_field('password', 'Password', '', $errors, ['type' => 'password', 'required' => true, 'autocomplete' => 'new-password', 'help' => 'At least 8 characters, letters and numbers']) ?>
                    <?= form_field('password_confirm', 'Confirm password', '', $errors, ['type' => 'password', 'required' => true, 'autocomplete' => 'new-password']) ?>
                </div>
            </fieldset>

            <label class="check<?= isset($errors['agree']) ? ' has-error' : '' ?>">
                <input type="checkbox" name="agree" value="1"<?= !empty($_POST['agree']) ? ' checked' : '' ?>>
                <span>I confirm these details are true and agree to Crab's driver terms.</span>
            </label>
            <?php if (isset($errors['agree'])): ?><small class="field-error"><?= e($errors['agree']) ?></small><?php endif; ?>

            <button type="submit" class="btn btn-primary btn-block">Submit application</button>
        </form>
    </main>
</body>
</html>
