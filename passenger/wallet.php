<?php
/**
 * Passenger → Wallet & payments: CrabPay balance, top-ups, saved cards, history.
 * Top-ups and card charges are SIMULATED (no real payment gateway) — fine for a school project.
 */
require __DIR__ . '/../includes/passenger_auth.php';
require __DIR__ . '/../includes/passenger_layout.php';
require __DIR__ . '/../includes/payments.php';
$p = require_passenger();
$pid = (int)$p['id'];
$pdo = db();

$errors = [];
$cardForm = ['holder_name' => $p['name'], 'card_number' => '', 'expiry' => ''];
$topup = ['amount' => '50', 'source' => 'fpx'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'topup') {
        // A typed amount wins over the quick-amount buttons.
        $custom = trim((string)($_POST['amount_custom'] ?? ''));
        $topup = ['amount' => $custom !== '' ? $custom : trim((string)($_POST['amount'] ?? '')), 'source' => (string)($_POST['source'] ?? '')];
        $amount = is_numeric($topup['amount']) ? round((float)$topup['amount'], 2) : 0;
        $source = null;
        if ($amount < WALLET_TOPUP_MIN || $amount > WALLET_TOPUP_MAX) {
            $errors['amount_custom'] = 'Top up between ' . money(WALLET_TOPUP_MIN) . ' and ' . money(WALLET_TOPUP_MAX) . '.';
        }
        if ($topup['source'] === 'fpx') {
            $source = 'online banking (FPX)';
        } elseif (ctype_digit($topup['source']) && ($c = find_card((int)$topup['source'], $pid)) && !card_expired($c)) {
            $source = card_label($c);
        } else {
            $errors['source'] = 'Choose how to pay for the top-up.';
        }
        if (!$errors) {
            $pdo->beginTransaction();
            wallet_apply($pid, $amount, 'topup', 'Top-up via ' . $source);
            $pdo->commit();
            flash('success', money($amount) . ' added to your CrabPay wallet.');
            redirect('passenger/wallet.php');
        }
    }

    if ($action === 'add_card') {
        $cardForm = array_merge($cardForm, array_intersect_key($_POST, $cardForm));
        $cardForm['card_number'] = '';   // never echo a card number back into the page
        if (count(passenger_cards($pid)) >= MAX_SAVED_CARDS) {
            $errors['card_number'] = 'You can save up to ' . MAX_SAVED_CARDS . ' cards. Remove one first.';
        } else {
            [$card, $errors] = validate_card($_POST);
            if (!$errors) {
                $first = !passenger_cards($pid);
                $pdo->prepare('INSERT INTO payment_cards (passenger_id, brand, last4, exp_month, exp_year, holder_name, is_default) VALUES (?, ?, ?, ?, ?, ?, ?)')
                    ->execute([$pid, $card['brand'], $card['last4'], $card['exp_month'], $card['exp_year'], $card['holder_name'], $first ? 1 : 0]);
                flash('success', $card['brand'] . ' •••• ' . $card['last4'] . ' saved' . ($first ? ' as your default card.' : '.'));
                redirect('passenger/wallet.php#cards');
            }
        }
    }

    if (in_array($action, ['default_card', 'remove_card'], true)) {
        $card = find_card((int)($_POST['card_id'] ?? 0), $pid);
        if (!$card) {
            flash('error', 'That card was not found.');
        } elseif ($action === 'default_card') {
            $pdo->prepare('UPDATE payment_cards SET is_default = (id = ?) WHERE passenger_id = ?')->execute([$card['id'], $pid]);
            flash('success', card_label($card) . ' is now your default card.');
        } else {
            // A card can't be removed while a trip that will be charged to it is in progress.
            $s = $pdo->prepare("SELECT 1 FROM payments WHERE card_id = ? AND status = 'pending'");
            $s->execute([$card['id']]);
            if ($s->fetch()) {
                flash('error', 'This card is being used for a trip in progress. Remove it after the trip ends.');
            } else {
                $pdo->prepare('DELETE FROM payment_cards WHERE id = ? AND passenger_id = ?')->execute([$card['id'], $pid]);
                if ($card['is_default']) {   // promote another card to default
                    $pdo->prepare('UPDATE payment_cards SET is_default = 1 WHERE passenger_id = ? ORDER BY id DESC LIMIT 1')->execute([$pid]);
                }
                flash('success', card_label($card) . ' removed.');
            }
        }
        redirect('passenger/wallet.php#cards');
    }
}

