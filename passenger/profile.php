<?php
require __DIR__ . '/../includes/passenger_auth.php';
require __DIR__ . '/../includes/passenger_layout.php';
require __DIR__ . '/../includes/uploads.php';
$p = require_passenger();

$errors = [];
$v = $p;
$form = $_POST['form'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    /* ----- Profile photo ----- */
    if ($form === 'photo') {
        if (($_POST['action'] ?? '') === 'remove') {
            delete_upload($p['profile_photo']);
            db()->prepare('UPDATE passengers SET profile_photo = NULL WHERE id = ?')->execute([$p['id']]);
            flash('success', 'Profile photo removed.');
            redirect('passenger/profile.php');
        }
        [$path, $err] = store_image_upload($_FILES['photo'] ?? [], 'passengers');
        if ($err) {
            $errors['photo'] = $err;
        } elseif (!$path) {
            $errors['photo'] = 'Choose a photo to upload.';
        } else {
            update_passenger((int)$p['id'], ['profile_photo' => $path]);
            delete_upload($p['profile_photo']);
            flash('success', 'Profile photo updated. Drivers will see it when they pick you up.');
            redirect('passenger/profile.php');
        }
    }

    /* ----- Personal details + emergency contact ----- */
    if ($form === 'details') {
        [$clean, $errors] = validate_passenger_input($_POST, PASSENGER_PROFILE_FIELDS, (int)$p['id']);
        $v = array_merge($v, $clean);

        // Changing the sign-in email needs the current password.
        if (!$errors && $clean['email'] !== $p['email']) {
            $s = db()->prepare('SELECT password_hash FROM passengers WHERE id = ?');
            $s->execute([$p['id']]);
            if (!password_verify((string)($_POST['current_password'] ?? ''), $s->fetchColumn())) {
                $errors['current_password'] = 'Enter your current password to change your email.';
            }
        }
        if (!$errors) {
            $changes = passenger_diff($p, $clean);
            update_passenger((int)$p['id'], $changes);
            flash('success', $changes ? 'Profile saved.' : 'No changes to save.');
            redirect('passenger/profile.php');
        }
    }
}

$age = passenger_age($p['date_of_birth']);
passenger_header('My profile', 'profile');
?>

<section class="grid-main">
    <form class="card" method="post" novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="form" value="details">

        <fieldset class="form-section">
            <legend>Personal details</legend>
            <div class="form-grid">
                <?= form_field('name', 'Full name', $v['name'], $errors, ['required' => true, 'autocomplete' => 'name', 'class' => 'span-2']) ?>
                <?= form_field('email', 'Email (used to sign in)', $v['email'], $errors, ['type' => 'email', 'required' => true, 'autocomplete' => 'email']) ?>
                <?= form_field('phone', 'Mobile number', $v['phone'], $errors, ['type' => 'tel', 'required' => true, 'autocomplete' => 'tel', 'help' => 'Drivers call this number if they can\'t find you.']) ?>
                <?= form_field('date_of_birth', 'Date of birth', $v['date_of_birth'], $errors, ['type' => 'date', 'attrs' => 'max="' . date('Y-m-d', strtotime('-' . MIN_PASSENGER_AGE . ' years')) . '"']) ?>
                <?= form_field('gender', 'Gender', $v['gender'] ?? '', $errors, ['type' => 'select', 'options' => GENDERS]) ?>
                <?= form_field('preferred_payment', 'Preferred payment', $v['preferred_payment'], $errors, ['type' => 'select', 'options' => PAYMENT_METHODS, 'required' => true]) ?>
            </div>
        </fieldset>

        <fieldset class="form-section">
            <legend>Emergency contact</legend>
            <p class="small muted" style="margin-top:-4px">Crab support only contacts this person if something goes wrong during a trip.</p>
            <div class="form-grid">
                <?= form_field('emergency_name', 'Name & relationship', $v['emergency_name'], $errors, ['placeholder' => 'e.g. Lina (sister)']) ?>
                <?= form_field('emergency_phone', 'Phone', $v['emergency_phone'], $errors, ['type' => 'tel', 'placeholder' => '012-3456789']) ?>
            </div>
        </fieldset>

        <?= form_field('current_password', 'Current password', '', $errors, ['type' => 'password', 'autocomplete' => 'current-password', 'help' => 'Only needed if you change your email.']) ?>
        <div class="form-actions"><button class="btn btn-primary">Save profile</button></div>
    </form>

    <div class="stack">
        <form class="card photo-card" method="post" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <input type="hidden" name="form" value="photo">
            <input type="hidden" name="MAX_FILE_SIZE" value="<?= UPLOAD_MAX_BYTES ?>">
            <header class="card-head"><div><h2>Profile photo</h2><p class="muted">Helps your driver recognise you at pick-up</p></div></header>
            <div class="photo-preview" data-photo-preview><?= avatar($p['name'], $p['profile_photo'], 'xxl') ?></div>
            <label class="file-drop<?= isset($errors['photo']) ? ' has-error' : '' ?>">
                <?= icon('camera') ?>
                <span><strong>Choose a photo</strong><small class="muted">JPG, PNG or WebP · max 2 MB</small></span>
                <input type="file" name="photo" accept="image/jpeg,image/png,image/webp" data-photo-input>
            </label>
            <?php if (isset($errors['photo'])): ?><small class="field-error"><?= e($errors['photo']) ?></small><?php endif; ?>
            <div class="form-actions">
                <button class="btn btn-primary" name="action" value="upload">Upload photo</button>
                <?php if ($p['profile_photo']): ?>
                    <button class="btn btn-ghost" name="action" value="remove" data-confirm="Remove your profile photo?">Remove</button>
                <?php endif; ?>
            </div>
        </form>

        <article class="card">
            <header class="card-head"><h2>Account</h2></header>
            <dl class="facts one-col">
                <div><dt>Member since</dt><dd><?= e(fmt_datetime($p['created_at'], 'd F Y')) ?></dd></div>
                <?php if ($age !== null): ?><div><dt>Age</dt><dd><?= $age ?></dd></div><?php endif; ?>
                <div><dt>Status</dt><dd><?= badge($p['status']) ?></dd></div>
            </dl>
        </article>
    </div>
</section>

<?php passenger_footer(); ?>
