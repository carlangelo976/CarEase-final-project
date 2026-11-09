<?php
$isAdminView = !empty($isAdmin);
$appShell = $isAdminView ? 'admin' : 'client';
$appNavKey = $isAdminView ? 'bookings' : 'home';
$appTitle = 'Rental Agreement';
$appSubtitle = 'Booking #' . (int) $booking['id'];

$contract = isset($contract) && is_array($contract) ? $contract : null;
$client = isset($client) && is_array($client) ? $client : [];
$carName = trim((string) ($car['brand'] ?? '') . ' ' . (string) ($car['name'] ?? ''));
$status = $contract ? (string) $contract['status'] : 'missing';
$idVerified = (string) ($client['id_status'] ?? '') === 'verified';

include __DIR__ . '/header.php'; ?>
<div class="heading">
    <h1>Rental Agreement</h1>
    <a class="button ghost"
        href="<?= site_url($isAdminView ? 'carease/admin#bookings' : 'carease/account') ?>">Back</a>
</div>

<?php if (!empty($notice)): ?>
    <p class="pill"><?= $h($notice) ?></p>
<?php endif; ?>
<?php if ($error): ?>
    <p class="error"><?= $h($error) ?></p>
<?php endif; ?>

<section class="panel">
    <h2>Booking details</h2>
    <div class="scroll">
        <table>
            <tbody>
                <tr>
                    <th>Booking</th>
                    <td>#<?= (int) $booking['id'] ?></td>
                </tr>
                <tr>
                    <th>Client</th>
                    <td><?= $h($booking['customer_name']) ?> &middot; <?= $h($booking['email']) ?> &middot;
                        <?= $h($booking['phone']) ?></td>
                </tr>
                <tr>
                    <th>Vehicle</th>
                    <td><?= $h($carName !== '' ? $carName : 'Vehicle') ?></td>
                </tr>
                <tr>
                    <th>Dates</th>
                    <td><?= $h($booking['pickup_date']) ?> to <?= $h($booking['return_date']) ?></td>
                </tr>
                <tr>
                    <th>Total</th>
                    <td>₱<?= number_format((float) $booking['total_amount'], 2) ?></td>
                </tr>
                <tr>
                    <th>Booking status</th>
                    <td><span class="pill"><?= $h(ucfirst($booking['status'])) ?></span></td>
                </tr>
                <tr>
                    <th>ID status</th>
                    <td><span class="pill"><?= $h(ucfirst((string) ($client['id_status'] ?? 'none'))) ?></span></td>
                </tr>
            </tbody>
        </table>
    </div>
</section>

<?php if ($contract): ?>
    <section class="panel">
        <div class="heading" style="margin-bottom:12px">
            <h2>Agreement <?= $h($contract['contract_no']) ?></h2>
            <span class="pill"><?= $h(ucfirst($status)) ?></span>
        </div>
        <?php if ($status === 'signed'): ?>
            <p class="muted">Signed by <?= $h($contract['customer_signature']) ?> on
                <?= $h($contract['signed_at']) ?>.</p>
        <?php endif; ?>
        <pre style="white-space:pre-wrap;font-family:Inter, sans-serif;line-height:1.6"><?= $h($contract['terms']) ?></pre>
    </section>

    <?php if ($isAdminView): ?>
        <section class="panel">
            <h2>Agreement actions</h2>
            <form method="post" action="<?= site_url('carease/contract/' . (int) $booking['id']) ?>">
                <input type="hidden" name="csrf" value="<?= $h($csrf) ?>">
                <input type="hidden" name="action" value="generate">
                <button class="button" type="submit">Resend Agreement Email</button>
            </form>
        </section>
    <?php elseif (in_array($status, ['draft', 'sent'], true)): ?>
        <section class="panel">
            <h2>Sign the agreement</h2>
            <?php if (!$idVerified): ?>
                <p class="error">Your ID must be verified before you can sign. <a
                        href="<?= site_url('carease/account/id') ?>">Check your ID status</a>.</p>
            <?php elseif (!in_array($booking['status'], ['approved', 'confirmed'], true)): ?>
                <p class="muted">You can sign once the booking is approved.</p>
            <?php else: ?>
                <form method="post" action="<?= site_url('carease/contract/' . (int) $booking['id']) ?>">
                    <input type="hidden" name="csrf" value="<?= $h($csrf) ?>">
                    <input type="hidden" name="action" value="sign">
                    <label>Type your full name to sign
                        <input name="signature" required maxlength="120" autocomplete="off"
                            placeholder="<?= $h($client['full_name'] ?? '') ?>">
                    </label>
                    <p class="muted">By signing, you accept the terms above. A signed copy will be emailed to
                        <?= $h($booking['email']) ?>.</p>
                    <button class="button" type="submit">Sign Agreement</button>
                </form>
            <?php endif; ?>
        </section>
    <?php endif; ?>
<?php else: ?>
    <section class="panel">
        <h2>Agreement not generated yet</h2>
        <?php if ($isAdminView): ?>
            <p class="muted">Generate and email the rental agreement once the booking is approved.</p>
            <form method="post" action="<?= site_url('carease/contract/' . (int) $booking['id']) ?>">
                <input type="hidden" name="csrf" value="<?= $h($csrf) ?>">
                <input type="hidden" name="action" value="generate">
                <button class="button" type="submit">Generate &amp; Send Agreement</button>
            </form>
        <?php else: ?>
            <p class="muted">The agreement will appear here once CarEase staff approves your booking.</p>
        <?php endif; ?>
    </section>
<?php endif; ?>

<?php include __DIR__ . '/footer.php'; ?>
