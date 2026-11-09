<?php
$appShell = 'admin';
$appNavKey = 'dashboard';
$appTitle = 'Admin Dashboard';
$appSubtitle = 'Your rental business at a glance';

$contracts = isset($contracts) && is_array($contracts) ? $contracts : [];
$idUsers = isset($idUsers) && is_array($idUsers) ? $idUsers : [];
$idMap = [];
foreach ($idUsers as $u) {
    $idMap[(int) $u['id']] = $u;
}

include __DIR__ . '/header.php'; ?>
<section class="ce-admin-intro"><div><span class="ce-kicker">OPERATIONS CENTER</span><h2>Welcome to CarEase Rentals</h2><p>Manage your fleet, customer reservations and daily operations from one place.</p></div><div class="ce-admin-illustration" aria-hidden="true">✦ <span>CAREASE</span></div></section>
<div class="heading">
    <h1>CarEase Admin</h1><a class="button" href="<?= site_url('carease') ?>">View
        Website</a><a class="button ghost" href="<?= site_url('carease/admin/maintenance') ?>">
        Maintenance
    </a>
    <a class="button ghost" href="<?= site_url('carease/admin/reports') ?>">
        Reports &amp; Charts
    </a>
    <form method="post" action="<?= site_url('carease/notifications/run') ?>" style="display:inline">
        <input type="hidden" name="csrf" value="<?= $h($csrf) ?>">
        <input type="hidden" name="dry_run" value="1">
        <button class="button ghost" type="submit">Preview Notifications</button>
    </form>
    <form method="post" action="<?= site_url('carease/notifications/run') ?>" style="display:inline">
        <input type="hidden" name="csrf" value="<?= $h($csrf) ?>">
        <button class="button ghost" type="submit">Send Due Notifications</button>
    </form>
</div>
<?php if (!empty($notice)): ?>
    <p class="error"><?= $h($notice) ?></p>
<?php endif; ?>
<div class="stats ce-stats ce-admin-stats">
    <div><strong><?= count($cars) ?></strong><span>Fleet cars</span></div>
    <div><strong><?= count($bookings) ?></strong><span>Total bookings</span></div>
    <div><strong><?= count(array_filter($bookings, fn($b) => $b['status'] === 'pending')) ?></strong><span>Pending
            approval</span></div>
    <div><strong><?= count(array_filter($idUsers, fn($u) => $u['id_status'] === 'pending')) ?></strong><span>IDs waiting
            review</span></div>
</div>

<section class="panel" id="ids">
    <h2>Valid ID Verification</h2>
    <?php if (!$idUsers): ?>
        <p class="muted">No client has submitted a valid ID yet.</p>
    <?php else: ?>
        <div class="scroll">
            <table>
                <thead>
                    <tr>
                        <th>Client</th>
                        <th>ID type / number</th>
                        <th>Status</th>
                        <th>Photo</th>
                        <th>Notes</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($idUsers as $u): ?>
                        <tr>
                            <td><?= $h($u['full_name']) ?><br><small><?= $h($u['email']) ?></small></td>
                            <td><?= $h($u['id_type'] ?? '') ?><br><small><?= $h($u['id_number'] ?? '') ?></small></td>
                            <td><span class="pill"><?= $h(ucfirst((string) $u['id_status'])) ?></span></td>
                            <td>
                                <?php if (!empty($u['id_image'])): ?>
                                    <a href="<?= site_url('carease/id/image/' . (int) $u['id']) ?>" target="_blank"
                                        rel="noopener">View ID</a>
                                <?php else: ?>
                                    &mdash;
                                <?php endif; ?>
                            </td>
                            <td><?= $h($u['id_notes'] ?? '') ?></td>
                            <td>
                                <form method="post"
                                    action="<?= site_url('carease/admin/id/' . (int) $u['id']) ?>"
                                    style="margin-bottom:8px">
                                    <input type="hidden" name="csrf" value="<?= $h($csrf) ?>">
                                    <input type="hidden" name="action" value="approve">
                                    <button class="button small" type="submit">Approve ID</button>
                                </form>
                                <form method="post" action="<?= site_url('carease/admin/id/' . (int) $u['id']) ?>">
                                    <input type="hidden" name="csrf" value="<?= $h($csrf) ?>">
                                    <input type="hidden" name="action" value="reject">
                                    <input name="id_notes" maxlength="255" required placeholder="Rejection reason">
                                    <button class="button small ghost" type="submit">Reject</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<section class="calendar-panel">
    <div class="calendar-panel-head">
        <div>
            <h2>Google Calendar</h2>
            <p>Live month view of every synced CarEase rental booking.</p>
        </div>
        <a class="button ghost small" href="<?= site_url('carease/calendar') ?>">Manage sync</a>
    </div>
    <?php if (!empty($gcalEmbedUrl)): ?>
        <iframe class="gcal-frame" src="<?= $h($gcalEmbedUrl) ?>" title="CarEase Google Calendar" loading="lazy"
            referrerpolicy="no-referrer"></iframe>
        <p class="gcal-hint">If the month view stays empty, share the calendar with
            <strong>Anyone with the link&nbsp;&rarr;&nbsp;Viewer</strong> in Google Calendar settings, then reload this
            page.</p>
    <?php else: ?>
        <p class="gcal-empty">Google Calendar is not connected yet. Add the calendar credentials in
            <code>app/config/carease_oauth_local.php</code> to see the month view here.</p>
    <?php endif; ?>
