<?php
require __DIR__ . '/../includes/driver_auth.php';
require __DIR__ . '/../includes/driver_layout.php';
$d = require_driver();

$errors = [];
$v = driver_with_pending($d); // pre-fill with pending values so untouched fields keep their pending change

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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

<?php driver_footer(); ?>
