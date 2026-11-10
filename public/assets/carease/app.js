(function () {
    'use strict';

    var shell = document.getElementById('appShell');
    if (!shell) {
        return;
    }

    var toggles = Array.prototype.slice.call(document.querySelectorAll('[data-sidebar-toggle]'));
    var KEY = 'carease.sidebar.collapsed';
    var MOBILE = 900;
    var backdrop = document.querySelector('.app-backdrop');

    function isMobile() {
        return window.innerWidth <= MOBILE;
    }

    function apply(collapsed) {
        shell.classList.toggle('is-collapsed', collapsed);
        toggles.forEach(function (btn) {
            btn.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
        });
        try {
            window.localStorage.setItem(KEY, collapsed ? '1' : '0');
        } catch (e) {
            /* storage unavailable - collapse state simply will not persist */
        }
    }

    function setDrawer(open) {
        shell.classList.toggle('is-open', open);
        if (backdrop) {
            backdrop.hidden = !open;
        }
    }

    var stored = null;
    try {
        stored = window.localStorage.getItem(KEY);
    } catch (e) {
        stored = null;
    }
    if (stored === '1') {
        apply(true);
    }

    toggles.forEach(function (btn) {
        btn.addEventListener('click', function () {
            if (isMobile()) {
                setDrawer(!shell.classList.contains('is-open'));
                return;
            }
            apply(!shell.classList.contains('is-collapsed'));
        });
    });

    // Mobile: tapping the backdrop or a nav link closes the drawer.
    if (backdrop) {
        backdrop.addEventListener('click', function () {
            setDrawer(false);
        });
    }

    Array.prototype.slice.call(document.querySelectorAll('.sidebar-link')).forEach(function (link) {
        link.addEventListener('click', function () {
            if (isMobile()) {
                setDrawer(false);
            }
        });
    });

    window.addEventListener('resize', function () {
        if (!isMobile()) {
            setDrawer(false);
        }
    });
})();