</section>

<section class="panel" id="fleet">
    <h2>Add / Edit Car</h2>
    <form method="post" action="<?= site_url('carease/admin/car') ?>" enctype="multipart/form-data"><input
            type="hidden" name="csrf" value="<?= $h($csrf) ?>">
        <div class="two"><label>Car ID (leave empty to add)<input type="number" min="1" name="id" id="car-id"
                    placeholder="Existing ID for edit"></label><label>Brand<input name="brand" id="car-brand" required
                    maxlength="80" placeholder="Toyota"></label><label>Model / car name<input name="name" id="car-name"
                    required maxlength="120" placeholder="Vios"></label><label>Daily rate (₱)<input type="number" min="1"
                    step="0.01" name="price_per_day" id="car-price" required></label><label>Seats<input type="number"
                    min="1" max="30" name="seats" id="car-seats" required></label><label>Status<select name="status"
                    id="car-status">
                    <option value="available">Available</option>
                    <option value="maintenance">Maintenance</option>
                </select></label></div>
        <div class="upload-field">
            <span class="upload-preview upload-preview--empty" id="car-photo-preview">
                <?= carease_icon('image') ?>
            </span>
            <div>
                <label class="upload-control">Car photo
                    <input type="file" name="car_image" accept="image/jpeg,image/png,image/webp,image/gif">
                    <span class="upload-hint">JPG, PNG, WEBP or GIF &middot; max 4 MB. Leave empty to keep the current
                        photo.</span>
                </label>
                <label class="upload-hint"><input type="checkbox" name="remove_image" value="1" id="car-remove-image">
                    Remove current photo</label>
            </div>
        </div>
        <div class="button-row">
            <button class="button" type="submit">Save Car</button>
            <button class="button ghost" type="button" id="car-reset">Clear form / Add new</button>
        </div>
    </form>
