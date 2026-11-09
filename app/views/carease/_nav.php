<?php
$h = function ($s) {
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); };
?>
<header class="site-header">
    <div class="header-inner">
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

        <nav class="site-nav">
            <a href="<?= site_url('carease') ?>">Home</a>
            <a href="<?= site_url('carease') ?>#fleet">Cars</a>
            <a href="<?= site_url('carease') ?>#why">About</a>
            <a href="<?= site_url('carease') ?>#how">How It Works</a>
            <a href="<?= site_url('carease') ?>#support">Support</a>
        </nav>

        <div class="header-actions">
            <?php if (!empty($careaseUser)): ?>
                <?php if (!empty($isAdmin)): ?>
                    <a class="btn btn-on-dark btn-sm" href="<?= site_url('carease/admin') ?>">Dashboard</a>
                <?php else: ?>
                    <a class="btn btn-on-dark btn-sm" href="<?= site_url('carease/account') ?>">My Account</a>
                <?php endif; ?>
                <span class="nav-user">Hi, <?= $h($careaseUser) ?></span>
                <form class="logout-form" method="post" action="<?= site_url('carease/logout') ?>">
                    <input type="hidden" name="csrf" value="<?= $h($csrf ?? '') ?>">
                    <button class="btn btn-on-dark btn-sm" type="submit">Logout</button>
                </form>
            <?php else: ?>
                <a class="btn btn-on-dark btn-sm" href="<?= site_url('carease/login') ?>">Sign In</a>
                <a class="btn btn-accent btn-sm" href="<?= site_url('carease/register') ?>">Register</a>
            <?php endif; ?>
            <?= carease_theme_toggle() ?>
        </div>
    </div>
</header>
