<?php
$appShell = 'client';
$appNavKey = 'bookings';
$appTitle = 'Payment Result';
$appSubtitle = 'Booking #' . (int) $bookingId;

include __DIR__ . '/header.php'; ?>
<section class="panel">
    <h2>Payment for Booking #
        <?= (int) $bookingId ?>
    </h2>
    <?php if ($paid): ?>
        <p>Payment verified successfully. Thank you!</p>
        <?php if ($payment): ?>
            <table>
                <tbody>
                    <tr>
                        <th>Amount</th>
                        <td>₱
                            <?= number_format((float) $amount, 2) ?>
                        </td>
                    </tr>
                    <tr>
                        <th>Payment Method</th>
                        <td>
                            <?= $h($payment['payment_method'] ?: 'Not recorded') ?>
                        </td>
                    </tr>
                    <tr>
                        <th>Status</th>
                        <td>
                            <?= $h(ucfirst($payment['status'])) ?>
                        </td>
                    </tr>
                    <tr>
                        <th>Paid At</th>
                        <td>
                            <?= $h($payment['paid_at'] ?: '-') ?>
                        </td>
                    </tr>
                    <tr>
                        <th>Reference</th>
                        <td>
                            <?= $h($payment['checkout_session_id']) ?>
                        </td>
                    </tr>
                </tbody>
            </table>
        <?php endif; ?>
    <?php else: ?>
        <p>Payment is not yet confirmed. Please return to My Bookings and check again later.</p>
        <?php if ($payment && $payment['status'] === 'pending'): ?>
            <p>Amount due: ₱
                <?= number_format((float) $amount, 2) ?>
            </p>
        <?php endif; ?>
    <?php endif; ?>
    <a class="button" href="<?= site_url('carease/my-bookings') ?>">My Bookings</a>
</section>
<?php include __DIR__ . '/footer.php'; ?>