</section>
<section class="panel">
    <h2>Fleet Management</h2>
    <div class="scroll">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Car</th>
                    <th>Photo</th>
                    <th>Price</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody><?php foreach ($cars as $c): ?>
                    <tr>
                        <td><?= (int) $c['id'] ?></td>
                        <td><?= $h($c['brand'] . ' ' . $c['name']) ?></td>
                        <td>
                            <div class="thumb-cell">
                                <?php $thumb = carease_car_image($c); ?>
                                <?php if ($thumb): ?>
                                    <img class="thumb" src="<?= $h($thumb) ?>"
                                        alt="<?= $h($c['brand'] . ' ' . $c['name']) ?>">
                                <?php else: ?>
                                    <span class="thumb thumb--empty"><?= carease_icon('image') ?></span>
                                <?php endif; ?>
                                <span class="upload-hint"><?= $thumb ? 'Uploaded' : 'No photo' ?></span>
                            </div>
                        </td>
                        <td>₱<?= number_format((float) $c['price_per_day'], 2) ?></td>
                        <td><?= $h($c['status']) ?></td>
                        <td>
                            <div class="row-actions">
                                <button type="button" class="button small ghost" data-edit-car
                                    data-id="<?= (int) $c['id'] ?>" data-brand="<?= $h($c['brand']) ?>"
                                    data-name="<?= $h($c['name']) ?>" data-price="<?= (float) $c['price_per_day'] ?>"
                                    data-seats="<?= (int) $c['seats'] ?>" data-status="<?= $h($c['status']) ?>"
                                    data-photo="<?= $h($thumb ?? '') ?>">Edit</button>
                                <form method="POST" action="<?= site_url('carease/admin/car/delete/' . (int) $c['id']) ?>"
                                    onsubmit="return confirm('Are you sure you want to delete this car?');">

                                    <input type="hidden" name="csrf" value="<?= $h($csrf) ?>">

                                    <button type="submit">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr><?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<section class="panel" id="bookings">
    <h2>Booking Management</h2>
    <div class="scroll">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Client</th>
                    <th>Car ID</th>
                    <th>Dates</th>
                    <th>Total</th>
                    <th>Status</th>
                    <th>Payment</th>
                    <th>Agreement</th>
                    <th>Action</th>
                    <th>Inspection</th>
                </tr>
            </thead>
            <tbody><?php foreach ($bookings as $b): ?>
                    <?php
                    $contract = $contracts[(int) $b['id']] ?? null;
                    $clientId = $idMap[(int) $b['user_id']] ?? null;
                    ?>
                    <tr>
                        <td><?= (int) $b['id'] ?></td>
                        <td><?= $h($b['customer_name']) ?><br><small><?= $h($b['email']) ?></small><br><small>ID:
                                <?= $h(ucfirst((string) ($clientId['id_status'] ?? 'none'))) ?></small></td>
                        <td><?= (int) $b['car_id'] ?></td>
                        <td><?= $h($b['pickup_date']) ?> to <?= $h($b['return_date']) ?></td>
                        <td>₱<?= number_format((float) $b['total_amount'], 2) ?></td>
                        <td><?= $h($b['status']) ?></td>
                        <td><?php
                        $paidRow = null;
                        foreach ($payments[$b['id']] ?? [] as $p) {
                            if ($p['status'] === 'paid') { $paidRow = $p; break; }
                        }
                        if ($paidRow): ?>
                                <span class="pill">Paid</span>
                                <?= $h($paidRow['payment_method'] ?: 'Method not recorded') ?>
                            <?php else: ?>
                                Unpaid
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($contract): ?>
                                <a class="button small ghost"
                                    href="<?= site_url('carease/contract/' . (int) $b['id']) ?>">
                                    <?= $h(ucfirst((string) $contract['status'])) ?>
                                </a>
                            <?php elseif (in_array($b['status'], ['approved', 'confirmed'], true)): ?>
                                <a class="button small"
                                    href="<?= site_url('carease/contract/' . (int) $b['id']) ?>">Create</a>
                            <?php else: ?>
                                &mdash;
                            <?php endif; ?>
                        </td>
                        <td><?php
                        $choices = ['pending', 'approved', 'ongoing', 'overdue', 'completed', 'rejected', 'cancelled'];
                        ?>
                            <form method="post"
                                action="<?= site_url('carease/admin/booking/' . $b['id']) ?>"><input type="hidden"
                                    name="csrf" value="<?= $h($csrf) ?>"><select name="status">
                                    <?php foreach ($choices as $choice): ?>
                                        <option value="<?= $h($choice) ?>"<?= $choice === $b['status'] ? ' selected' : '' ?>>
                                            <?= $h(ucfirst($choice)) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select><button class="button small">Update</button></form>
                        </td>

                        <td>
                            <?php if (
                                in_array($b['status'], [
                                    'approved',
                                    'confirmed',
                                    'ongoing',
                                    'overdue',
                                    'completed'
                                ], true)
                            ): ?>
                                <a class="button small" href="<?= site_url('carease/admin/inspection/' . (int) $b['id']) ?>">
                                    Inspect Vehicle
                                </a>
                            <?php else: ?>
                                <span>Not available</span>
                            <?php endif; ?>
                        </td>

                    </tr><?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<script>
    (function () {
        'use strict';
        var form = document.getElementById('fleet');
        var fields = {
            id: document.getElementById('car-id'),
            brand: document.getElementById('car-brand'),
            name: document.getElementById('car-name'),
            price: document.getElementById('car-price'),
            seats: document.getElementById('car-seats'),
            status: document.getElementById('car-status')
        };
        var preview = document.getElementById('car-photo-preview');
        var removeBox = document.getElementById('car-remove-image');
        var resetBtn = document.getElementById('car-reset');
        if (!fields.id || !preview) {
            return;
        }

        function showPhoto(url) {
            if (url) {
                preview.classList.remove('upload-preview--empty');
                preview.innerHTML = '<img src="' + url + '" alt="Car photo">';
            } else {
                preview.classList.add('upload-preview--empty');
                preview.textContent = '';
            }
        }

        Array.prototype.slice.call(document.querySelectorAll('[data-edit-car]')).forEach(function (btn) {
            btn.addEventListener('click', function () {
                fields.id.value = btn.getAttribute('data-id') || '';
                fields.brand.value = btn.getAttribute('data-brand') || '';
                fields.name.value = btn.getAttribute('data-name') || '';
                fields.price.value = btn.getAttribute('data-price') || '';
                fields.seats.value = btn.getAttribute('data-seats') || '';
                fields.status.value = btn.getAttribute('data-status') || 'available';
                if (removeBox) {
                    removeBox.checked = false;
                }
                showPhoto(btn.getAttribute('data-photo') || '');
                if (form) {
                    form.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            });
        });

        if (resetBtn) {
            resetBtn.addEventListener('click', function () {
                fields.id.value = '';
                fields.brand.value = '';
                fields.name.value = '';
                fields.price.value = '';
                fields.seats.value = '';
                fields.status.value = 'available';
                if (removeBox) {
                    removeBox.checked = false;
                }
                showPhoto('');
            });
        }

        var fileInput = document.querySelector('input[type="file"][name="car_image"]');
        if (fileInput) {
            fileInput.addEventListener('change', function () {
                var file = fileInput.files && fileInput.files[0];
                if (!file) {
                    return;
                }
                var reader = new FileReader();
                reader.onload = function (e) {
                    showPhoto(e.target.result);
                };
                reader.readAsDataURL(file);
            });
        }
    })();
</script>
<?php include __DIR__ . '/footer.php'; ?>
