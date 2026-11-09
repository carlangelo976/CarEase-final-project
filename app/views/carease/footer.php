<?php
require_once __DIR__ . '/_helpers.php';

$h = function ($s) {
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); };

$stats = isset($stats) && is_array($stats) ? $stats : ['customers' => 0, 'cars' => 0, 'locations' => 0];
$appShell = isset($appShell) ? $appShell : '';
?>
    <?php if ($appShell): ?>
                </main>
            </div>
        </div>
        <script src="<?= $h(base_url('assets/carease/app.js')) ?>"></script>
    <?php else: ?>
        <?php if (empty($splitHeader)): ?>
    </div>
        <?php endif; ?>
    </main>
    <?php endif; ?>

    <?php if (!$appShell): ?>
    <footer class="site-footer" id="support">
        <div class="footer-inner">
            <div class="footer-brand">
                <a class="brand" href="<?= site_url('carease') ?>">
                    <span class="brand-mark">
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <path
                                d="M5 11l1.5-4.5A2 2 0 0 1 8.4 5h7.2a2 2 0 0 1 1.9 1.5L19 11a3 3 0 0 1 2 2.8V17a1 1 0 0 1-1 1h-1a2 2 0 0 1-2-2v-1H7v1a2 2 0 0 1-2 2H4a1 1 0 0 1-1-1v-3.2A3 3 0 0 1 5 11zm2.6 1.5a1.5 1.5 0 1 0 0 3 1.5 1.5 0 0 0 0-3zm8.8 0a1.5 1.5 0 1 0 0 3 1.5 1.5 0 0 0 0-3z" />
                        </svg>
                    </span>
                    <span class="brand-text">
                        <strong>CarEase</strong>
                        <small>Car Rental Booking</small>
                    </span>
                </a>
                <p class="footer-tag">Drive more. Worry less.</p>
                <div class="footer-stats">
                    <div class="footer-stat">
                        <strong><?= (int) $stats['customers'] ?></strong>
                        <span>Happy Customers</span>
                    </div>
                    <div class="footer-stat">
                        <strong><?= (int) $stats['cars'] ?></strong>
                        <span>Cars Available</span>
                    </div>
                    <div class="footer-stat">
                        <strong><?= (int) $stats['locations'] ?></strong>
                        <span>Locations</span>
                    </div>
                </div>
            </div>

            <div class="footer-col">
                <h4>Quick Links</h4>
                <ul>
                    <li><a href="<?= site_url('carease') ?>">Home</a></li>
                    <li><a href="<?= site_url('carease') ?>#fleet">Cars</a></li>
                    <li><a href="<?= site_url('carease') ?>#why">About</a></li>
                    <li><a href="<?= site_url('carease') ?>#how">How It Works</a></li>
                    <li><a href="<?= site_url('carease/my-bookings') ?>">My Bookings</a></li>
                </ul>
            </div>

            <div class="footer-col">
                <h4>Contact Us</h4>
                <ul class="footer-contact">
                    <li><?= carease_icon('phone') ?><span>+63 917 555 0134</span></li>
                    <li><?= carease_icon('mail') ?><span>hello@carease.bookings</span></li>
                    <li><?= carease_icon('pin') ?><span>Quezon City, Metro Manila</span></li>
                    <li><?= carease_icon('clock') ?><span>Mon - Sat, 8:00 AM - 8:00 PM</span></li>
                </ul>
            </div>
        </div>

        <div class="footer-bottom">
            <span>&copy; <?= date('Y') ?> CarEase. All rights reserved.</span>
            <nav>
                <a href="<?= site_url('carease') ?>#why">Privacy Policy</a>
                <a href="<?= site_url('carease') ?>#how">Terms &amp; Conditions</a>
            </nav>
        </div>
    </footer>
    <?php endif; ?>

    <script>
        (function () {
            var root = document.documentElement;

            function currentTheme() {
                return root.getAttribute('data-theme') === 'dark' ? 'dark' : 'light';
            }

            function sync(theme) {
                var dark = theme === 'dark';
                root.setAttribute('data-theme', theme);
                root.style.colorScheme = theme;

                Array.prototype.forEach.call(document.querySelectorAll('[data-theme-toggle]'), function (btn) {
                    btn.setAttribute('aria-label', dark ? 'Switch to light mode' : 'Switch to dark mode');
                    btn.setAttribute('title', dark ? 'Switch to light mode' : 'Switch to dark mode');
                    btn.setAttribute('aria-pressed', dark ? 'true' : 'false');

                    var sun = btn.querySelector('[data-icon-sun]');
                    var moon = btn.querySelector('[data-icon-moon]');
                    if (sun) { sun.style.display = dark ? 'block' : 'none'; }
                    if (moon) { moon.style.display = dark ? 'none' : 'block'; }
                });
            }

            function apply(theme) {
                try { window.localStorage.setItem('carease.theme', theme); } catch (e) { /* ignore */ }
                sync(theme);
            }

            sync(currentTheme());

            document.addEventListener('click', function (event) {
                var btn = event.target.closest ? event.target.closest('[data-theme-toggle]') : null;
                if (!btn) { return; }
                event.preventDefault();
                apply(currentTheme() === 'dark' ? 'light' : 'dark');
            });

            try {
                var mq = window.matchMedia('(prefers-color-scheme: dark)');
                var onChange = function (event) {
                    var stored = null;
                    try { stored = window.localStorage.getItem('carease.theme'); } catch (e) { /* ignore */ }
                    if (stored === 'light' || stored === 'dark') { return; }
                    apply(event.matches ? 'dark' : 'light');
                };
                if (mq.addEventListener) { mq.addEventListener('change', onChange); }
                else if (mq.addListener) { mq.addListener(onChange); }
            } catch (e) { /* ignore */ }
        })();
    </script>
</body>

</html>
