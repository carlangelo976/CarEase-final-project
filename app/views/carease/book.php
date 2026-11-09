<?php 

$appShell = 'client';
$appNavKey = 'fleet';
$appTitle = 'Book a Car';
$appSubtitle = $car['brand'] . ' ' . $car['name'];

$blockedRanges = isset($blockedRanges) && is_array($blockedRanges) ? $blockedRanges : [];
$customer = isset($customer) && is_array($customer) ? $customer : [];
$hasId = !empty($hasId);

include __DIR__ . '/header.php'; ?>
<section class="panel narrow">
    <p class="eyebrow">BOOKING REQUEST</p>
    <h1><?= $h($car['name']) ?></h1>
    <p>₱<?= number_format((float) $car['price_per_day'], 2) ?> per day</p><?php if ($error): ?>
        <p class="error"><?= $h($error) ?></p><?php endif; ?>

    <?php if (!$hasId): ?>
        <p class="error">You need a valid ID on file before booking. <a href="<?= site_url('carease/account/id') ?>">Upload
                your valid ID</a> and wait for admin verification.</p>
    <?php endif; ?>

    <div class="upload-field" style="margin-bottom:16px">
        <?php $thumb = carease_car_image($car); ?>
        <?php if ($thumb): ?>
            <img class="upload-preview" src="<?= $h($thumb) ?>" alt="<?= $h($car['brand'] . ' ' . $car['name']) ?>">
        <?php else: ?>
            <span class="upload-preview upload-preview--empty"><?= carease_icon('car') ?></span>
        <?php endif; ?>
        <div>
            <strong><?= $h($car['brand'] . ' ' . $car['name']) ?></strong>
            <p class="upload-hint"><?= (int) $car['seats'] ?> seats &middot; <?= $h(carease_transmission($car['id'])) ?></p>
        </div>
    </div>

    <?php if ($blockedRanges): ?>
        <div class="panel" style="margin-bottom:16px">
            <p class="eyebrow">RESERVED DATES</p>
            <p class="muted">This car cannot be booked on the following dates.</p>
            <ul>
                <?php foreach ($blockedRanges as $range): ?>
                    <li><?= $h($range['pickup_date']) ?> to <?= $h($range['return_date']) ?>
                        <span class="pill"><?= $h(ucfirst($range['status'])) ?></span></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="post" action="<?= site_url('carease/book/' . $car['id']) ?>"><input type="hidden" name="csrf"
            value="<?= $h($csrf) ?>"><label>Full name<input required maxlength="120" name="customer_name"
                id="customer-name"
                value="<?= $h($_POST['customer_name'] ?? ($customer['full_name'] ?? '')) ?>"></label><label>Email<input
                type="email" required name="email"
                value="<?= $h($_POST['email'] ?? ($customer['email'] ?? '')) ?>"></label><label>Phone<input required
                name="phone" maxlength="20" value="<?= $h($_POST['phone'] ?? '') ?>"></label>
        <div class="two"><label>Pickup date<input required type="date" name="pickup_date" id="pickup-date"
                    min="<?= date('Y-m-d') ?>"
                    value="<?= $h($_POST['pickup_date'] ?? '') ?>"></label><label>Return date<input required type="date"
                    name="return_date" id="return-date" min="<?= date('Y-m-d', strtotime('+1 day')) ?>"
                    value="<?= $h($_POST['return_date'] ?? '') ?>"></label></div>
        <p class="error" id="date-warning" hidden></p>
        <p class="muted">No payment is collected at this stage. A staff member must approve your request.</p><button
            class="button" type="submit"<?= $hasId ? '' : ' disabled' ?>>Submit Booking Request</button>
    </form>
</section>
<script>
    (function () {
        'use strict';
        var ranges = <?= json_encode($blockedRanges, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
        var pickup = document.getElementById('pickup-date');
        var ret = document.getElementById('return-date');
        var warning = document.getElementById('date-warning');
        if (!pickup || !ret || !warning) {
            return;
        }

        function inBlocked(date) {
            if (!date) {
                return false;
            }
            return ranges.some(function (r) {
                return date >= r.pickup_date && date < r.return_date;
            });
        }

        function insideReserved(date) {
            if (!date) {
                return false;
            }
            return ranges.some(function (r) {
                return date > r.pickup_date && date < r.return_date;
            });
        }

        function overlaps(start, end) {
            if (!start || !end) {
                return false;
            }
            return ranges.some(function (r) {
                return r.pickup_date < end && r.return_date > start;
            });
        }

        function validate() {
            var start = pickup.value;
            var end = ret.value;
            var message = '';
            if (inBlocked(start)) {
                message = 'That pickup date is already reserved.';
            } else if (insideReserved(end)) {
                message = 'That return date falls inside a reserved period.';
            } else if (overlaps(start, end)) {
                message = 'The selected range overlaps an existing reservation.';
            }
            pickup.setCustomValidity(inBlocked(start) ? 'reserved' : '');
            ret.setCustomValidity(insideReserved(end) || overlaps(start, end) ? 'reserved' : '');
            warning.textContent = message;
            warning.hidden = message === '';
        }

        pickup.addEventListener('change', validate);
        ret.addEventListener('change', validate);
        validate();
    })();
</script>
<?php include __DIR__ . '/footer.php'; ?>
