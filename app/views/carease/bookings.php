<?php
$appShell = 'client';
$appNavKey = 'bookings';
$appTitle = 'My Bookings';
$appSubtitle = 'Track every reservation you made';

$contracts = isset($contracts) && is_array($contracts) ? $contracts : [];

include __DIR__ . '/header.php'; ?>
<div class="heading">
    <h1>My Bookings</h1><a class="button" href="<?= site_url('carease') ?>">+ Book Another Car</a>
</div>
<?php if (!empty($notice)): ?>
    <p class="muted"><?= $h($notice) ?></p>
<?php endif; ?>
<div class="panel">
    <div class="scroll">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Car</th>
                    <th>Pickup</th>
                    <th>Return</th>
                    <th>Total</th>
                    <th>Status</th>
                    <th>Payment</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($bookings as $b):
                    $carName = 'Car #' . $b['car_id'];
                    foreach ($cars as $c) {
                        if ($c['id'] == $b['car_id']) {
                            $carName = $c['name'];
                            break;
                        }
                    }
                    $paidRow = null;
                    $pendingRow = null;
                    foreach ($payments[$b['id']] ?? [] as $p) {
                        if ($p['status'] === 'paid') $paidRow = $p;
                        elseif ($p['status'] === 'pending') $pendingRow = $p;
                    }
                    $payable = in_array($b['status'], ['approved', 'confirmed'], true); ?>
                    <tr>
                        <td>
                            <?= (int) $b['id'] ?>
                        </td>
                        <td>
                            <?= $h($carName) ?>
                        </td>
                        <td>
                            <?= $h($b['pickup_date']) ?>
                        </td>
                        <td>
                            <?= $h($b['return_date']) ?>
                        </td>
                        <td>₱
                            <?= number_format((float) $b['total_amount'], 2) ?>
                        </td>
                        <td><span class="pill">
                                <?= $h(ucfirst($b['status'])) ?>
                            </span></td>
                        <td>
                            <?php if ($paidRow): ?>
                                <span class="pill">Paid</span>
                                <?= $h($paidRow['payment_method'] ?: 'Method not recorded') ?>
                            <?php elseif ($pendingRow): ?>
                                <span class="pill">Pending</span> Awaiting payment
                            <?php elseif ($payable): ?>
                                Unpaid
                            <?php else: ?>
                                &mdash;
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($payable && !$paidRow): ?>
                                <form method="post"
                                    action="<?= site_url('carease/payment/checkout/' . (int) $b['id']) ?>">
                                    <input type="hidden" name="csrf" value="<?= $h($csrf) ?>">
                                    <button class="button small" type="submit">
                                        <?= $pendingRow ? 'Continue Payment' : 'Pay Now' ?>
                                    </button>
                                </form>
                            <?php endif; ?>
                            <?php if (isset($contracts[(int) $b['id']]) || in_array($b['status'], ['approved', 'confirmed', 'ongoing', 'overdue', 'completed'], true)): ?>
                                <a class="button small ghost"
                                    href="<?= site_url('carease/contract/' . (int) $b['id']) ?>">Agreement</a>
                            <?php elseif (!$payable || $paidRow): ?>
                                &mdash;
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php if (!$bookings): ?>
        <p>No bookings yet.</p>
    <?php endif; ?>
</div>
<?php include __DIR__ . '/footer.php'; ?>