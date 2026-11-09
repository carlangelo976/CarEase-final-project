<?php

require_once __DIR__ . '/_helpers.php';

$h = function ($s) {
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); };

$appShell = isset($appShell) ? $appShell : 'client';
$appNavKey = isset($appNavKey) ? $appNavKey : '';

$adminNav = [
    ['key' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'grid', 'url' => site_url('carease/admin')],
    ['key' => 'fleet', 'label' => 'Fleet & Cars', 'icon' => 'car', 'url' => site_url('carease/admin') . '#fleet'],
    ['key' => 'bookings', 'label' => 'Bookings', 'icon' => 'list', 'url' => site_url('carease/admin') . '#bookings'],
    ['key' => 'ids', 'label' => 'Valid IDs', 'icon' => 'badge', 'url' => site_url('carease/admin') . '#ids'],
    ['key' => 'maintenance', 'label' => 'Maintenance', 'icon' => 'wrench', 'url' => site_url('carease/admin/maintenance')],
    ['key' => 'reports', 'label' => 'Reports & Charts', 'icon' => 'chart', 'url' => site_url('carease/admin/reports')],
    ['key' => 'calendar', 'label' => 'Calendar', 'icon' => 'calendar', 'url' => site_url('carease/calendar')],
];

$clientNav = [
    ['key' => 'home', 'label' => 'My Account', 'icon' => 'grid', 'url' => site_url('carease/account')],
    ['key' => 'fleet', 'label' => 'Browse Cars', 'icon' => 'car', 'url' => site_url('carease') . '#fleet'],
    ['key' => 'bookings', 'label' => 'My Bookings', 'icon' => 'list', 'url' => site_url('carease/my-bookings')],
    ['key' => 'id', 'label' => 'Valid ID', 'icon' => 'badge', 'url' => site_url('carease/account/id')],
    ['key' => 'support', 'label' => 'Support', 'icon' => 'headset', 'url' => site_url('carease') . '#support'],
];

$nav = $appShell === 'admin' ? $adminNav : $clientNav;
$initials = '';
if (!empty($careaseUser)) {
    $parts = preg_split('/\s+/', trim($careaseUser));
    $initials = mb_strtoupper(mb_substr($parts[0] ?? 'C', 0, 1) . mb_substr($parts[1] ?? '', 0, 1));
}
?>
<aside class="app-sidebar" id="appSidebar">
    <div class="sidebar-top">
        <a class="sidebar-brand" href="<?= site_url('carease') ?>">
            <span class="brand-mark">
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path
                        d="M5 11l1.5-4.5A2 2 0 0 1 8.4 5h7.2a2 2 0 0 1 1.9 1.5L19 11a3 3 0 0 1 2 2.8V17a1 1 0 0 1-1 1h-1a2 2 0 0 1-2-2v-1H7v1a2 2 0 0 1-2 2H4a1 1 0 0 1-1-1v-3.2A3 3 0 0 1 5 11zm2.6 1.5a1.5 1.5 0 1 0 0 3 1.5 1.5 0 0 0 0-3zm8.8 0a1.5 1.5 0 1 0 0 3 1.5 1.5 0 0 0 0-3z" />
                </svg>
            </span>
            <span class="sidebar-brand-text">
                <strong>CarEase <span class="ce-brand-gold">Rentals</span></strong>
                <small><?= $appShell === 'admin' ? 'Admin Console' : 'Client Portal' ?></small>
            </span>
        </a>
        <button class="sidebar-toggle" type="button" data-sidebar-toggle
            aria-label="Toggle sidebar" aria-expanded="true" aria-controls="appSidebar">
            <span class="sidebar-toggle-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24">
                    <path d="M3 6h18v2H3V6zm0 5h18v2H3v-2zm0 5h18v2H3v-2z" />
                </svg>
            </span>
        </button>
    </div>

    <nav class="sidebar-nav" aria-label="Main navigation">
        <p class="sidebar-label"><?= $appShell === 'admin' ? 'Manage' : 'Menu' ?></p>
        <?php foreach ($nav as $item): ?>
            <a class="sidebar-link<?= $appNavKey === $item['key'] ? ' is-active' : '' ?>" href="<?= $h($item['url']) ?>"
                title="<?= $h($item['label']) ?>">
                <span class="sidebar-icon"><?= carease_icon($item['icon']) ?></span>
                <span class="sidebar-text"><?= $h($item['label']) ?></span>
            </a>
        <?php endforeach; ?>
    </nav>

    <div class="sidebar-foot">
        <div class="sidebar-user">
            <span class="sidebar-avatar"><?= $h($initials ?: 'C') ?></span>
            <span class="sidebar-user-text">
                <strong><?= $h($careaseUser ?: 'Guest') ?></strong>
                <small><?= $appShell === 'admin' ? 'Administrator' : 'Renter' ?></small>
            </span>
        </div>
        <a class="sidebar-link" href="<?= site_url('carease') ?>" title="Back to Website">
            <span class="sidebar-icon"><?= carease_icon('globe') ?></span>
            <span class="sidebar-text">Back to Website</span>
        </a>
        <form class="logout-form sidebar-logout" method="post" action="<?= site_url('carease/logout') ?>">
            <input type="hidden" name="csrf" value="<?= $h($csrf ?? '') ?>">
            <button class="sidebar-link sidebar-logout-btn" type="submit" title="Logout">
                <span class="sidebar-icon"><?= carease_icon('logout') ?></span>
                <span class="sidebar-text">Logout</span>
            </button>
        </form>
    </div>
</aside>
<div class="app-backdrop" hidden></div>
