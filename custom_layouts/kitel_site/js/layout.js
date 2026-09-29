(function () {
    'use strict';
    var site = document.querySelector('.kitel-site');
    if (!site) return;

    var header = site.querySelector('.kitel-header');
    var nav = site.querySelector('.kitel-nav');
    var mega = site.querySelector('.kitel-mega');
    var toggle = site.querySelector('.kitel-mobile-toggle');
    var mobile = site.querySelector('.kitel-mobile');

    function closeMega() {
        if (mega) mega.hidden = true;
        if (nav) nav.querySelectorAll('[aria-expanded]').forEach(function (link) { link.setAttribute('aria-expanded', 'false'); });
    }
    function openMega() {
        if (!mega || window.matchMedia('(max-width: 1120px)').matches) return;
        mega.hidden = false;
        if (nav) nav.querySelectorAll('[aria-expanded]').forEach(function (link) { link.setAttribute('aria-expanded', 'true'); });
    }
    function closeMobile() {
        if (mobile) mobile.hidden = true;
        if (toggle) toggle.setAttribute('aria-expanded', 'false');
    }
    if (nav) {
        nav.addEventListener('pointerenter', openMega);
        nav.addEventListener('focusin', openMega);
    }
    if (header) {
        header.addEventListener('pointerleave', closeMega);
        header.addEventListener('focusout', function (event) {
            if (!header.contains(event.relatedTarget)) closeMega();
        });
    }
    if (toggle) {
        toggle.addEventListener('click', function () {
            var opening = mobile.hidden;
            closeMega();
            mobile.hidden = !opening;
            toggle.setAttribute('aria-expanded', opening ? 'true' : 'false');
        });
    }
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            closeMega();
            closeMobile();
            if (toggle && window.matchMedia('(max-width: 1120px)').matches) toggle.focus();
        }
    });
    document.addEventListener('pointerdown', function (event) {
        if (header && !header.contains(event.target)) {
            closeMega();
            closeMobile();
        }
    });
    window.addEventListener('resize', function () {
        if (window.matchMedia('(max-width: 1120px)').matches) closeMega();
        else closeMobile();
    });
})();
