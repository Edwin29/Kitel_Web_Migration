(function () {
    'use strict';
    var site = document.querySelector('.kitel-site');
    if (!site) return;

    var header = site.querySelector('.kitel-header');
    var nav = site.querySelector('.kitel-nav');
    var mega = site.querySelector('.kitel-mega');
    var toggle = site.querySelector('.kitel-mobile-toggle');
    var mobile = site.querySelector('.kitel-mobile');

    var megaTrigger = null;
    var returningFocus = false;

    function closeMega() {
        if (mega) mega.hidden = true;
        if (nav) nav.querySelectorAll('[aria-expanded]').forEach(function (link) { link.setAttribute('aria-expanded', 'false'); });
    }
    function openMega(link) {
        if (!mega || window.matchMedia('(max-width: 1120px)').matches) return;
        if (returningFocus) return;
        megaTrigger = link;
        mega.hidden = false;
        if (nav) nav.querySelectorAll('[aria-expanded]').forEach(function (item) { item.setAttribute('aria-expanded', item === link ? 'true' : 'false'); });
    }
    function closeMobile() {
        if (mobile) mobile.hidden = true;
        if (toggle) {
            toggle.setAttribute('aria-expanded', 'false');
            toggle.setAttribute('aria-label', '메뉴 열기');
        }
    }
    if (nav) {
        nav.addEventListener('pointerover', function (event) {
            var link = event.target.closest('.kitel-nav__link');
            if (link && nav.contains(link)) openMega(link);
        });
        nav.addEventListener('focusin', function (event) {
            var link = event.target.closest('.kitel-nav__link');
            if (link && nav.contains(link)) openMega(link);
        });
        nav.addEventListener('click', function (event) {
            var link = event.target.closest('.kitel-nav__link');
            if (link && nav.contains(link)) openMega(link);
        });
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
            toggle.setAttribute('aria-label', opening ? '메뉴 닫기' : '메뉴 열기');
        });
    }
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            var hadMega = mega && !mega.hidden;
            var hadMobile = mobile && !mobile.hidden;
            closeMega();
            closeMobile();
            if (hadMega && megaTrigger) {
                returningFocus = true;
                megaTrigger.focus();
                returningFocus = false;
            } else if (hadMobile && toggle) toggle.focus();
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
