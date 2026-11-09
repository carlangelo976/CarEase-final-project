<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

/**
 * Shared helpers for the CarEase views.
 */

if (!function_exists('carease_icon')) {
    /**
     * Returns an inline SVG icon (24x24 viewBox) for the given name.
     */
    function carease_icon($name, $class = '')
    {
        $paths = [
            'car' => '<path d="M5 11l1.5-4.5A2 2 0 0 1 8.4 5h7.2a2 2 0 0 1 1.9 1.5L19 11a3 3 0 0 1 2 2.8V17a1 1 0 0 1-1 1h-1a2 2 0 0 1-2-2v-1H7v1a2 2 0 0 1-2 2H4a1 1 0 0 1-1-1v-3.2A3 3 0 0 1 5 11zm2.6 1.5a1.5 1.5 0 1 0 0 3 1.5 1.5 0 0 0 0-3zm8.8 0a1.5 1.5 0 1 0 0 3 1.5 1.5 0 0 0 0-3z"/>',
            'road' => '<path d="M11 2h2l-1 20h-2l1-20zm-3.4 0h2L8.2 22h-2L7.6 2zm8.8 0h2l1.4 20h-2L16.4 2z"/>',
            'tag' => '<path d="M21.4 11.6l-9-9A2 2 0 0 0 11 2H4a2 2 0 0 0-2 2v7a2 2 0 0 0 .6 1.4l9 9a2 2 0 0 0 2.8 0l7-7a2 2 0 0 0 0-2.8zM7.5 9A1.5 1.5 0 1 1 7.5 6 1.5 1.5 0 0 1 7.5 9z"/>',
            'clock' => '<path d="M12 2a10 10 0 1 0 10 10A10 10 0 0 0 12 2zm1 10.6l4.2 2.5-1 1.7-5.2-3.1V6h2z"/>',
            'shield' => '<path d="M12 2l8 3.2v6c0 5-3.4 9.4-8 10.8-4.6-1.4-8-5.8-8-10.8v-6L12 2zm-1 13.4l5.3-5.3-1.4-1.4L11 12.6 9.1 10.7 7.7 12.1 11 15.4z"/>',
            'badge' => '<path d="M12 2l2.4 2.1 3.1-.4 1 3 2.8 1.5-1 3 1 3-2.8 1.5-1 3-3.1-.4L12 22l-2.4-2.1-3.1.4-1-3L2.7 15l1-3-1-3L5.5 7.5l1-3 3.1.4L12 2zm0 6a4 4 0 1 0 4 4 4 4 0 0 0-4-4z"/>',
            'headset' => '<path d="M12 2a9 9 0 0 0-9 9v6a3 3 0 0 0 3 3h2v-8H5v-1a7 7 0 1 1 14 0h-3v8h2a3 3 0 0 0 3-3v-6a9 9 0 0 0-9-9z"/>',
            'pin' => '<path d="M12 2a7 7 0 0 0-7 7c0 5.2 7 13 7 13s7-7.8 7-13a7 7 0 0 0-7-7zm0 9.5A2.5 2.5 0 1 1 14.5 9 2.5 2.5 0 0 1 12 11.5z"/>',
            'plane' => '<path d="M21 16v-2l-8-5V3.5a1.5 1.5 0 0 0-3 0V9l-8 5v2l8-2.5V19l-2 1.5V22l3.5-1 3.5 1v-1.5L13 19v-5.5z"/>',
            'truck' => '<path d="M3 6h11v9H3V6zm12 4h4l3 3.5V17a1 1 0 0 1-1 1h-1a2 2 0 0 1-2-2v-1h-3v1a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2v-1H3a1 1 0 0 1-1-1v-9a1 1 0 0 1 1-1zm1 2v4h3V8h-3zM7 17a1.5 1.5 0 1 0 0 3 1.5 1.5 0 0 0 0-3zm11 0a1.5 1.5 0 1 0 0 3 1.5 1.5 0 0 0 0-3z"/>',
            'steering' => '<path d="M12 2a10 10 0 1 0 10 10A10 10 0 0 0 12 2zm0 2.5c1.2 0 3 2.4 3.6 5.3h-7.2C9 6.9 10.8 4.5 12 4.5zM4.6 13h3.1a15 15 0 0 0 1.1 4.7A8 8 0 0 1 4.6 13zm4.9 0h5a15 15 0 0 1-.9 4.9A8 8 0 0 1 9.5 13zm6.8 0h3.9a8 8 0 0 1-4.2 4.7A15 15 0 0 0 16.3 13zM4.6 11a8 8 0 0 1 4.2-4.7A15 15 0 0 0 7.7 11H4.6zm7.3 0h4.2a15 15 0 0 0-1.1-4.7A8 8 0 0 1 11.9 11zm5.4-4.7A8 8 0 0 1 19.4 11h-3.1a15 15 0 0 0-1.1-4.7z"/>',
            'gear' => '<path d="M19.4 13a7.6 7.6 0 0 0 0-2l2-1.6-2-3.4-2.4 1a7.3 7.3 0 0 0-1.7-1L15 3h-4l-.3 3a7.3 7.3 0 0 0-1.7 1l-2.4-1-2 3.4L6.6 11a7.6 7.6 0 0 0 0 2l-2 1.6 2 3.4 2.4-1a7.3 7.3 0 0 0 1.7 1l.3 3h4l.3-3a7.3 7.3 0 0 0 1.7-1l2.4 1 2-3.4zM13 15.5A3.5 3.5 0 1 1 13 8.5a3.5 3.5 0 0 1 0 7z"/>',
            'star' => '<path d="M12 2l3.1 6.3 6.9 1-5 4.9 1.2 6.8L12 17.8 5.8 21l1.2-6.8-5-4.9 6.9-1L12 2z"/>',
            'phone' => '<path d="M6.6 2h3l1.5 4-2 1.4a12 12 0 0 0 5.5 5.5l1.4-2 4 1.5v3A2 2 0 0 1 18 17.6 15.4 15.4 0 0 1 6.4 6 2 2 0 0 1 6.6 2z"/>',
            'mail' => '<path d="M3 5h18a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1H3a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1zm9 8L4.5 7.5v.6L12 12l7.5-3.9v-.6L12 13z"/>',
            'globe' => '<path d="M12 2a10 10 0 1 0 10 10A10 10 0 0 0 12 2zm6.9 6h-3a15.6 15.6 0 0 0-1.3-3.6A8 8 0 0 1 18.9 8zM12 4.2c.8 1 1.4 2.2 1.8 3.8h-3.6c.4-1.6 1-2.8 1.8-3.8zM4.3 14A8 8 0 0 1 4 12c0-.7.1-1.4.3-2h3.4a17 17 0 0 0 0 4zm.8 2h3a15.6 15.6 0 0 0 1.3 3.6A8 8 0 0 1 5.1 16zm3-8h-3a8 8 0 0 1 4.3-3.6A15.6 15.6 0 0 0 8.1 8zM12 19.8c-.8-1-1.4-2.2-1.8-3.8h3.6c-.4 1.6-1 2.8-1.8 3.8zm2.1-5.8H9.9a15 15 0 0 1 0-4h4.2a15 15 0 0 1 0 4zm.5 5.6a15.6 15.6 0 0 0 1.3-3.6h3a8 8 0 0 1-4.3 3.6zM16.3 14a17 17 0 0 0 0-4h3.4c.2.6.3 1.3.3 2s-.1 1.4-.3 2z"/>',
            'app' => '<path d="M16.4 12.8c0-2.3 1.9-3.4 2-3.5-1.1-1.6-2.8-1.8-3.4-1.8-1.4-.2-2.8.9-3.5.9s-1.8-.9-3-.9c-1.5 0-3 .9-3.8 2.3-1.6 2.8-.4 7 1.2 9.3.8 1.1 1.7 2.4 2.9 2.3 1.2 0 1.6-.7 3-.7s1.8.7 3 .7 2-1.1 2.8-2.2c.9-1.3 1.3-2.5 1.3-2.6 0 0-2.5-1-2.5-3.8zM14.2 5.9A4.4 4.4 0 0 0 15.2 3a4.5 4.5 0 0 0-2.9 1.5 4.2 4.2 0 0 0-1.1 2.8 3.7 3.7 0 0 0 3 1.6z"/>',
            'play' => '<path d="M3 2.4v19.2a1 1 0 0 0 1.5.9l16.2-9.6a1 1 0 0 0 0-1.8L4.5 1.5A1 1 0 0 0 3 2.4zm8.6 7.2l6.3 2.4-6.3 2.4V9.6z"/>',
            'check' => '<path d="M9 16.2L4.8 12l-1.4 1.4L9 19 21 7l-1.4-1.4L9 16.2z"/>',
            'spark' => '<path d="M12 2l1.8 6.2L20 10l-6.2 1.8L12 18l-1.8-6.2L4 10l6.2-1.8L12 2z"/>',
            'grid' => '<path d="M3 3h8v8H3V3zm10 0h8v8h-8V3zM3 13h8v8H3v-8zm10 0h8v8h-8v-8z"/>',
            'list' => '<path d="M3 5h2v2H3V5zm4 0h14v2H7V5zM3 11h2v2H3v-2zm4 0h14v2H7v-2zm-4 6h2v2H3v-2zm4 0h14v2H7v-2z"/>',
            'wrench' => '<path d="M20.7 5.6a5.5 5.5 0 0 1-7.1 6.9L6 20.1 3.9 18l7.6-7.6A5.5 5.5 0 0 1 18.4 3.3l-3 3 2.3 2.3 3-3a5.5 5.5 0 0 1 0 0z"/>',
            'chart' => '<path d="M4 20V10h4v10H4zm6 0V4h4v16h-4zm6 0v-6h4v6h-4z"/>',
            'calendar' => '<path d="M7 2v2H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2h-2V2h-2v2H9V2H7zM5 9h14v11H5V9zm2 2v2h3v-2H7zm5 0v2h3v-2h-3zm5 0v2h2v-2h-2z"/>',
            'upload' => '<path d="M12 3l5 5h-3v6h-4V8H7l5-5zM5 18h14v2H5v-2z"/>',
            'image' => '<path d="M3 5h18a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1H3a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1zm2 12h14l-4.5-6-3.5 4.5L9 13l-4 4zm3.5-9a1.5 1.5 0 1 0 0 3 1.5 1.5 0 0 0 0-3z"/>',
            'logout' => '<path d="M10 3v2H5v14h5v2H3V3h7zm5.6 4.4L14.2 8.8 16.4 11H9v2h7.4l-2.2 2.2 1.4 1.4 4.6-4.6-4.6-4.6z"/>',
            'menu' => '<path d="M3 6h18v2H3V6zm0 5h18v2H3v-2zm0 5h18v2H3v-2z"/>',
        ];

        $path = isset($paths[$name]) ? $paths[$name] : $paths['car'];

        return '<svg viewBox="0 0 24 24" class="' . htmlspecialchars($class, ENT_QUOTES, 'UTF-8') . '" aria-hidden="true">' . $path . '</svg>';
    }
}

