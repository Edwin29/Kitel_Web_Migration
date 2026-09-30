(function () {
    'use strict';
    var hero = document.querySelector('.kitel-hero');
    if (!hero) return;
    function fitFirstScreen() {
        var top = Math.max(0, Math.round(hero.getBoundingClientRect().top + window.scrollY));
        hero.style.setProperty('--kitel-hero-top', top + 'px');
    }
    fitFirstScreen();
    window.addEventListener('resize', fitFirstScreen);
})();