$balance = wallet_balance($pid);
$cards = passenger_cards($pid);
$history = wallet_history($pid, 30);
$s = $pdo->prepare("SELECT COALESCE(SUM(CASE WHEN type = 'topup' THEN amount END), 0) AS topped, COALESCE(-SUM(CASE WHEN type = 'ride' THEN amount END), 0) AS spent
                    FROM wallet_transactions WHERE passenger_id = ? AND created_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')");
$s->execute([$pid]);
$month = $s->fetch();

passenger_header('Wallet', 'wallet');
?>

<section class="grid-main">
    <div class="stack">
        <article class="card wallet-hero">
            <div>
                <p class="wallet-label"><?= crab_logo(22) ?> CrabPay balance</p>
                <p class="wallet-balance"><?= e(money($balance)) ?></p>
                <p class="wallet-sub">This month: <?= e(money($month['topped'])) ?> topped up · <?= e(money($month['spent'])) ?> spent on trips</p>
            </div>
        </article>

        <form class="card" method="post" novalidate>
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="topup">
            <header class="card-head"><div><h2>Top up</h2><p class="muted">Use your balance to pay for trips — no cash needed</p></div></header>
            <div class="amount-chips" role="group" aria-label="Quick amounts">
                <?php foreach ([10, 20, 50, 100] as $a): ?>
                    <label class="chip"><input type="radio" name="amount" value="<?= $a ?>"<?= $topup['amount'] === (string)$a ? ' checked' : '' ?>><span>RM <?= $a ?></span></label>
                <?php endforeach; ?>
            </div>
            <?= form_field('amount_custom', 'Or enter an amount (RM)',in_array($topup['amount'], ['10', '20', '50', '100'], true) ? '' : $topup['amount'], $errors, ['type' => 'number', 'attrs' => 'min="' . WALLET_TOPUP_MIN . '" max="' . WALLET_TOPUP_MAX . '" step="0.01" data-custom-amount', 'placeholder' => WALLET_TOPUP_MIN . ' – ' . WALLET_TOPUP_MAX, 'class' => 'custom-amount']) ?>
            <label class="field<?= isset($errors['source']) ? ' has-error' : '' ?>">
                <span>Pay with</span>
                <select name="source">
                    <option value="fpx"<?= $topup['source'] === 'fpx' ? ' selected' : '' ?>>Online banking (FPX)</option>
                    <?php foreach ($cards as $c): if (card_expired($c)) continue; ?>
                        <option value="<?= (int)$c['id'] ?>"<?= $topup['source'] === (string)$c['id'] ? ' selected' : '' ?>><?= e(card_label($c)) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if (isset($errors['source'])): ?><small class="field-error"><?= e($errors['source']) ?></small><?php endif; ?>
            </label>
            <div class="form-actions"><button class="btn btn-primary">Top up</button></div>
            <p class="small muted">Demo mode: top-ups are simulated, no real money is charged.</p>
        </form>

        <article class="card">
            <header class="card-head"><div><h2>Wallet history</h2><p class="muted">Latest 30 transactions</p></div></header>
            <?php if ($history): ?>
                <ul class="txn-list">
                    <?php foreach ($history as $t): ?>
                        <li>
                            <span class="txn-icon <?= $t['amount'] >= 0 ? 'in' : 'out' ?>"><?= icon($t['type'] === 'ride' ? 'rides' : ($t['type'] === 'refund' ? 'arrow' : 'money'), 16) ?></span>
                            <div class="txn-text"><strong><?= e(['topup' => 'Top-up', 'ride' => 'Trip payment', 'refund' => 'Refund', 'adjustment' => 'Adjustment'][$t['type']]) ?></strong>
                                <small class="muted"><?= e($t['description']) ?></small>
                                <small class="muted"><?= e(fmt_datetime($t['created_at'], 'j M Y, g:i A')) ?><?php if ($t['ride_id']): ?> · <a class="link" href="<?= e(url('passenger/receipt.php?id=' . $t['ride_id'])) ?>">Receipt</a><?php endif; ?></small></div>
                            <div class="txn-amount"><strong class="<?= $t['amount'] >= 0 ? 'pos' : '' ?>"><?= $t['amount'] >= 0 ? '+' : '−' ?><?= e(money(abs((float)$t['amount']))) ?></strong><small class="muted">Bal. <?= e(money($t['balance_after'])) ?></small></div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p class="empty">No wallet activity yet.</p>
            <?php endif; ?>
        </article>
    </div>

    <div class="stack" id="cards">
        <article class="card">
            <header class="card-head"><div><h2>Saved cards</h2><p class="muted">Used for card trips and top-ups</p></div></header>
            <?php if ($cards): ?>
                <ul class="card-list">
                    <?php foreach ($cards as $c): ?>
                        <li class="<?= card_expired($c) ? 'expired' : '' ?>">
                            <span class="card-chip brand-<?= e(strtolower($c['brand'])) ?>"><?= e($c['brand']) ?></span>
                            <div class="card-text"><strong>•••• <?= e($c['last4']) ?></strong>
                                <small class="muted"><?= e($c['holder_name']) ?> · <?= card_expired($c) ? '<span class="field-error">Expired</span>' : 'Exp. ' . sprintf('%02d/%02d', $c['exp_month'], $c['exp_year'] % 100) ?></small></div>
                            <form method="post" class="inline-actions">
                                <?= csrf_field() ?><input type="hidden" name="card_id" value="<?= (int)$c['id'] ?>">
                                <?php if ($c['is_default']): ?><span class="badge badge-completed">Default</span>
                                <?php elseif (!card_expired($c)): ?><button class="btn btn-sm btn-ghost" name="action" value="default_card">Make default</button><?php endif; ?>
                                <button class="icon-btn no" name="action" value="remove_card" aria-label="Remove card ending <?= e($c['last4']) ?>" title="Remove" data-confirm="Remove <?= e(card_label($c)) ?>?"><?= icon('x', 16) ?></button>
                            </form>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p class="empty">No cards saved yet.</p>
            <?php endif; ?>
        </article>

        <form class="card" method="post" novalidate autocomplete="off">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="add_card">
            <header class="card-head"><div><h2>Add a card</h2><p class="muted">Visa, Mastercard or Amex</p></div><?= icon('lock') ?></header>
            <div class="form-grid">
                <?= form_field('card_number', 'Card number', '', $errors, ['required' => true, 'class' => 'span-2', 'placeholder' => '4242 4242 4242 4242', 'attrs' => 'inputmode="numeric" autocomplete="off" maxlength="23" data-card-number']) ?>
                <?= form_field('expiry', 'Expiry (MM/YY)', $cardForm['expiry'], $errors, ['required' => true, 'placeholder' => '08/28', 'attrs' => 'inputmode="numeric" maxlength="7" data-card-expiry']) ?>
                <?= form_field('cvv', 'Security code', '', $errors, ['type' => 'password', 'required' => true, 'placeholder' => 'CVV', 'attrs' => 'inputmode="numeric" maxlength="4" autocomplete="off"']) ?>
                <?= form_field('holder_name', 'Name on card', $cardForm['holder_name'], $errors, ['required' => true, 'class' => 'span-2']) ?>
            </div>
            <div class="form-actions"><button class="btn btn-primary">Save card</button></div>
            <p class="small muted">We only keep the card type, last 4 digits and expiry — never the full number or security code. Test card: 4242 4242 4242 4242, any future date, any 3 digits.</p>
        </form>
    </div>
</section>
<script src="<?= e(asset('assets/js/booking.js')) ?>" defer></script>

<?php passenger_footer(); ?>