if (!function_exists('carease_category')) {
    /**
     * Maps a car row onto one of the fleet categories used by the landing page.
     */
    function carease_category($car)
    {
        $text = strtolower(($car['brand'] ?? '') . ' ' . ($car['name'] ?? ''));

        $rules = [
            'Van' => ['van', 'hiace', 'grand', 'tourer', 'express', 'sprinter', 'commuter'],
            'Luxury' => ['bmw', 'mercedes', 'benz', 'audi', 'lexus', 'volvo', 'porsche', 'bmw', 'crown', 'camry', 'genesis'],
            'SUV' => ['innova', 'fortuner', 'pajero', 'montero', 'sportage', 'tucson', 'rav4', 'x5', 'x3', 'explorer', 'pinnacle', 'tiguan', 'mugen'],
            'Economy' => ['vios', 'mirage', 'hatch', 'alto', 'wagon', 'hhr', 'fit'],
        ];

        foreach ($rules as $category => $needles) {
            foreach ($needles as $needle) {
                if (strpos($text, $needle) !== false) {
                    return $category;
                }
            }
        }

        return 'Economy';
    }
}

if (!function_exists('carease_rating')) {
    /**
     * Stable pseudo rating derived from the car id so the markup stays deterministic.
     */
    function carease_rating($id)
    {
        $scale = [4.5, 4.7, 4.8, 4.9, 5.0, 4.6];
        return $scale[(int) $id % count($scale)];
    }
}

