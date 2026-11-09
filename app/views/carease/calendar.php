<?php
$appShell = 'admin';
$appNavKey = 'calendar';
$appTitle = 'Google Calendar Sync';
$appSubtitle = 'Push reserved bookings to the rental calendar';

include __DIR__ . '/header.php'; ?>
<div class="heading">
    <h1>Google Calendar Sync</h1><a class="button ghost" href="<?= site_url('carease/admin') ?>">Back to Admin</a>
</div>
<?php if (!empty($gcalEmbedUrl)): ?>
    <section class="calendar-panel">
        <div class="calendar-panel-head">
            <div>
                <h2>Calendar Preview</h2>
                <p>Live month view of the rental calendar.</p>
            </div>
        </div>
        <iframe class="gcal-frame" src="<?= $h($gcalEmbedUrl) ?>" title="CarEase Google Calendar" loading="lazy"
            referrerpolicy="no-referrer"></iframe>
        <p class="gcal-hint">If the month view stays empty, share the calendar with
            <strong>Anyone with the link&nbsp;&rarr;&nbsp;Viewer</strong> in Google Calendar settings, then reload this
            page.</p>
    </section>
<?php endif; ?>
<section class="panel">
    <p><strong>Google connection:</strong> <?= !empty($connected) ? 'Authorized (token saved)' : 'Not connected' ?></p>
    <?php if (empty($connected)): ?>
        <p><a class="button" href="<?= site_url('carease/calendar-oauth/connect') ?>">Connect Google Calendar</a></p>
    <?php else: ?>
        <p><a class="button ghost" href="<?= site_url('carease/calendar-oauth/connect') ?>">Reconnect Google
                Calendar</a></p>
        <form method="post" action="<?= site_url('carease/calendar/sync-all') ?>">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
            <button class="button" type="submit">Sync all bookings</button>
        </form>
    <?php endif; ?>
    <p>Pending, approved and active bookings are included. Click Sync to create an all-day event in the rental calendar. Repeating Sync will not
        create a duplicate event.</p>
    <?php if (!empty($message)): ?>
        <p>
            <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
        </p>
    <?php endif; ?>
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
                        <td>#
                            <?= (int) $b['id'] ?>
                        </td>
                        <td>
                            <?= htmlspecialchars($b['customer_name'], ENT_QUOTES, 'UTF-8') ?>
                        </td>
                        <td>
                            <?= htmlspecialchars($b['pickup_date'], ENT_QUOTES, 'UTF-8') ?>
                        </td>
                        <td>
                            <?= htmlspecialchars($b['return_date'], ENT_QUOTES, 'UTF-8') ?>
                        </td>
                        <td>
                            <?= htmlspecialchars($b['status'], ENT_QUOTES, 'UTF-8') ?>
                        </td>
                        <td>
                            <form method="post" action="<?= site_url('carease/calendar/sync') ?>"><input type="hidden"
                                    name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>"><input
                                    type="hidden" name="booking_id" value="<?= (int) $b['id'] ?>"><button
                                    class="button small" type="submit" <?= empty($connected) ? 'disabled' : '' ?>>Sync
                                    to Google Calendar</button></form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php include __DIR__ . '/footer.php'; ?>