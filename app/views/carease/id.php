<?php
$appShell = 'client';
$appNavKey = 'id';
$appTitle = 'Valid ID';
$appSubtitle = 'Required before booking and signing the rental agreement';

$user = isset($user) && is_array($user) ? $user : [];
$idStatus = (string) ($user['id_status'] ?? 'none');
$idTypes = ['Philippine Passport', "Driver's License", 'UMID', 'PhilHealth ID', 'Postal ID', 'PRC ID', 'National ID'];
$statusLabels = [
    'none' => 'No ID submitted',
    'pending' => 'Pending verification',
    'verified' => 'Verified',
    'rejected' => 'Rejected',
];

include __DIR__ . '/header.php'; ?>
<div class="heading">
    <h1>Valid ID</h1>
    <a class="button ghost" href="<?= site_url('carease/account') ?>">Back to Account</a>
</div>

<?php if (!empty($notice)): ?>
    <p class="pill"><?= $h($notice) ?></p>
<?php endif; ?>
<?php if ($error): ?>
    <p class="error"><?= $h($error) ?></p>
<?php endif; ?>

<section class="panel">
    <h2>Verification status</h2>
    <p><span class="pill"><?= $h($statusLabels[$idStatus] ?? ucfirst($idStatus)) ?></span></p>
    <?php if ($idStatus === 'rejected' && !empty($user['id_notes'])): ?>
        <p class="error">Reason: <?= $h($user['id_notes']) ?></p>
    <?php endif; ?>
    <?php if (!empty($user['id_verified_at'])): ?>
        <p class="muted">Verified on <?= $h($user['id_verified_at']) ?>.</p>
    <?php endif; ?>
    <?php if ($idStatus === 'verified'): ?>
        <p class="muted">Your ID is verified. You can book a car and sign your rental agreement.</p>
    <?php elseif ($idStatus === 'pending'): ?>
        <p class="muted">Your ID is waiting for admin review. You can book while it is pending, but the vehicle will
            only be released after verification.</p>
    <?php endif; ?>

    <?php if (!empty($user['id_type']) || !empty($user['id_number'])): ?>
        <div class="two">
            <div><strong>ID type</strong><br><?= $h($user['id_type'] ?? '') ?></div>
            <div><strong>ID number</strong><br><?= $h($user['id_number'] ?? '') ?></div>
        </div>
    <?php endif; ?>

    <?php if (!empty($user['id_image'])): ?>
        <div class="upload-field" style="margin-top:16px">
            <a href="<?= site_url('carease/id/image/' . (int) ($user['id'] ?? 0)) ?>" target="_blank"
                rel="noopener">View submitted ID photo</a>
        </div>
    <?php endif; ?>
</section>

<section class="panel">
    <h2><?= !empty($user['id_image']) ? 'Replace your ID' : 'Submit your ID' ?></h2>
    <form method="post" action="<?= site_url('carease/account/id') ?>" enctype="multipart/form-data">
        <input type="hidden" name="csrf" value="<?= $h($csrf) ?>">
        <div class="two">
            <label>ID type
                <select name="id_type" required>
                    <option value="">Choose ID type</option>
                    <?php foreach ($idTypes as $type): ?>
                        <option value="<?= $h($type) ?>"<?= ($user['id_type'] ?? '') === $type ? ' selected' : '' ?>>
                            <?= $h($type) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>ID number
                <input name="id_number" required maxlength="60" value="<?= $h($user['id_number'] ?? '') ?>"
                    placeholder="Enter the number on your ID">
            </label>
        </div>
        <div class="upload-field">
            <span class="upload-preview upload-preview--empty"><?= carease_icon('image') ?></span>
            <div>
                <label class="upload-control">ID photo
                    <input type="file" name="id_image" accept="image/jpeg,image/png,image/webp,image/gif"
                        <?= empty($user['id_image']) ? 'required' : '' ?>>
                    <span class="upload-hint">JPG, PNG, WEBP or GIF &middot; max 5 MB. Make sure the ID details are
                        readable. Leave empty to keep your current photo.</span>
                </label>
            </div>
        </div>
        <p class="muted">Submitting a new ID resets the status to pending until an admin verifies it. Your ID photo is
            stored privately and is only visible to you and CarEase admins.</p>
        <button class="button" type="submit">Submit for Verification</button>
    </form>
</section>
<?php include __DIR__ . '/footer.php'; ?>
