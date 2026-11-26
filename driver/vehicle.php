<?php
require __DIR__ . '/../includes/driver_auth.php';
require __DIR__ . '/../includes/driver_layout.php';
require __DIR__ . '/../includes/driver_documents.php';
$d = require_driver();

$errors = [];
$v = driver_with_pending($d); // pre-fill with pending values so untouched fields keep their pending change

$docErrors = [];
// Replace car photos / PUSPAKOM certificate
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'docs') {
    verify_csrf();
    $replaced = [];
    foreach (array_keys(DRIVER_DOC_TYPES) as $type) {
        [$file, $err] = store_driver_doc($_FILES['doc_' . $type] ?? [], $type);
        if ($err) $docErrors['doc_' . $type] = $err;
        elseif ($file) { save_driver_doc((int)$d['id'], $type, $file); $replaced[] = DRIVER_DOC_TYPES[$type][0]; }
    }
    if (!$docErrors) {
        flash($replaced ? 'success' : 'error', $replaced ? 'Updated: ' . implode(', ', $replaced) . '.' : 'Choose at least one new file to upload.');
        redirect('driver/vehicle.php#documents');
    }
    if ($replaced) flash('success', 'Updated: ' . implode(', ', $replaced) . '. Please fix the file marked below.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') !== 'docs') {
    verify_csrf();
    [$clean, $errors] = validate_driver_input($_POST, DRIVER_VEHICLE_FIELDS, (int)$d['id']);
    $v = array_merge($v, $clean);
    if (!$errors) {
        flash('success', save_review_fields($d, $clean));
        redirect('driver/vehicle.php');
    }
}

$needsReview = in_array($d['status'], ['approved', 'suspended'], true);
$pending = pending_change_request((int)$d['id']);
$pendingVehicle = $pending ? array_intersect_key($pending['changes'], array_flip(DRIVER_VEHICLE_FIELDS)) : [];
$daysLeft = license_days_left($d['license_expiry']);
$minYear = (int)date('Y') - MAX_VEHICLE_AGE;

driver_header('Licence & vehicle', 'vehicle');
?>

<?= driver_status_banner($d) ?>

<section class="grid-main">
    <form class="card" method="post" novalidate>
        <?= csrf_field() ?>
        <header class="card-head">
            <div>
                <h2>Licence &amp; vehicle details</h2>
                <p class="muted"><?= $needsReview
                    ? 'For passenger safety, changes here are checked by Crab before they go live. Your current details stay active until then.'
                    : 'These are checked as part of your application.' ?></p>
            </div>
        </header>

        <?php if ($pendingVehicle): ?>
            <div class="pending-note">
                <?= icon('clock', 16) ?>
                <div>You have changes waiting for approval (sent <?= e(time_ago($pending['created_at'])) ?>). The form below shows your requested values —
                    submit again to adjust the request, or withdraw it from the <a class="link" href="<?= e(url('driver/index.php')) ?>">Home</a> page.</div>
            </div>
        <?php endif; ?>

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
                <?= form_field('plate_no', 'Plate number', $v['plate_no'], $errors, ['required' => true, 'placeholder' => 'SAA 1234']) ?>
                <?= form_field('vehicle_color', 'Colour', $v['vehicle_color'], $errors, ['required' => true, 'placeholder' => 'Silver']) ?>
                <?= form_field('vehicle_year', 'Year of manufacture', $v['vehicle_year'], $errors, ['type' => 'number', 'required' => true, 'help' => "$minYear or newer", 'attrs' => 'min="' . $minYear . '" max="' . (date('Y') + 1) . '"']) ?>
            </div>
        </fieldset>

        <div class="form-actions">
            <button class="btn btn-primary"><?= $needsReview ? 'Submit for approval' : 'Save details' ?></button>
        </div>
    </form>

    <div class="stack">
        <article class="card">
            <header class="card-head"><div><h2>On file now</h2><p class="muted">What passengers and Crab currently see</p></div></header>
            <div class="vehicle-card">
                <span class="plate big"><?= e($d['plate_no']) ?></span>
                <strong><?= e($d['vehicle_model']) ?></strong>
                <span class="muted"><?= e(implode(' · ', array_filter([$d['vehicle_color'], $d['vehicle_year'], VEHICLE_TYPES[$d['vehicle_type']] ?? null]))) ?></span>
            </div>
            <dl class="facts one-col">
                <div><dt>Licence number</dt><dd class="mono"><?= e($d['license_no']) ?></dd></div>
                <div><dt>Licence expiry</dt><dd>
                    <?= e(driver_field_display('license_expiry', $d['license_expiry'])) ?>
                    <?php if ($daysLeft !== null && $daysLeft < 0): ?> <span class="badge badge-suspended">Expired</span>
                    <?php elseif ($daysLeft !== null && $daysLeft <= 30): ?> <span class="badge badge-pending">In <?= $daysLeft ?> days</span><?php endif; ?>
                </dd></div>
            </dl>
        </article>
    </div>
</section>

<?php $docs = driver_documents((int)$d['id']); $missing = array_diff(array_keys(DRIVER_DOC_TYPES), array_keys($docs)); ?>
<form class="card" id="documents" method="post" enctype="multipart/form-data" novalidate>
    <?= csrf_field() ?>
    <input type="hidden" name="form" value="docs">
    <input type="hidden" name="MAX_FILE_SIZE" value="<?= DRIVER_DOC_MAX_BYTES ?>">
    <header class="card-head"><div><h2>Car photos &amp; PUSPAKOM</h2>
        <p class="muted">Replace any photo or your certificate — choose the new file, check the preview, then save. Files stay as they are until you save.</p></div></header>
    <?php if ($missing): ?><div class="pending-note"><?= icon('alert', 16) ?><div>Please upload: <?= e(implode(', ', array_map(fn($t) => DRIVER_DOC_TYPES[$t][0], $missing))) ?>.</div></div><?php endif; ?>
    <div class="doc-grid">
        <?php foreach (CAR_PHOTO_TYPES as $t): ?><?= doc_slot($t, $docs[$t] ?? null, 'replace', $docErrors['doc_' . $t] ?? null) ?><?php endforeach; ?>
    </div>
    <?= doc_slot('puspakom', $docs['puspakom'] ?? null, 'replace', $docErrors['doc_puspakom'] ?? null) ?>
    <?php if ($docs): ?><p class="small muted">Last updated <?= e(time_ago(max(array_column($docs, 'uploaded_at')))) ?>.</p><?php endif; ?>
    <div class="form-actions"><button class="btn btn-primary">Save new files</button></div>
</form>
<script src="<?= e(asset('assets/js/documents.js')) ?>" defer></script>

<?php driver_footer(); ?>
