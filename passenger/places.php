<?php
/**
 * Saved places: Home, Work and favourite addresses for quicker booking.
 */
require __DIR__ . '/../includes/passenger_auth.php';
require __DIR__ . '/../includes/passenger_layout.php';
$p = require_passenger();
$pdo = db();

$errors = [];
$editId = (int)($_GET['edit'] ?? 0);
$v = ['label' => $_GET['label'] ?? '', 'address' => '', 'note' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    $placeId = (int)($_POST['place_id'] ?? 0);

    if ($action === 'delete') {
        $d = $pdo->prepare('DELETE FROM passenger_saved_places WHERE id = ? AND passenger_id = ?');
        $d->execute([$placeId, $p['id']]);
        flash($d->rowCount() ? 'success' : 'error', $d->rowCount() ? 'Place removed.' : 'That place was not found.');
        redirect('passenger/places.php');
    }

    if ($action === 'save') {
        // Editing: make sure the place belongs to this passenger.
        if ($placeId) {
            $own = $pdo->prepare('SELECT 1 FROM passenger_saved_places WHERE id = ? AND passenger_id = ?');
            $own->execute([$placeId, $p['id']]);
            if (!$own->fetch()) redirect('passenger/places.php');
        } elseif (count(saved_places((int)$p['id'])) >= MAX_SAVED_PLACES) {
            flash('error', 'You can save up to ' . MAX_SAVED_PLACES . ' places. Remove one to add another.');
            redirect('passenger/places.php');
        }

        [$clean, $errors] = validate_place($_POST, (int)$p['id'], $placeId ?: null);
        $v = $clean;
        $editId = $placeId;
        if (!$errors) {
            if ($placeId) {
                $pdo->prepare('UPDATE passenger_saved_places SET label = ?, address = ?, note = ? WHERE id = ? AND passenger_id = ?')
                    ->execute([$clean['label'], $clean['address'], $clean['note'], $placeId, $p['id']]);
                flash('success', "\"{$clean['label']}\" updated.");
            } else {
                $pdo->prepare('INSERT INTO passenger_saved_places (passenger_id, label, address, note) VALUES (?, ?, ?, ?)')
                    ->execute([$p['id'], $clean['label'], $clean['address'], $clean['note']]);
                flash('success', "\"{$clean['label']}\" saved.");
            }
            redirect('passenger/places.php');
        }
    }
}

$places = saved_places((int)$p['id']);
$labels = array_column($places, 'label');

// Load the place being edited (GET ?edit=ID)
if ($editId && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $s = $pdo->prepare('SELECT * FROM passenger_saved_places WHERE id = ? AND passenger_id = ?');
    $s->execute([$editId, $p['id']]);
    $row = $s->fetch();
    if ($row) $v = ['label' => $row['label'], 'address' => $row['address'], 'note' => $row['note']];
    else $editId = 0;
}
$full = !$editId && count($places) >= MAX_SAVED_PLACES;

passenger_header('Saved places', 'places');
?>

<section class="grid-main">
    <article class="card">
        <header class="card-head"><div><h2>Your places</h2><p class="muted"><?= count($places) ?> of <?= MAX_SAVED_PLACES ?> saved</p></div></header>
        <?php if ($places): ?>
            <ul class="place-list">
                <?php foreach ($places as $pl): ?>
                    <li<?= $pl['id'] == $editId ? ' class="editing"' : '' ?>>
                        <span class="place-icon"><?= icon($pl['label'] === 'Home' ? 'home' : ($pl['label'] === 'Work' ? 'flag' : 'pin'), 18) ?></span>
                        <div class="place-text">
                            <strong><?= e($pl['label']) ?></strong>
                            <span><?= e($pl['address']) ?></span>
                            <?php if ($pl['note']): ?><small class="muted"><?= e($pl['note']) ?></small><?php endif; ?>
                        </div>
                        <form method="post" class="inline-actions">
                            <?= csrf_field() ?>
                            <input type="hidden" name="place_id" value="<?= (int)$pl['id'] ?>">
                            <a class="icon-btn" href="<?= e(url('passenger/places.php?edit=' . $pl['id'])) ?>" title="Edit <?= e($pl['label']) ?>" aria-label="Edit <?= e($pl['label']) ?>"><?= icon('edit', 16) ?></a>
                            <button class="icon-btn no" name="action" value="delete" title="Remove <?= e($pl['label']) ?>" aria-label="Remove <?= e($pl['label']) ?>" data-confirm="Remove &quot;<?= e($pl['label']) ?>&quot; from your saved places?"><?= icon('x', 16) ?></button>
                        </form>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p class="empty">No saved places yet. Add your Home and Work to book in one tap.</p>
        <?php endif; ?>
    </article>

    <form class="card" method="post" novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="place_id" value="<?= (int)$editId ?>">
        <header class="card-head"><div><h2><?= $editId ? 'Edit place' : 'Add a place' ?></h2><p class="muted">Home, Work, or any spot you visit often</p></div></header>

        <?php if ($full): ?>
            <p class="empty">You've saved the maximum of <?= MAX_SAVED_PLACES ?> places. Remove one to add another.</p>
        <?php else: ?>
            <?php if (!$editId): ?>
                <div class="quick-labels">
                    <?php foreach (['Home', 'Work'] as $q): if (!in_array($q, $labels, true)): ?>
                        <a class="tab" href="<?= e(url('passenger/places.php?label=' . $q)) ?>"><?= icon($q === 'Home' ? 'home' : 'flag', 14) ?> <?= $q ?></a>
                    <?php endif; endforeach; ?>
                </div>
            <?php endif; ?>
            <div class="form-grid one">
                <?= form_field('label', 'Name', $v['label'], $errors, ['required' => true, 'placeholder' => 'e.g. Home, Work, Gym, Mum\'s house']) ?>
                <?= form_field('address', 'Address', $v['address'], $errors, ['type' => 'textarea', 'required' => true, 'placeholder' => 'No. 12, Jalan Example, 50450 Kuala Lumpur']) ?>
                <?= form_field('note', 'Note for drivers', $v['note'], $errors, ['placeholder' => 'e.g. Wait at guard house, Block B lobby']) ?>
            </div>
            <div class="form-actions">
                <button class="btn btn-primary"><?= $editId ? 'Save changes' : 'Save place' ?></button>
                <?php if ($editId): ?><a class="btn btn-ghost" href="<?= e(url('passenger/places.php')) ?>">Cancel</a><?php endif; ?>
            </div>
        <?php endif; ?>
    </form>
</section>

<?php passenger_footer(); ?>
