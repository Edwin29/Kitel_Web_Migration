(function () {
    function initialize() {
        var form = document.querySelector('.kitel-gallery__visibility-form');
        if (!form || !window.Rhymix || typeof Rhymix.ajax !== 'function') return;
        var button = form.querySelector('[role="switch"]');
        var nextValue = form.querySelector('input[name="is_public"]');
        var error = form.querySelector('[role="alert"]');
        form.addEventListener('submit', function (event) {
            event.preventDefault();
            if (button.disabled) return;
            button.disabled = true;
            button.setAttribute('aria-busy', 'true');
            error.textContent = '';
            Rhymix.ajax(null, new FormData(form), function () {
                var isPublic = nextValue.value === 'Y';
                button.classList.toggle('is-on', isPublic);
                button.classList.toggle('is-off', !isPublic);
                button.setAttribute('aria-checked', isPublic ? 'true' : 'false');
                nextValue.value = isPublic ? 'N' : 'Y';
                button.disabled = false;
                button.removeAttribute('aria-busy');
            }, function (response) {
                error.textContent = response && response.message && response.message !== 'error' ? response.message : '공개 상태를 변경하지 못했습니다.';
                button.disabled = false;
                button.removeAttribute('aria-busy');
            });
        });
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initialize);
    else initialize();
}());
