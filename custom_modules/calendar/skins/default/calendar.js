document.addEventListener('DOMContentLoaded', function () {
    var calendar = document.querySelector('.kitel-cal');
    if (!calendar) return;

    var trigger = calendar.querySelector('#kitel-cal-period-trigger');
    var popover = calendar.querySelector('#kitel-cal-period-popover');
    var toolbar = calendar.querySelector('.kitel-cal__toolbar');
    var jump = calendar.querySelector('.kitel-cal__jump');
    var yearInput = jump.querySelector('input[name="cal_year"]');
    var yearLabel = calendar.querySelector('.kitel-cal__popover-year');
    var monthButtons = Array.from(jump.querySelectorAll('button[name="cal_month"]'));
    var currentYear = Number(jump.dataset.currentYear);
    var currentMonth = Number(jump.dataset.currentMonth);
    var todayYear = Number(jump.dataset.todayYear);
    var todayMonth = Number(jump.dataset.todayMonth);
    var pickerYear = currentYear;

    function setPickerYear(year) {
        pickerYear = Math.max(1970, Math.min(9999, year));
        yearInput.value = String(pickerYear);
        yearLabel.textContent = pickerYear + '년';
        calendar.querySelector('.kitel-cal__year-prev').disabled = pickerYear === 1970;
        calendar.querySelector('.kitel-cal__year-next').disabled = pickerYear === 9999;
        monthButtons.forEach(function (button) {
            var month = Number(button.value);
            var selected = pickerYear === currentYear && month === currentMonth;
            button.classList.toggle('is-selected', selected);
            button.classList.toggle('is-today', pickerYear === todayYear && month === todayMonth);
            button.setAttribute('aria-current', selected ? 'date' : 'false');
        });
    }

    function closePicker(restoreFocus) {
        if (popover.hidden) return;
        popover.hidden = true;
        trigger.setAttribute('aria-expanded', 'false');
        toolbar.classList.remove('is-picker-open');
        if (restoreFocus) trigger.focus();
    }

    function openPicker() {
        setPickerYear(currentYear);
        popover.hidden = false;
        trigger.setAttribute('aria-expanded', 'true');
        toolbar.style.setProperty('--kitel-calendar-popover-height', popover.offsetHeight + 'px');
        toolbar.classList.add('is-picker-open');
        (monthButtons.find(function (button) { return Number(button.value) === currentMonth; }) || monthButtons[0]).focus();
    }

    trigger.addEventListener('click', function () {
        if (popover.hidden) openPicker();
        else closePicker(true);
    });
    calendar.querySelector('.kitel-cal__year-prev').addEventListener('click', function () { setPickerYear(pickerYear - 1); });
    calendar.querySelector('.kitel-cal__year-next').addEventListener('click', function () { setPickerYear(pickerYear + 1); });
    jump.addEventListener('submit', function () { closePicker(false); });
    document.addEventListener('click', function (event) {
        if (!popover.hidden && !calendar.querySelector('.kitel-cal__period-picker').contains(event.target)) closePicker(true);
    });
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && !popover.hidden) {
            event.preventDefault();
            closePicker(true);
        }
    });

    function revealEvent() {
        var id = window.location.hash.slice(1);
        if (!/^kitel-cal-event-\d+$/.test(id)) return;
        var detail = document.getElementById(id);
        if (detail && calendar.contains(detail)) detail.open = true;
    }

    window.addEventListener('hashchange', revealEvent);
    revealEvent();
});
