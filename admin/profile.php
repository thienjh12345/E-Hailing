<?php
/**
 * Admin → Settings → Profile: the signed-in admin's own name and email.
 */
require __DIR__ . '/../includes/admin_auth.php';
require __DIR__ . '/../includes/admin_layout.php';
require __DIR__ . '/../includes/validation.php';
$admin = require_admin();

$s = db()->prepare('SELECT id, name, email, password_hash, last_login_at, created_at FROM admins WHERE id = ?');
$s->execute([$admin['id']]);
$me = $s->fetch();
if (!$me) {            // account removed while signed in
    logout_admin();
    redirect('index.php?as=admin');
}

$v = ['name' => $me['name'], 'email' => $me['email']];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $v['name']  = preg_replace('/\s+/', ' ', trim((string)($_POST['name'] ?? '')));
    $v['email'] = strtolower(trim((string)($_POST['email'] ?? '')));

    if ($err = validate_person_name($v['name'])) $errors['name'] = $err;
    if (!filter_var($v['email'], FILTER_VALIDATE_EMAIL) || strlen($v['email']) > 150) {
        $errors['email'] = 'Enter a valid email address.';
    } else {
        $u = db()->prepare('SELECT 1 FROM admins WHERE email = ? AND id <> ?');
        $u->execute([$v['email'], $me['id']]);
        if ($u->fetch()) $errors['email'] = 'Another admin already uses this email.';
    }
    // Changing the sign-in email needs the current password.
    if (!$errors && $v['email'] !== $me['email'] && !password_verify((string)($_POST['current_password'] ?? ''), $me['password_hash'])) {
        $errors['current_password'] = 'Enter your current password to change your email.';
    }

    if (!$errors) {
        if ($v['name'] !== $me['name'] || $v['email'] !== $me['email']) {
            db()->prepare('UPDATE admins SET name = ?, email = ? WHERE id = ?')->execute([$v['name'], $v['email'], $me['id']]);
            // The sidebar reads the name/email from the session — keep it in sync.
            $_SESSION['admin']['name']  = $v['name'];
            $_SESSION['admin']['email'] = $v['email'];
            flash('success', 'Profile saved.');
        } else {
            flash('success', 'No changes to save.');
        }
        redirect('admin/profile.php');
    }
}

admin_header('Settings', 'settings');
?>

<?= settings_tabs('admin', 'profile') ?>

<section class="grid-main">
    <form class="card" method="post" novalidate>
        <?= csrf_field() ?>
        <header class="card-head"><div><h2>Your profile</h2><p class="muted">How you appear in the admin console</p></div><?= icon('user') ?></header>
        <div class="form-grid">
            <?= form_field('name', 'Full name', $v['name'], $errors, ['required' => true, 'autocomplete' => 'name']) ?>
            <?= form_field('email', 'Email (used to sign in)', $v['email'], $errors, ['type' => 'email', 'required' => true, 'autocomplete' => 'email']) ?>
            <?= form_field('current_password', 'Current password', '', $errors, ['type' => 'password', 'class' => 'span-2', 'autocomplete' => 'current-password', 'help' => 'Only needed if you change your email.']) ?>
        </div>
        <div class="form-actions"><button class="btn btn-primary">Save profile</button></div>
    </form>

    <article class="card">
        <header class="card-head"><h2>Account</h2></header>
        <div class="person big"><?= avatar($me['name']) ?><div><strong><?= e($me['name']) ?></strong><small class="muted block">Administrator</small></div></div>
        <dl class="facts one-col">
            <div><dt>Admin since</dt><dd><?= e(fmt_datetime($me['created_at'], 'd F Y')) ?></dd></div>
            <div><dt>Last sign-in</dt><dd><?= e(fmt_datetime($me['last_login_at'])) ?></dd></div>
        </dl>
    </article>
</section>

<?php admin_footer(); ?>
