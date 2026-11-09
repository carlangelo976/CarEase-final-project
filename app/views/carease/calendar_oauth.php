<?php
$appShell = 'admin';
$appNavKey = 'calendar';
$appTitle = 'Google Calendar (OAuth)';
$appSubtitle = 'Authorize CarEase to write bookings into your calendar';

include __DIR__ . '/header.php'; ?>
<div class="heading">
    <h1>Google Calendar (OAuth)</h1><a class="button ghost" href="<?= site_url('carease/admin') ?>">Back to Admin</a>
</div>
<section class="panel">
    <p>Connect the CarEase Rentals Google Calendar, then sync reserved and active bookings. Only CarEase admins can use this page.
    </p>
    <?php if (!empty($notice)): ?>
        <p><?= htmlspecialchars($notice, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
    <p><strong>Google connection:</strong> <?= $connected ? 'Authorized (token saved)' : 'Not connected' ?></p>
    <p><a class="button" href="<?= site_url('carease/calendar-oauth/connect') ?>">Connect Google Calendar</a></p>
    <p><small>Use the Google account that owns the CarEase Rentals calendar. This page does not collect your Google
            password.</small></p>
    <div class="scroll">
        <table>
            <thead>
                <tr>
                    <th>Booking</th>
                    <th>Client</th>
                    <th>Pickup</th>
                    <th>Return</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($bookings as $b): ?>
                    <tr>
                        <td>#<?= (int) $b['id'] ?></td>
                        <td><?= htmlspecialchars($b['customer_name'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($b['pickup_date'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($b['return_date'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($b['status'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td>
                            <form method="post" action="<?= site_url('carease/calendar-oauth/sync') ?>">
                                <input type="hidden" name="csrf"
                                    value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                                <input type="hidden" name="booking_id" value="<?= (int) $b['id'] ?>">
                                <button type="submit" class="button small" <?= !$connected ? 'disabled' : '' ?>>Sync to
                                    Google Calendar</button>
                            </form>
                        </td>
                    </tr><?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php include __DIR__ . '/footer.php'; ?>