<?php
$splitHeader = true;
require_once __DIR__ . '/_helpers.php';
include __DIR__ . '/header.php';

$h = function ($s) {
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); };

$cars = isset($cars) && is_array($cars) ? $cars : [];
$available = array_values(array_filter($cars, function ($c) {
    return ($c['status'] ?? '') === 'available';
}));
$featured = array_slice($available, 0, 3);
$categories = ['All Cars', 'Economy', 'SUV', 'Luxury', 'Van'];

$badges = [
    ['road', 'Wide Range'],
    ['tag', 'Best Price'],
    ['clock', '24/7 Availability'],
    ['shield', 'Secure Booking'],
    ['badge', 'Quality Guarantee'],
    ['headset', 'Booking Support'],
];

$features = [
    ['clock', 'Flexible Booking', 'Plans that fit your schedule, not the other way around.'],
    ['shield', 'Well-Maintained Cars', 'Clean, safe and ready for the road on every single pickup.'],
    ['tag', 'Affordable Rates', 'Premium cars at the best prices, with no hidden charges.'],
    ['headset', '24/7 Support', 'We are always here to help, whenever you need us.'],
];

$steps = [
    ['Search', 'Find your perfect car from our fleet in a few taps.'],
    ['Pay', 'Secure and easy payment options, processed instantly.'],
    ['Drive', 'Pick up your car and hit the road in minutes.'],
    ['Relax', 'Sit back and enjoy the ride — we handle the rest.'],
];
?>

<?php include __DIR__ . '/_nav.php'; ?>

<section class="hero" id="home">
    <div class="hero-wrap">
        <div class="hero-inner">
            <p class="eyebrow">Car Rental Booking</p>
            <h1>Drive Your Next <span>Adventure</span><br>Book Your Dream Car in Minutes</h1>
            <p class="hero-lead">From city rides to weekend getaways, find the perfect car, anytime, anywhere.</p>

            <div class="hero-badges">
                <?php foreach ($badges as [$icon, $label]): ?>
                    <div class="hero-badge"><i><?= carease_icon($icon) ?></i><span><?= $h($label) ?></span></div>
                <?php endforeach; ?>
            </div>

            <div class="hero-tags">
                <span class="hero-tag"><?= carease_icon('car') ?> Rent a Car</span>
                <span class="hero-tag"><?= carease_icon('road') ?> One way</span>
                <span class="hero-tag"><?= carease_icon('plane') ?> Airport Transfer</span>
            </div>

            <div class="search-card">
                <form method="get" action="<?= site_url('carease') ?>#fleet">
                    <div class="search-grid">
                        <div class="search-field">
                            <label for="pickup-location">Pick-up Location</label>
                            <input id="pickup-location" name="pickup_location" type="text" placeholder="Select location">
                        </div>
                        <div class="search-field">
                            <label for="dropoff-location">Drop-off Location</label>
                            <input id="dropoff-location" name="dropoff_location" type="text" placeholder="Select location">
                        </div>
                        <div class="search-field">
                            <label for="pickup-date">Pick-up Date &amp; Time</label>
                            <input id="pickup-date" name="pickup_date" type="datetime-local">
                        </div>
                        <div class="search-field">
                            <label for="dropoff-date">Drop-off Date &amp; Time</label>
                            <input id="dropoff-date" name="dropoff_date" type="datetime-local">
                        </div>
                    </div>
                    <button class="btn btn-accent btn-lg" type="submit">Search Cars</button>
                </form>
            </div>
        </div>
        <div class="hero-art" aria-hidden="true">
            <?= carease_car_art('SUV') ?>
        </div>
    </div>
</section>