if (!function_exists('carease_transmission')) {
    function carease_transmission($id)
    {
        return ((int) $id % 3 === 0) ? 'Manual' : 'Automatic';
    }
}

if (!function_exists('carease_car_art')) {
    /**
     * Inline SVG illustration used as the car "photo" placeholder.
     */
    function carease_car_art($category = 'Economy')
    {
        $sedan = '<path class="car-body" d="M26 112V92c0-7 5-12 12-13l32-4 21-20c5-5 11-7 18-7h44c9 0 17 3 23 9l19 18 38 5c10 1 17 8 18 17l1 15H26z"/>'
            . '<path class="car-glass" d="M104 52h30v22H91l13-14zm37 0h16c6 0 11 2 15 6l12 16h-43z"/>'
            . '<circle class="car-wheel" cx="80" cy="112" r="18"/><circle class="car-hub" cx="80" cy="112" r="7"/>'
            . '<circle class="car-wheel" cx="228" cy="112" r="18"/><circle class="car-hub" cx="228" cy="112" r="7"/>';

        $suv = '<path class="car-body" d="M26 114V76c0-6 4-10 10-11l18-3 16-22c4-6 11-9 18-9h58c9 0 17 3 23 9l20 22 27 4c9 1 15 8 16 17l1 31H26z"/>'
            . '<path class="car-glass" d="M96 36h28v26H83l13-17zm35 0h22c6 0 11 2 15 6l13 20h-50z"/>'
            . '<circle class="car-wheel" cx="80" cy="114" r="20"/><circle class="car-hub" cx="80" cy="114" r="8"/>'
            . '<circle class="car-wheel" cx="232" cy="114" r="20"/><circle class="car-hub" cx="232" cy="114" r="8"/>';

        $luxury = '<path class="car-body" d="M18 112V94c0-7 6-12 13-13l34-4 25-19c5-4 11-6 18-6h40c10 0 19 4 25 11l17 20 34 4c10 1 17 8 17 17v8H18z"/>'
            . '<path class="car-glass" d="M104 56h30v21H90l14-14zm37 0h14c7 0 13 2 17 6l12 15h-43z"/>'
            . '<circle class="car-wheel" cx="76" cy="112" r="19"/><circle class="car-hub" cx="76" cy="112" r="7"/>'
            . '<circle class="car-wheel" cx="230" cy="112" r="19"/><circle class="car-hub" cx="230" cy="112" r="7"/>';

        $van = '<path class="car-body" d="M22 116V52c0-5 4-8 9-8h118c6 0 11 3 14 8l22 34 22 6c7 2 11 7 11 14v10H22z"/>'
            . '<path class="car-glass" d="M100 50h46v28h-46zM40 50h52v28H40z"/>'
            . '<circle class="car-wheel" cx="74" cy="116" r="19"/><circle class="car-hub" cx="74" cy="116" r="7"/>'
            . '<circle class="car-wheel" cx="216" cy="116" r="19"/><circle class="car-hub" cx="216" cy="116" r="7"/>';

        $shapes = [
            'SUV' => $suv,
            'Luxury' => $luxury,
            'Van' => $van,
            'Economy' => $sedan,
        ];

        $shape = isset($shapes[$category]) ? $shapes[$category] : $sedan;

        return '<svg class="car-art" viewBox="0 0 320 140" aria-hidden="true">' . $shape . '</svg>';
    }
}

