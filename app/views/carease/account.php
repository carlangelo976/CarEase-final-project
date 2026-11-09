<?php
$appShell = 'client';
$appNavKey = 'home';
$appTitle = 'Customer Dashboard';
$appSubtitle = 'Welcome back, ' . ($careaseUser ?: 'renter');

include __DIR__ . '/header.php';

$profile = isset($profile) && is_array($profile) ? $profile : [];
$contracts = isset($contracts) && is_array($contracts) ? $contracts : [];
$idStatus = (string) ($profile['id_status'] ?? 'none');
$idLabels = [
    'none' => 'No ID submitted',
    'pending' => 'Pending verification',
    'verified' => 'Verified',
    'rejected' => 'Rejected',
];

$active = array_values(array_filter($bookings, function ($b) {
    return in_array($b['status'], ['pending', 'approved', 'confirmed', 'ongoing', 'overdue'], true);
}));
$past = array_values(array_filter($bookings, function ($b) {
    return in_array($b['status'], ['completed', 'cancelled', 'rejected'], true);
}));
$totalSpent = 0.0;
foreach ($bookings as $b) {
    if (in_array($b['status'], ['completed', 'approved', 'confirmed', 'ongoing'], true)) {
        $totalSpent += (float) $b['total_amount'];
    }
}
?>

<section class="ce-hero ce-customer-hero"><div class="ce-hero-copy"><span class="ce-kicker">CAREASE RENTALS · YOUR JOURNEY STARTS HERE</span><h2>Rent the right car.<br><em>Enjoy every journey.</em></h2><p>Discover reliable vehicles, simple reservations, and a rental experience built around you.</p><div class="ce-hero-actions"><a class="ce-primary" href="<?= site_url('carease') ?>#fleet">Explore Our Fleet <span aria-hidden="true">→</span></a><a class="ce-secondary" href="<?= site_url('carease/my-bookings') ?>">My Bookings</a></div></div><div class="ce-hero-visual" aria-hidden="true"><span class="ce-car-symbol">✦</span><span class="ce-visual-caption">DRIVE WITH CONFIDENCE</span></div></section>
<div class="ce-section-heading"><div><span class="ce-kicker">YOUR OVERVIEW</span><h2>At a glance</h2></div><span class="ce-heading-note">Your rental activity</span></div>
<div class="stats ce-stats ce-customer-stats">
    <div><strong><?= count($bookings) ?></strong><span>Total bookings</span></div>
    <div><strong><?= count($active) ?></strong><span>Active rentals</span></div>
    <div><strong><?= count($availableCars) ?></strong><span>Cars available</span></div>
    <div><strong>₱<?= number_format($totalSpent, 0) ?></strong><span>Total booked</span></div>
</div>

<section class="panel ce-feature-panel">
    <div class="heading" style="margin-bottom:12px">
        <h2>Valid ID</h2>
        <a class="button small<?= $idStatus === 'verified' ? ' ghost' : '' ?>"
            href="<?= site_url('carease/account/id') ?>">
            <?= $idStatus === 'none' ? 'Upload ID' : ($idStatus === 'rejected' ? 'Resubmit ID' : 'Manage ID') ?>
        </a>
    </div>
    <p><span class="pill"><?= $h($idLabels[$idStatus] ?? ucfirst($idStatus)) ?></span></p>
    <?php if ($idStatus === 'none'): ?>
        <p class="muted">Upload a valid government-issued ID before booking a car.</p>
    <?php elseif ($idStatus === 'pending'): ?>
        <p class="muted">Your ID is awaiting verification. Booking will be available once the admin approves it.</p>
    <?php elseif ($idStatus === 'rejected'): ?>
        <p class="error">Your ID was rejected<?= !empty($profile['id_notes']) ? ': ' . $h($profile['id_notes']) : '.' ?>
        </p>
    <?php else: ?>
        <p class="muted">Your ID is verified. You may book cars, sign agreements, and receive the vehicle.</p>
    <?php endif; ?>
</section>

<section class="panel">
    <div class="heading" style="margin-bottom:16px">
        <h2>Active &amp; upcoming</h2>
        <a class="button small" href="<?= site_url('carease') ?>#fleet">Browse Cars</a>
    </div>
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
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($active as $b):
                    $carName = 'Car #' . $b['car_id'];
                    $thumb = null;
                    foreach ($cars as $c) {
                        if ($c['id'] == $b['car_id']) {
                            $carName = $c['brand'] . ' ' . $c['name'];
                            $thumb = carease_car_image($c);
                            break;
                        }
                    }
                    $paid = false;
                    foreach ($payments[$b['id']] ?? [] as $p) {
                        if ($p['status'] === 'paid') {
                            $paid = true;
                        }
                    } ?>
                    <tr>
                        <td><?= (int) $b['id'] ?></td>
                        <td>
                            <div class="thumb-cell">
                                <?php if ($thumb): ?>
                                    <img class="thumb" src="<?= $h($thumb) ?>" alt="<?= $h($carName) ?>">
                                <?php else: ?>
                                    <span class="thumb thumb--empty"><?= carease_icon('car') ?></span>
                                <?php endif; ?>
                                <span><?= $h($carName) ?></span>
                            </div>
                        </td>
                        <td><?= $h($b['pickup_date']) ?></td>
                        <td><?= $h($b['return_date']) ?></td>
                        <td>₱<?= number_format((float) $b['total_amount'], 2) ?></td>
                        <td><span class="pill"><?= $h(ucfirst($b['status'])) ?></span></td>
                        <td>
                            <?php if (!$paid && in_array($b['status'], ['approved', 'confirmed'], true)): ?>
                                <form method="post"
                                    action="<?= site_url('carease/payment/checkout/' . (int) $b['id']) ?>">
                                    <input type="hidden" name="csrf" value="<?= $h($csrf) ?>">
                                    <button class="button small" type="submit">Pay Now</button>
                                </form>
                            <?php endif; ?>
                            <?php if (isset($contracts[(int) $b['id']]) || in_array($b['status'], ['approved', 'confirmed', 'ongoing', 'overdue'], true)): ?>
                                <a class="button small ghost"
                                    href="<?= site_url('carease/contract/' . (int) $b['id']) ?>">Agreement</a>
                            <?php elseif ($paid): ?>
                                &mdash;
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php if (!$active): ?>
        <p class="empty">You have no active rentals. <a href="<?= site_url('carease') ?>#fleet">Find a car</a> to get
            started.</p>
    <?php endif; ?>
</section>

<section class="panel">
    <h2>Booking history</h2>
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
                </tr>
            </thead>
            <tbody>
                <?php foreach ($past as $b):
                    $carName = 'Car #' . $b['car_id'];
                    foreach ($cars as $c) {
                        if ($c['id'] == $b['car_id']) {
                            $carName = $c['brand'] . ' ' . $c['name'];
                            break;
                        }
                    } ?>
                    <tr>
                        <td><?= (int) $b['id'] ?></td>
                        <td><?= $h($carName) ?></td>
                        <td><?= $h($b['pickup_date']) ?></td>
                        <td><?= $h($b['return_date']) ?></td>
                        <td>₱<?= number_format((float) $b['total_amount'], 2) ?></td>
                        <td><span class="pill"><?= $h(ucfirst($b['status'])) ?></span></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php if (!$past): ?>
        <p class="empty">No completed bookings yet.</p>
    <?php endif; ?>
</section>

<?php include __DIR__ . '/footer.php'; ?>