<section class="section" id="popular">
    <div class="section-head">
        <p class="eyebrow">Popular Cars</p>
        <h2>Choose from a wide range of vehicles for every journey.</h2>
        <p>Hand-picked favourites our renters book the most.</p>
    </div>

    <div class="mini-cars mini-cars--wide">
        <?php foreach ($featured as $car):
            $category = carease_category($car);
            $rating = carease_rating($car['id']); ?>
            <article class="mini-car">
                <?= carease_car_media($car, $category, $rating) ?>
                <div class="mini-car-body">
                    <div class="mini-car-top">
                        <div>
                            <span class="car-tag"><?= $h($category) ?></span>
                            <h4><?= $h($car['brand'] . ' ' . $car['name']) ?></h4>
                        </div>
                        <span class="car-price">₱<?= number_format((float) $car['price_per_day'], 0) ?><small>/day</small></span>
                    </div>
                    <div class="car-meta">
                        <span><?= carease_icon('steering') ?> <?= (int) $car['seats'] ?> Seats</span>
                        <span><?= carease_icon('gear') ?> <?= $h(carease_transmission($car['id'])) ?></span>
                    </div>
                    <a class="btn btn-accent btn-sm btn-block" href="<?= site_url('carease/book/' . (int) $car['id']) ?>">Book Now</a>
                </div>
            </article>
        <?php endforeach; ?>
    </div>

    <?php if (!$featured): ?>
        <p class="empty">No cars available right now.</p>
    <?php endif; ?>

    <div class="section-cta">
        <a class="btn btn-navy" href="<?= site_url('carease') ?>#fleet">View All Cars →</a>
    </div>
</section>

<section class="section section--surface" id="why">
    <div class="section-head">
        <p class="eyebrow">Why Choose CarEase?</p>
        <h2>More than just a car rental &mdash; it&rsquo;s your freedom on wheels.</h2>
    </div>
    <div class="feature-grid">
        <?php foreach ($features as [$icon, $title, $text]): ?>
            <article class="feature-card">
                <div class="feature-icon"><?= carease_icon($icon) ?></div>
                <h3><?= $h($title) ?></h3>
                <p><?= $h($text) ?></p>
            </article>
        <?php endforeach; ?>
    </div>
</section>

<section class="section" id="fleet">
    <div class="section-head">
        <p class="eyebrow">Our Fleet</p>
        <h2>Find the perfect ride for your travel needs.</h2>
        <p>Every vehicle is inspected, cleaned and ready for the road.</p>
    </div>

    <div class="fleet-filters" id="fleet-filters">
        <?php foreach ($categories as $i => $cat): ?>
            <button type="button" class="filter-chip<?= $i === 0 ? ' is-active' : '' ?>" data-filter="<?= $h($cat) ?>"><?= $h($cat) ?></button>
        <?php endforeach; ?>
    </div>

    <div class="car-grid" id="fleet-grid">
        <?php foreach ($cars as $car):
            $category = carease_category($car);
            $rating = carease_rating($car['id']);
            $isAvailable = ($car['status'] ?? '') === 'available'; ?>
            <article class="car-card" data-category="<?= $h($category) ?>">
                <?= carease_car_media($car, $category, $rating) ?>
                <div class="car-body">
                    <span class="car-brand"><?= $h($car['brand']) ?></span>
                    <h3 class="car-name"><?= $h($car['name']) ?></h3>
                    <div class="car-price-row">
                        <span class="amount">₱<?= number_format((float) $car['price_per_day'], 0) ?></span>
                        <span class="per">/ day</span>
                    </div>
                    <div class="car-meta">
                        <span><?= carease_icon('steering') ?> <?= (int) $car['seats'] ?> seats</span>
                        <span><?= carease_icon('gear') ?> <?= $h(carease_transmission($car['id'])) ?></span>
                    </div>
                    <div class="car-actions">
                        <?php if ($isAvailable): ?>
                            <a class="btn btn-accent btn-block" href="<?= site_url('carease/book/' . (int) $car['id']) ?>">Book Now</a>
                        <?php else: ?>
                            <span class="btn btn-block disabled">In Maintenance</span>
                        <?php endif; ?>
                    </div>
                </div>
            </article>
        <?php endforeach; ?>
    </div>

    <?php if (!$cars): ?>
        <p class="empty">No cars in the fleet yet. An admin can add cars from the dashboard.</p>
    <?php endif; ?>
</section>

