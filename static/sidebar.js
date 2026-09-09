/**
 * sidebar.js
 * Shared sidebar controller for all SCNHS admin and student dashboard pages.
 * Handles: smooth open/close, overlay dim, close on outside click, keyboard ESC, 
 * active link highlighting, and touch/swipe support.
 */
(function () {
    'use strict';

    const SIDEBAR_ID   = 'sidebar';
    const HAMBURGER_ID = 'hamburgerBtn';
    const OVERLAY_ID   = 'sidebarOverlay';
    const ACTIVE_CLASS = 'active';

    // ─── Inject overlay element if not already in DOM ─────────
    function ensureOverlay() {
        if (document.getElementById(OVERLAY_ID)) return document.getElementById(OVERLAY_ID);
        const overlay = document.createElement('div');
        overlay.id = OVERLAY_ID;
        overlay.className = 'sidebar-overlay';
        overlay.setAttribute('aria-hidden', 'true');
        document.body.insertBefore(overlay, document.body.firstChild);
        return overlay;
    }

    // ─── Main Init ────────────────────────────────────────────
    function init() {
        const sidebar    = document.getElementById(SIDEBAR_ID);
        const hamburger  = document.getElementById(HAMBURGER_ID);
        const overlay    = ensureOverlay();

        if (!sidebar || !hamburger) return;

        // ── Open / Close Toggle ────────────────────────────
        function openSidebar() {
            sidebar.classList.add(ACTIVE_CLASS);
            overlay.classList.add(ACTIVE_CLASS);
            hamburger.setAttribute('aria-expanded', 'true');
            hamburger.innerHTML = '<i class="fas fa-times"></i>';
            document.body.style.overflow = 'hidden'; // prevent background scroll
        }

        function closeSidebar() {
            sidebar.classList.remove(ACTIVE_CLASS);
            overlay.classList.remove(ACTIVE_CLASS);
            hamburger.setAttribute('aria-expanded', 'false');
            hamburger.innerHTML = '<i class="fas fa-bars"></i>';
            document.body.style.overflow = '';
        }

        function isOpen() {
            return sidebar.classList.contains(ACTIVE_CLASS);
        }

        // ── Hamburger button ────────────────────────────────
        hamburger.addEventListener('click', function (e) {
            e.stopPropagation();
            isOpen() ? closeSidebar() : openSidebar();
        });

        // ── Overlay click ───────────────────────────────────
        overlay.addEventListener('click', closeSidebar);

        // ── ESC key ─────────────────────────────────────────
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && isOpen()) closeSidebar();
        });

        // ── Close sidebar when nav item is clicked (mobile UX) ─
        sidebar.querySelectorAll('.nav-item').forEach(function (link) {
            link.addEventListener('click', function () {
                if (window.innerWidth <= 1024) closeSidebar();
            });
        });

        // ── Auto-close on window resize (desktop) ──────────
        window.addEventListener('resize', function () {
            if (window.innerWidth > 1024) {
                closeSidebar();
            }
        });

        // ── Touch Swipe Left to Close ───────────────────────
        let touchStartX = null;
        sidebar.addEventListener('touchstart', function (e) {
            touchStartX = e.changedTouches[0].screenX;
        }, { passive: true });

        sidebar.addEventListener('touchend', function (e) {
            if (touchStartX === null) return;
            const dx = touchStartX - e.changedTouches[0].screenX;
            if (dx > 60) closeSidebar(); // swipe left 60px = close
            touchStartX = null;
        }, { passive: true });
    }

    // ─── Auto-mark active nav link ────────────────────────────
    function markActive() {
        const currentPage = window.location.pathname.split('/').pop() || 'index';
        document.querySelectorAll('.nav-item').forEach(function (link) {
            link.classList.remove('active');
            const href = (link.getAttribute('href') || '').split('/').pop();
            if (href && href === currentPage) {
                link.classList.add('active');
            }
        });
    }

    // ─── Run on DOM ready ─────────────────────────────────────
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () { init(); markActive(); });
    } else {
        init();
        markActive();
    }
})();
