<?php
/**
 * Admin → Edit passenger profile. Uses the same validation as the passenger portal.
 */
require __DIR__ . '/../includes/admin_auth.php';
require __DIR__ . '/../includes/admin_layout.php';
require __DIR__ . '/../includes/passenger_profile.php';
require __DIR__ . '/../includes/uploads.php';
require_admin();

$id = (int)($_GET['id'] ?? 0);
$p = find_passenger($id);
if (!$p) {
    redirect('admin/passengers.php');
}

$v = $p;
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    [$clean, $errors] = validate_passenger_input($_POST, PASSENGER_PROFILE_FIELDS, $id);
    $v = array_merge($v, $clean);

    [$photo, $photoErr] = store_image_upload($_FILES['photo'] ?? [], 'passengers');
    if ($photoErr) $errors['photo'] = $photoErr;

    if ($errors) {
        if ($photo) delete_upload($photo);
    } else {
        $changes = passenger_diff($p, $clean);
        if ($photo) {
            $changes['profile_photo'] = $photo;
        } elseif (!empty($_POST['remove_photo']) && $p['profile_photo']) {
            $changes['profile_photo'] = null;
        }
        update_passenger($id, $changes);
        if (array_key_exists('profile_photo', $changes)) delete_upload($p['profile_photo']);

        flash('success', $changes ? 'Profile updated (' . count($changes) . ' field' . (count($changes) > 1 ? 's' : '') . ').' : 'No changes to save.');
        redirect('admin/passenger.php?id=' . $id);
    }
}

admin_header('Edit ' . $p['name'], 'passengers');
?>

<a class="back-link" href="<?= e(url('admin/passenger.php?id=' . $id)) ?>">← Back to profile</a>

<?php if ($errors): ?>
    <div class="flash flash-error" role="alert">Please fix the highlighted fields below.</div>
<?php endif; ?>

<form method="post" enctype="multipart/form-data" novalidate class="grid-main">
    <?= csrf_field() ?>
    <input type="hidden" name="MAX_FILE_SIZE" value="<?= UPLOAD_MAX_BYTES ?>">
    <article class="card">
        <fieldset class="form-section">
            <legend>Personal details</legend>
            <div class="form-grid">
                <?= form_field('name', 'Full name', $v['name'], $errors, ['required' => true, 'class' => 'span-2']) ?>
                <?= form_field('email', 'Email', $v['email'], $errors, ['type' => 'email', 'required' => true]) ?>
                <?= form_field('phone', 'Mobile number', $v['phone'], $errors, ['type' => 'tel', 'required' => true]) ?>
                <?= form_field('date_of_birth', 'Date of birth', $v['date_of_birth'], $errors, ['type' => 'date']) ?>
                <?= form_field('gender', 'Gender', $v['gender'] ?? '', $errors, ['type' => 'select', 'options' => GENDERS]) ?>
                <?= form_field('preferred_payment', 'Preferred payment', $v['preferred_payment'], $errors, ['type' => 'select', 'options' => PAYMENT_METHODS, 'required' => true]) ?>
            </div>
        </fieldset>
        <fieldset class="form-section">
            <legend>Emergency contact</legend>
            <div class="form-grid">
                <?= form_field('emergency_name', 'Name & relationship', $v['emergency_name'], $errors) ?>
                <?= form_field('emergency_phone', 'Phone', $v['emergency_phone'], $errors, ['type' => 'tel']) ?>
            </div>
        </fieldset>
    </article>

    <div class="stack">
        <article class="card photo-card">
            <header class="card-head"><h2>Profile photo</h2></header>
            <div class="photo-preview" data-photo-preview><?= avatar($p['name'], $p['profile_photo'], 'xxl') ?></div>
            <label class="file-drop<?= isset($errors['photo']) ? ' has-error' : '' ?>">
                <?= icon('camera') ?>
                <span><strong>Replace photo</strong><small class="muted">JPG, PNG or WebP · max 2 MB</small></span>
                <input type="file" name="photo" accept="image/jpeg,image/png,image/webp" data-photo-input>
            </label>
            <?php if (isset($errors['photo'])): ?><small class="field-error"><?= e($errors['photo']) ?></small><?php endif; ?>
            <?php if ($p['profile_photo']): ?>
                <label class="check"><input type="checkbox" name="remove_photo" value="1"> <span>Remove current photo (e.g. inappropriate image)</span></label>
            <?php endif; ?>
        </article>

        <article class="card">
            <p class="small muted">Edits apply immediately. To suspend or reactivate the account, use the buttons on the profile page.</p>
            <div class="form-actions">
                <button class="btn btn-primary" type="submit">Save changes</button>
                <a class="btn btn-ghost" href="<?= e(url('admin/passenger.php?id=' . $id)) ?>">Cancel</a>
            </div>
        </article>
    </div>
</form>

<?php admin_footer(); ?>