<section class="section section--surface" id="how">
    <div class="section-head">
        <p class="eyebrow">How It Works</p>
        <h2>Get on the road in 4 simple steps.</h2>
    </div>
    <div class="steps">
        <?php foreach ($steps as $i => [$title, $text]): ?>
            <div class="step">
                <div class="step-num"><?= $i + 1 ?></div>
                <h4><?= $h($title) ?></h4>
                <p><?= $h($text) ?></p>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<section class="journey" id="journey">
    <div class="journey-art" aria-hidden="true">
        <?= carease_car_art('Van') ?>
    </div>
    <div class="journey-inner">
        <p class="eyebrow">Explore More</p>
        <h3>Your Journey,<br>Our Priority</h3>
        <p>Whimsical journeys deserve the right ride &mdash; the right car, at the right time, wherever the road takes you.</p>
        <a class="btn btn-accent" href="<?= site_url('carease') ?>#fleet">Book Now</a>
    </div>
</section>

<section class="section" id="reviews">
    <div class="section-head">
        <p class="eyebrow">What Our Customers Say</p>
        <h2>Real reviews from real renters.</h2>
    </div>
    <div class="review-grid">
        <article class="review">
            <div class="review-stars">
                <?php for ($i = 0; $i < 5; $i++) {
                    echo carease_icon('star');
                } ?>
            </div>
            <p>&ldquo;The booking process was super easy, and the car was in excellent condition. I will definitely use CarEase again!&rdquo;</p>
            <div class="reviewer">
                <span class="reviewer-avatar">SK</span>
                <span>
                    <strong>Sarah Khan</strong>
                    <small>CarEase customer</small>
                </span>
            </div>
        </article>
        <article class="review">
            <div class="review-stars">
                <?php for ($i = 0; $i < 5; $i++) {
                    echo carease_icon('star');
                } ?>
            </div>
            <p>&ldquo;Great rates and the team was very responsive. My whole family enjoyed the trip without a single hitch.&rdquo;</p>
            <div class="reviewer">
                <span class="reviewer-avatar">MR</span>
                <span>
                    <strong>Marco Reyes</strong>
                    <small>CarEase customer</small>
                </span>
            </div>
        </article>
    </div>
</section>

<section class="app-block" id="app">
    <div class="app-block-text">
        <p class="eyebrow">On The Go</p>
        <h3>Book Anytime, Anywhere.</h3>
        <p>Download our app for a faster, easier and smarter booking experience.</p>
    </div>
    <div class="store-btns">
        <a class="store-btn" href="#" onclick="return false;">
            <?= carease_icon('app') ?>
            <span><span>Download on the</span><strong>App Store</strong></span>
        </a>
        <a class="store-btn" href="#" onclick="return false;">
            <?= carease_icon('play') ?>
            <span><span>Get it on</span><strong>Play Store</strong></span>
        </a>
    </div>
</section>

<section class="cta-main" id="cta">
    <div class="cta-art" aria-hidden="true">
        <?= carease_car_art('Luxury') ?>
    </div>
    <div style="position:relative;z-index:2">
        <p class="eyebrow">Discover The Freedom</p>
        <h2>Rent a Car<br>New places, stories and roads await you.</h2>
        <p>Explore more today and make every mile count.</p>
        <a class="btn btn-accent btn-lg" href="<?= site_url('carease') ?>#fleet">Explore Now</a>
    </div>
</section>

<script>
    (function () {
        var chips = document.querySelectorAll('#fleet-filters .filter-chip');
        var cards = document.querySelectorAll('#fleet-grid .car-card');
        chips.forEach(function (chip) {
            chip.addEventListener('click', function () {
                var filter = chip.getAttribute('data-filter');
                chips.forEach(function (c) {
                    c.classList.remove('is-active');
                });
                chip.classList.add('is-active');
                cards.forEach(function (card) {
                    var show = filter === 'All Cars' || card.getAttribute('data-category') === filter;
                    card.style.display = show ? '' : 'none';
                });
            });
        });
    })();
</script>

<?php include __DIR__ . '/footer.php'; ?>
