<?php include __DIR__ . '/header.php'; ?>
<div class="ce-auth-layout"><section class="ce-auth-showcase"><a href="<?= site_url('carease') ?>" class="ce-auth-logo"><span class="ce-logo-icon">✦</span> CarEase <b>Rentals</b></a><div class="ce-auth-showcase-copy"><span class="ce-kicker">PREMIUM CAR RENTAL EXPERIENCE</span><h2>Every great journey<br>begins with <em>the right car.</em></h2><p>Reliable vehicles. Seamless bookings. A premium rental experience designed around you.</p><div class="ce-auth-features"><span>✓ Verified vehicles</span><span>✓ Secure reservations</span><span>✓ Dedicated support</span></div></div><div class="ce-auth-watermark">CAREASE RENTALS</div></section><section class="panel narrow auth-panel ce-auth-form"><div class="ce-auth-form-brand">CarEase <b>Rentals</b></div>
    <div style="display:flex;justify-content:flex-end;margin-bottom:8px"><?= carease_theme_toggle() ?></div>
    <p class="eyebrow">JOIN CAREASE</p>
    <h1>Create an account</h1><?php if ($error): ?>
        <p class="error"><?= $h($error) ?></p><?php endif; ?>
    <form method="post" action="<?= site_url('carease/register') ?>"><input type="hidden" name="csrf"
            value="<?= $h($csrf) ?>"><label>Full Name<input name="full_name" required maxlength="120"
                value="<?= $h($_POST['full_name'] ?? '') ?>"></label><label>Email<input type="email" name="email"
                required maxlength="190" value="<?= $h($_POST['email'] ?? '') ?>"></label><label>Password (at least 8
            characters)<input type="password" name="password" minlength="8" required
                autocomplete="new-password"></label><label>Confirm Password<input type="password"
                name="confirm_password" minlength="8" required autocomplete="new-password"></label><button
            class="button" type="submit">Register</button></form>
    <p>Already registered? <a href="<?= site_url('carease/login') ?>">Login</a></p>
</section></div><?php include __DIR__ . '/footer.php'; ?>