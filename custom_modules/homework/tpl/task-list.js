(function () {
    function initVisibilitySwitches() {
        if (!window.Rhymix || typeof Rhymix.ajax !== 'function') return;

        document.querySelectorAll('.kitel-hw-admin__visibility-form').forEach(function (form) {
            var button = form.querySelector('.kitel-hw-admin__visibility');
            var nextValue = form.querySelector('input[name="is_visible"]');
            var error = form.parentElement.querySelector('.kitel-hw-admin__visibility-error');

            form.addEventListener('submit', function (event) {
                event.preventDefault();
                if (button.disabled) return;

                button.disabled = true;
                button.setAttribute('aria-busy', 'true');
                error.textContent = '';

                Rhymix.ajax(null, new FormData(form), function () {
                    var nowVisible = nextValue.value === 'Y';
                    button.classList.toggle('is-on', nowVisible);
                    button.classList.toggle('is-off', !nowVisible);
                    button.setAttribute('aria-checked', nowVisible ? 'true' : 'false');
                    nextValue.value = nowVisible ? 'N' : 'Y';
                    button.disabled = false;
                    button.removeAttribute('aria-busy');
                }, function (response) {
                    error.textContent = response && response.message && response.message !== 'error' ? response.message : '표시 상태를 변경하지 못했습니다.';
                    button.disabled = false;
                    button.removeAttribute('aria-busy');
                });
            });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initVisibilitySwitches);
    } else {
        initVisibilitySwitches();
    }
}());
