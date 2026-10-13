(function () {
    'use strict';

    var shell = document.getElementById('admin-shell');
    var menuToggle = document.getElementById('admin-menu-toggle');
    var scrim = document.getElementById('admin-sidebar-scrim');
    var sidebar = document.getElementById('admin-sidebar');

    if (!shell || !menuToggle || !scrim || !sidebar) {
        return;
    }

    function setNavigationOpen(isOpen) {
        var isMobile = window.innerWidth <= 760;
        shell.classList.toggle('is-nav-open', isOpen);
        menuToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        menuToggle.setAttribute('aria-label', isOpen ? 'Close admin navigation' : 'Open admin navigation');
        sidebar.setAttribute('aria-hidden', isMobile && !isOpen ? 'true' : 'false');
        sidebar.inert = isMobile && !isOpen;
        menuToggle.innerHTML = isOpen
            ? '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.65" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m18 6-12 12M6 6l12 12"/></svg>'
            : '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.65" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h16"/></svg>';
    }

    menuToggle.addEventListener('click', function () {
        setNavigationOpen(menuToggle.getAttribute('aria-expanded') !== 'true');
    });

    scrim.addEventListener('click', function () {
        setNavigationOpen(false);
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && shell.classList.contains('is-nav-open')) {
            setNavigationOpen(false);
            menuToggle.focus();
        }
    });

    window.addEventListener('resize', function () {
        if (window.innerWidth > 760 && shell.classList.contains('is-nav-open')) {
            setNavigationOpen(false);
        } else {
            sidebar.setAttribute('aria-hidden', window.innerWidth <= 760 ? 'true' : 'false');
            sidebar.inert = window.innerWidth <= 760;
        }
    });

    setNavigationOpen(false);
}());
