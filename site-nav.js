(function () {
    'use strict';
    const nav = document.querySelector('.site-nav');
    if (!nav) return;
    const toggle = nav.querySelector('.site-nav-toggle');
    const menu = nav.querySelector('#site-nav-links');
    if (!toggle || !menu) return;

    const mobileMenu = document.querySelector('.mobile-menu');
    function setOpen(open) {
        nav.classList.toggle('nav-open', open);
        toggle.setAttribute('aria-expanded', String(open));
        if (mobileMenu) mobileMenu.setAttribute('aria-expanded', String(open));
        toggle.setAttribute('aria-label', open ? 'Close menu' : 'Open menu');
    }
    toggle.addEventListener('click', function () {
        setOpen(!nav.classList.contains('nav-open'));
    });
    if (mobileMenu) mobileMenu.addEventListener('click', function () {
        setOpen(!nav.classList.contains('nav-open'));
        if (nav.classList.contains('nav-open')) nav.scrollIntoView({block: 'start'});
    });
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && nav.classList.contains('nav-open')) {
            setOpen(false);
            toggle.focus();
        }
    });
    document.addEventListener('click', function (event) {
        if (!nav.contains(event.target) && !(mobileMenu && mobileMenu.contains(event.target)) && nav.classList.contains('nav-open')) setOpen(false);
    });
    window.addEventListener('resize', function () {
        if (window.innerWidth > 1024) setOpen(false);
    });
    nav.classList.add('nav-ready');
}());
