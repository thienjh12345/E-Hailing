<?php
require __DIR__ . '/../includes/passenger_auth.php';
require __DIR__ . '/../includes/passenger_layout.php';
$p = require_passenger();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $s = db()->prepare('SELECT password_hash FROM passengers WHERE id = ?');
    $s->execute([$p['id']]);
    $hash = $s->fetchColumn();

    $current = (string)($_POST['current_password'] ?? '');
    $new     = (string)($_POST['new_password'] ?? '');
    $confirm = (string)($_POST['new_password_confirm'] ?? '');

    if (!password_verify($current, $hash)) {
        $errors['current_password'] = 'That isn\'t your current password.';
    } elseif ($err = validate_new_password($new, $confirm)) {
        $errors['new_password'] = $err;
    } elseif (password_verify($new, $hash)) {
        $errors['new_password'] = 'Choose a different password from your current one.';
    } else {
        db()->prepare('UPDATE passengers SET password_hash = ? WHERE id = ?')->execute([password_hash($new, PASSWORD_DEFAULT), $p['id']]);
        session_regenerate_id(true);
        flash('success', 'Password changed.');
        redirect('passenger/password.php');
    }
}

passenger_header('Password', 'security');
?>

<section class="grid-main">
    <form class="card narrow" method="post" novalidate>
        <?= csrf_field() ?>
        <header class="card-head"><div><h2>Change password</h2><p class="muted">Use at least 8 characters with letters and numbers.</p></div><?= icon('lock') ?></header>
        <div class="form-grid one">
            <?= form_field('current_password', 'Current password', '', $errors, ['type' => 'password', 'required' => true, 'autocomplete' => 'current-password']) ?>
            <?= form_field('new_password', 'New password', '', $errors, ['type' => 'password', 'required' => true, 'autocomplete' => 'new-password']) ?>
            <?= form_field('new_password_confirm', 'Confirm new password', '', $errors, ['type' => 'password', 'required' => true, 'autocomplete' => 'new-password']) ?>
        </div>
        <div class="form-actions"><button class="btn btn-primary">Change password</button></div>
    </form>
</section>

<?php passenger_footer(); ?>
