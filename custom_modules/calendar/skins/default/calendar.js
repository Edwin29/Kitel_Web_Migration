document.addEventListener('DOMContentLoaded', function () {
    var calendar = document.querySelector('.kitel-cal');
    if (!calendar) return;

    function revealEvent() {
        var id = window.location.hash.slice(1);
        if (!/^kitel-cal-event-\d+$/.test(id)) return;
        var detail = document.getElementById(id);
        if (detail && calendar.contains(detail)) detail.open = true;
    }

    window.addEventListener('hashchange', revealEvent);
    revealEvent();
});
