<?php
$appShell = 'admin';
$appNavKey = 'maintenance';
$appTitle = 'Vehicle Maintenance';
$appSubtitle = 'Schedule and track servicing for the fleet';

include __DIR__ . '/header.php'; ?>
<div class="heading">
    <h1>Vehicle Maintenance</h1>
    <a class="button ghost" href="<?= site_url('carease/admin') ?>">Back to Admin</a>
</div>
<?php if (!empty($notice)): ?>
    <p><?= htmlspecialchars($notice, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
<section class="panel">
    <h2>Schedule Maintenance</h2>
    <form method="post" action="<?= site_url('carease/admin/maintenance/save') ?>">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
        <div class="two">
            <label>Vehicle<select name="car_id" required>
                    <option value="">Choose vehicle</option>
                    <?php foreach ($cars as $c): ?>
                        <option value="<?= (int) $c['id'] ?>">
                            <?= htmlspecialchars($c['brand'] . ' ' . $c['name'], ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select></label>
            <label>Maintenance Type<input name="maintenance_type" maxlength="100" required
                    placeholder="Oil Change"></label>
            <label>Scheduled Date<input type="date" name="scheduled_date" required></label>
            <label>Cost (PHP)<input type="number" name="cost" min="0" max="9999999999.99" step="0.01" value="0"
                    required></label>
        </div>
        <label>Notes<textarea name="notes" maxlength="2000" rows="3"></textarea></label>
        <button class="button" type="submit">Add Maintenance</button>
    </form>
</section>
<section class="panel">
    <h2>Maintenance History</h2>
    <div class="scroll">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Car ID</th>
                    <th>Type</th>
                    <th>Date</th>
                    <th>Cost</th>
                    <th>Notes</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($records as $m): ?>
                    <tr>
                        <td><?= (int) $m['id'] ?></td>
                        <td><?= (int) $m['car_id'] ?></td>
                        <td><?= htmlspecialchars($m['maintenance_type'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($m['scheduled_date'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td>₱<?= number_format((float) $m['cost'], 2) ?></td>
                        <td><?= htmlspecialchars($m['notes'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($m['status'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td>
                            <?php $next = $m['status'] === 'scheduled' ? 'in_progress' : ($m['status'] === 'in_progress' ? 'completed' : null); ?>
                            <?php if ($next): ?>
                                <form method="post"
                                    action="<?= site_url('carease/admin/maintenance/' . (int) $m['id'] . '/status') ?>">
                                    <input type="hidden" name="csrf"
                                        value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                                    <input type="hidden" name="status" value="<?= $next ?>">
                                    <button class="button small"
                                        type="submit"><?= $next === 'in_progress' ? 'Start Maintenance' : 'Mark Completed' ?></button>
                                </form>
                            <?php else: ?>Done<?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php include __DIR__ . '/footer.php'; ?>