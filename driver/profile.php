<?php
require __DIR__ . '/../includes/driver_auth.php';
require __DIR__ . '/../includes/driver_layout.php';
require __DIR__ . '/../includes/uploads.php';
$d = require_driver();

$errors = [];
$form = $_POST['form'] ?? '';
$v = driver_with_pending($d); // form values: what's on file, with pending identity changes shown
foreach (DRIVER_FREE_FIELDS as $f) $v[$f] = $d[$f];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    /* ----- Profile photo: upload or remove ----- */
    if ($form === 'photo') {
        if (($_POST['action'] ?? '') === 'remove') {
            delete_upload($d['profile_photo']);
            db()->prepare('UPDATE drivers SET profile_photo = NULL WHERE id = ?')->execute([$d['id']]);
            flash('success', 'Profile photo removed.');
            redirect('driver/profile.php');
        }
        [$path, $err] = store_image_upload($_FILES['photo'] ?? [], 'drivers');
        if ($err) {
            $errors['photo'] = $err;
        } elseif (!$path) {
            $errors['photo'] = 'Choose a photo to upload.';
        } else {
            update_driver((int)$d['id'], ['profile_photo' => $path]);
            delete_upload($d['profile_photo']);
            flash('success', 'Profile photo updated.');
            redirect('driver/profile.php');
        }
    }

    /* ----- Contact details: saved immediately ----- */
    if ($form === 'contact') {
        [$clean, $errors] = validate_driver_input($_POST, DRIVER_FREE_FIELDS, (int)$d['id']);
        $v = array_merge($v, $clean);

        // Changing the login email requires the current password.
        if (!$errors && $clean['email'] !== $d['email']) {
            $s = db()->prepare('SELECT password_hash FROM drivers WHERE id = ?');
            $s->execute([$d['id']]);
            if (!password_verify((string)($_POST['current_password'] ?? ''), $s->fetchColumn())) {
                $errors['current_password'] = 'Enter your current password to change your email.';
            }
        }
        if (!$errors) {
            $changes = driver_diff($d, $clean);
            if ($changes) {
                update_driver((int)$d['id'], $changes);
                flash('success', 'Contact details saved.');
            } else {
                flash('success', 'No changes to save.');
            }
            redirect('driver/profile.php');
        }
    }

    /* ----- Identity: needs admin approval once approved ----- */
    if ($form === 'identity') {
        [$clean, $errors] = validate_driver_input($_POST, DRIVER_IDENTITY_FIELDS, (int)$d['id']);
        $v = array_merge($v, $clean);
        if (!$errors) {
            flash('success', save_review_fields($d, $clean));
            redirect('driver/profile.php');
        }
    }
}

$needsReview = in_array($d['status'], ['approved', 'suspended'], true);
$pending = pending_change_request((int)$d['id']);
$pendingIdentity = $pending ? array_intersect_key($pending['changes'], array_flip(DRIVER_IDENTITY_FIELDS)) : [];

driver_header('Settings', 'settings');
?>

<?= settings_tabs('driver', 'profile') ?>

<?= driver_status_banner($d) ?>

<section class="grid-main">
    <div class="stack">
        <form class="card" method="post" novalidate>
            <?= csrf_field() ?>
            <input type="hidden" name="form" value="contact">
            <header class="card-head"><div><h2>Contact details</h2><p class="muted">Saved straight away</p></div></header>
            <div class="form-grid">
                <?= form_field('email', 'Email (used to sign in)', $v['email'], $errors, ['type' => 'email', 'required' => true, 'autocomplete' => 'email']) ?>
                <?= form_field('phone', 'Mobile number', $v['phone'], $errors, ['type' => 'tel', 'required' => true, 'autocomplete' => 'tel', 'placeholder' => '012-3456789']) ?>
                <?= form_field('address', 'Home address', $v['address'], $errors, ['type' => 'textarea', 'class' => 'span-2', 'autocomplete' => 'street-address']) ?>
                <?= form_field('emergency_name', 'Emergency contact name', $v['emergency_name'], $errors, ['placeholder' => 'e.g. Siti (spouse)']) ?>
                <?= form_field('emergency_phone', 'Emergency contact phone', $v['emergency_phone'], $errors, ['type' => 'tel', 'placeholder' => '012-3456789']) ?>
                <?= form_field('current_password', 'Current password', '', $errors, ['type' => 'password', 'class' => 'span-2', 'autocomplete' => 'current-password', 'help' => 'Only needed if you change your email.']) ?>
            </div>
            <div class="form-actions"><button class="btn btn-primary">Save contact details</button></div>
        </form>

        <form class="card" method="post" novalidate>
            <?= csrf_field() ?>
            <input type="hidden" name="form" value="identity">
            <header class="card-head">
                <div><h2>Identity</h2><p class="muted"><?= $needsReview ? 'Changes are checked by Crab before they apply' : 'Checked as part of your application' ?></p></div>
                <?= icon('id') ?>
            </header>
            <?php if ($pendingIdentity): ?>
                <div class="pending-note">
                    <?= icon('clock', 16) ?>
                    <div>Waiting for approval:
                        <?php foreach ($pendingIdentity as $f => $val): ?>
                            <span class="pending-chip"><?= e(driver_field_label($f)) ?>: <strong><?= e($val) ?></strong></span>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
            <div class="form-grid">
                <?= form_field('name', 'Full name (as in MyKad)', $v['name'], $errors, ['required' => true, 'autocomplete' => 'name']) ?>
                <?= form_field('ic_no', 'IC number (MyKad)', $v['ic_no'], $errors, ['required' => true, 'placeholder' => '900101-12-5678']) ?>
            </div>
            <div class="form-actions">
                <button class="btn btn-outline"><?= $needsReview ? 'Request change' : 'Save' ?></button>
            </div>
        </form>
    </div>

    <form class="card photo-card" method="post" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <input type="hidden" name="form" value="photo">
        <input type="hidden" name="MAX_FILE_SIZE" value="<?= UPLOAD_MAX_BYTES ?>">
        <header class="card-head"><div><h2>Profile photo</h2><p class="muted">Shown to passengers when you accept a ride</p></div></header>
        <div class="photo-preview" data-photo-preview>
            <?= avatar($d['name'], $d['profile_photo'], 'xxl') ?>
        </div>
        <label class="file-drop<?= isset($errors['photo']) ? ' has-error' : '' ?>">
            <?= icon('camera') ?>
            <span><strong>Choose a photo</strong><small class="muted">JPG, PNG or WebP · max 2 MB · clear, front-facing</small></span>
            <input type="file" name="photo" accept="image/jpeg,image/png,image/webp" data-photo-input>
        </label>
        <?php if (isset($errors['photo'])): ?><small class="field-error"><?= e($errors['photo']) ?></small><?php endif; ?>
        <div class="form-actions">
            <button class="btn btn-primary" name="action" value="upload">Upload photo</button>
            <?php if ($d['profile_photo']): ?>
                <button class="btn btn-ghost" name="action" value="remove" data-confirm="Remove your profile photo?">Remove</button>
            <?php endif; ?>
        </div>
    </form>
</section>

<?php driver_footer(); ?>
