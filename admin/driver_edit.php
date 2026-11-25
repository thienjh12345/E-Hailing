<?php
/**
 * Admin → Edit driver profile. Admin edits apply immediately (no change request),
 * using the same validation rules as the driver portal.
 */
require __DIR__ . '/../includes/admin_auth.php';
require __DIR__ . '/../includes/admin_layout.php';
require __DIR__ . '/../includes/driver_profile.php';
require __DIR__ . '/../includes/uploads.php';
require_admin();

$id = (int)($_GET['id'] ?? 0);
$d = find_driver($id);
if (!$d) {
    redirect('admin/drivers.php');
}

$fields = array_merge(DRIVER_IDENTITY_FIELDS, DRIVER_FREE_FIELDS, DRIVER_VEHICLE_FIELDS);
$v = $d;
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    [$clean, $errors] = validate_driver_input($_POST, $fields, $id);
    $v = array_merge($v, $clean);

    [$photo, $photoErr] = store_image_upload($_FILES['photo'] ?? [], 'drivers');
    if ($photoErr) $errors['photo'] = $photoErr;

    if ($errors) {
        if ($photo) delete_upload($photo); // don't keep an orphan file if the form failed
    } else {
        $changes = driver_diff($d, $clean);
        if ($photo) {
            $changes['profile_photo'] = $photo;
        } elseif (!empty($_POST['remove_photo']) && $d['profile_photo']) {
            $changes['profile_photo'] = null;
        }
        update_driver($id, $changes);
        if (array_key_exists('profile_photo', $changes)) delete_upload($d['profile_photo']);

        flash('success', $changes ? 'Profile updated (' . count($changes) . ' field' . (count($changes) > 1 ? 's' : '') . ').' : 'No changes to save.');
        redirect('admin/driver.php?id=' . $id);
    }
}

$pending = pending_change_request($id);
admin_header('Edit ' . $d['name'], 'drivers');
?>

<a class="back-link" href="<?= e(url('admin/driver.php?id=' . $id)) ?>">← Back to profile</a>

<?php if ($pending): ?>
    <div class="pending-note"><?= icon('inbox', 16) ?>
        <div>This driver has a change request waiting. Edits here apply immediately and don't affect that request —
            <a class="link" href="<?= e(url('admin/driver.php?id=' . $id)) ?>">review it on the profile page</a>.</div>
    </div>
<?php endif; ?>
<?php if ($errors): ?>
    <div class="flash flash-error" role="alert">Please fix the highlighted fields below.</div>
<?php endif; ?>

<form method="post" enctype="multipart/form-data" novalidate class="grid-main">
    <?= csrf_field() ?>
    <input type="hidden" name="MAX_FILE_SIZE" value="<?= UPLOAD_MAX_BYTES ?>">
    <div class="stack">
        <article class="card">
            <header class="card-head"><h2>Personal &amp; contact</h2></header>
            <div class="form-grid">
                <?= form_field('name', 'Full name', $v['name'], $errors, ['required' => true]) ?>
                <?= form_field('ic_no', 'IC number', $v['ic_no'], $errors, ['required' => true, 'placeholder' => '900101-12-5678']) ?>
                <?= form_field('email', 'Email', $v['email'], $errors, ['type' => 'email', 'required' => true]) ?>
                <?= form_field('phone', 'Mobile number', $v['phone'], $errors, ['type' => 'tel', 'required' => true]) ?>
                <?= form_field('address', 'Home address', $v['address'], $errors, ['type' => 'textarea', 'class' => 'span-2']) ?>
                <?= form_field('emergency_name', 'Emergency contact', $v['emergency_name'], $errors) ?>
                <?= form_field('emergency_phone', 'Emergency phone', $v['emergency_phone'], $errors, ['type' => 'tel']) ?>
            </div>
        </article>

        <article class="card">
            <header class="card-head"><h2>Licence &amp; vehicle</h2></header>
            <div class="form-grid">
                <?= form_field('license_no', 'Licence number', $v['license_no'], $errors, ['required' => true]) ?>
                <?= form_field('license_expiry', 'Licence expiry', $v['license_expiry'], $errors, ['type' => 'date', 'required' => true]) ?>
                <?= form_field('vehicle_type', 'Service type', $v['vehicle_type'], $errors, ['type' => 'select', 'options' => VEHICLE_TYPES, 'required' => true]) ?>
                <?= form_field('vehicle_model', 'Make & model', $v['vehicle_model'], $errors, ['required' => true]) ?>
                <?= form_field('plate_no', 'Plate number', $v['plate_no'], $errors, ['required' => true]) ?>
                <?= form_field('vehicle_color', 'Colour', $v['vehicle_color'], $errors, ['required' => true]) ?>
                <?= form_field('vehicle_year', 'Year', $v['vehicle_year'], $errors, ['type' => 'number', 'required' => true]) ?>
            </div>
        </article>
    </div>

    <div class="stack">
        <article class="card photo-card">
            <header class="card-head"><h2>Profile photo</h2></header>
            <div class="photo-preview" data-photo-preview><?= avatar($d['name'], $d['profile_photo'], 'xxl') ?></div>
            <label class="file-drop<?= isset($errors['photo']) ? ' has-error' : '' ?>">
                <?= icon('camera') ?>
                <span><strong>Replace photo</strong><small class="muted">JPG, PNG or WebP · max 2 MB</small></span>
                <input type="file" name="photo" accept="image/jpeg,image/png,image/webp" data-photo-input>
            </label>
            <?php if (isset($errors['photo'])): ?><small class="field-error"><?= e($errors['photo']) ?></small><?php endif; ?>
            <?php if ($d['profile_photo']): ?>
                <label class="check"><input type="checkbox" name="remove_photo" value="1"> <span>Remove current photo</span></label>
            <?php endif; ?>
        </article>

        <article class="card">
            <p class="small muted">Admin edits apply immediately. Use this to correct typos or update details confirmed with the driver by phone.
                For status changes (approve, suspend…) use the buttons on the profile page.</p>
            <div class="form-actions">
                <button class="btn btn-primary" type="submit">Save changes</button>
                <a class="btn btn-ghost" href="<?= e(url('admin/driver.php?id=' . $id)) ?>">Cancel</a>
            </div>
        </article>
    </div>
</form>

<?php admin_footer(); ?>