if (!function_exists('carease_car_image')) {
    /**
     * Returns the public URL of an uploaded car photo, or null when the car
     * has no uploaded image yet.
     */
    function carease_car_image($car)
    {
        $path = trim((string) ($car['image_path'] ?? ''));
        if ($path === '')
            return null;

        if (preg_match('#^https?://#i', $path))
            return $path;

        $file = ROOT_DIR . 'public' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'carease'
            . DIRECTORY_SEPARATOR . ltrim($path, '/');
        if (!is_file($file))
            return null;

        return base_url('uploads/carease/' . ltrim($path, '/'));
    }
}

if (!function_exists('carease_car_media')) {
    /**
     * Media block for a car card (photo or illustration + badge + rating).
     */
    function carease_car_media($car, $category, $rating, $extraClass = '')
    {
        $dark = ($category === 'Luxury' || $category === 'Van') ? ' is-dark' : '';
        $image = carease_car_image($car);

        $art = $image
            ? '<img class="car-photo" src="' . htmlspecialchars($image, ENT_QUOTES, 'UTF-8') . '" alt="'
            . htmlspecialchars(($car['brand'] ?? '') . ' ' . ($car['name'] ?? ''), ENT_QUOTES, 'UTF-8') . '">'
            : carease_car_art($category);

        return '<div class="car-media' . $dark . ($image ? ' has-photo' : '') . ' '
            . htmlspecialchars($extraClass, ENT_QUOTES, 'UTF-8') . '">'
            . '<span class="car-badge">' . htmlspecialchars($category, ENT_QUOTES, 'UTF-8') . '</span>'
            . '<span class="car-rating">' . carease_icon('star') . number_format($rating, 1) . '</span>'
            . $art
            . '</div>';
    }
}

