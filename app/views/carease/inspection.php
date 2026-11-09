<?php
$appShell = 'admin';
$appNavKey = 'bookings';
$appTitle = 'Vehicle Inspection';
$appSubtitle = 'Booking #' . (int) $booking['id'];

include __DIR__ . '/header.php'; ?>
<div class="heading">
    <h1>Vehicle Inspection — Booking #<?= (int) $booking['id'] ?></h1><a class="button ghost"
        href="<?= site_url('carease/admin') ?>">Back to Admin</a>
</div>
<section class="panel">
    <p>Client: <?= $h($booking['customer_name']) ?> | Vehicle: <?= $h($car['brand'] . ' ' . $car['name']) ?> | Booking
        status: <?= $h($booking['status']) ?></p>
    <?php if (!empty($inspectionError)): ?>
        <p><?= $h($inspectionError) ?></p><?php endif; ?>
    <?php foreach (['pre' => 'Before Rental', 'post' => 'After Return'] as $type => $label): ?>
        <h2><?= $label ?></h2>
        <?php if (isset($inspections[$type])): $i = $inspections[$type]; ?>
            <p>Recorded: <?= $h($i['inspected_at']) ?> | Fuel: <?= $h($i['fuel_level']) ?> | Mileage: <?= (int) $i['mileage'] ?>
                km | Condition: <?= $h($i['vehicle_condition']) ?></p>
            <p>Damage notes: <?= $h($i['damage_notes'] ?: 'None') ?></p>
        <?php elseif (($type === 'pre' && in_array($booking['status'], ['approved', 'confirmed'], true)) || ($type === 'post' && in_array($booking['status'], ['ongoing', 'overdue'], true))): ?>
            <form method="post" action="<?= site_url('carease/admin/inspection/' . (int) $booking['id']) ?>">
                <input type="hidden" name="csrf" value="<?= $h($csrf) ?>"><input type="hidden" name="inspection_type"
                    value="<?= $h($type) ?>">
                <div class="two">
                    <label>Fuel Level<select name="fuel_level" required>
                            <option value="full">Full</option>
                            <option value="three_quarter">3/4</option>
                            <option value="half">1/2</option>
                            <option value="quarter">1/4</option>
                            <option value="empty">Empty</option>
                        </select></label>
                    <label>Mileage (km)<input type="number" name="mileage" min="0" max="99999999" required></label>
                    <label>Vehicle Condition<select name="vehicle_condition" required>
                            <option value="good">Good</option>
                            <option value="minor">Minor Damage</option>
                            <option value="major">Major Damage</option>
                        </select></label>
                    <label>Damage Notes<textarea name="damage_notes" maxlength="2000"
                            placeholder="Describe any damage"></textarea></label>
                </div><button class="button" type="submit">Save <?= $label ?> Inspection</button>
            </form>
        <?php else: ?>
            <p>Inspection form will be available at the appropriate rental stage.</p><?php endif; ?>
    <?php endforeach; ?>
</section>
<?php include __DIR__ . '/footer.php'; ?>