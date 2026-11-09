<?php

require_once __DIR__ . '/_helpers.php';

$h = function ($s) {
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); };

// $appShell is set by the views that render inside the collapsible app shell
// ('admin' or 'client'). When empty the public marketing header is used.
$appShell = isset($appShell) ? $appShell : '';
$isAdmin = !empty($isAdmin);
$careaseUser = $careaseUser ?? null;
$bodyClass = trim(($bodyClass ?? '') . ($appShell ? ' has-app-shell' : ''));
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>CarEase Rentals</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Inter:wght@400;500;600;700&display=swap"
        rel="stylesheet">
    <?php $careaseCssVersion = @filemtime(__DIR__ . '/../../../public/assets/carease/style.css') ?: '2026'; ?>
    <link rel="stylesheet" href="<?= $h(base_url('assets/carease/style.css')) ?>?v=<?= $h($careaseCssVersion) ?>">
    <script>
        (function () {
            try {
                var stored = window.localStorage.getItem('carease.theme');
                var theme = (stored === 'light' || stored === 'dark')
                    ? stored
                    : ((window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) ? 'dark' : 'light');
                document.documentElement.setAttribute('data-theme', theme);
                document.documentElement.style.colorScheme = theme;
            } catch (e) { /* theme falls back to light */ }
        })();
    </script>
</head>

<body class="<?= $h($bodyClass) ?>">
    <?php if ($appShell): ?>
        <div class="app-shell" id="appShell">
            <?php include __DIR__ . '/_sidebar.php'; ?>
            <div class="app-body">
                <header class="app-topbar">
                    <button class="app-menu-btn" type="button" data-sidebar-toggle aria-label="Toggle sidebar"
                        aria-expanded="true">
                        <?= carease_icon('menu') ?>
                    </button>
                    <div class="app-topbar-text">
                        <h1><?= $h($appTitle ?? 'Dashboard') ?></h1>
                        <p><?= $h($appSubtitle ?? '') ?></p>
                    </div>
                    <div class="app-topbar-actions">
                        <?php if ($appShell === 'admin'): ?>
                            <a class="btn btn-ghost btn-sm" href="<?= site_url('carease') ?>">View Website</a>
                        <?php else: ?>
                            <a class="btn btn-accent btn-sm" href="<?= site_url('carease') ?>#fleet">Browse Cars</a>
                        <?php endif; ?>
                        <?= carease_theme_toggle() ?>
                    </div>
                </header>
                <main class="app-content">
    <?php else: ?>
        <main class="site-main">
            <?php if (empty($splitHeader)): ?>
                <?php include __DIR__ . '/_nav.php'; ?>
                <div class="page-body">
            <?php endif; ?>
    <?php endif; ?>