if (!function_exists('carease_theme_toggle')) {
    /**
     * Dark/light mode toggle button. footer.php keeps the icons in sync.
     */
    function carease_theme_toggle()
    {
        return '<button class="theme-toggle" type="button" data-theme-toggle'
            . ' aria-label="Switch to dark mode" title="Switch to dark mode" aria-pressed="false">'
            . '<svg data-icon-moon viewBox="0 0 24 24" aria-hidden="true">'
            . '<path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>'
            . '<svg data-icon-sun viewBox="0 0 24 24" aria-hidden="true" style="display:none">'
            . '<path d="M12 7a5 5 0 1 0 0 10 5 5 0 0 0 0-10zm0-5a1 1 0 0 1 1 1v2a1 1 0 0 1-2 0V3a1 1 0 0 1 1-1zm0 17a1 1 0 0 1 1 1v2a1 1 0 0 1-2 0v-2a1 1 0 0 1 1-1zM2 12a1 1 0 0 1 1-1h2a1 1 0 0 1 0 2H3a1 1 0 0 1-1-1zm17 0a1 1 0 0 1 1-1h2a1 1 0 0 1 0 2h-2a1 1 0 0 1-1-1zM4.93 4.93a1 1 0 0 1 1.41 0l1.42 1.42a1 1 0 0 1-1.42 1.41L4.93 6.34a1 1 0 0 1 0-1.41zm11.31 11.31a1 1 0 0 1 1.41 0l1.42 1.42a1 1 0 0 1-1.42 1.41l-1.41-1.41a1 1 0 0 1 0-1.42zM19.07 4.93a1 1 0 0 1 0 1.41l-1.42 1.42a1 1 0 0 1-1.41-1.42l1.41-1.41a1 1 0 0 1 1.42 0zM7.76 16.24a1 1 0 0 1 0 1.41l-1.42 1.42a1 1 0 0 1-1.41-1.42l1.41-1.41a1 1 0 0 1 1.42 0z"/></svg>'
            . '</button>';
    }
}
