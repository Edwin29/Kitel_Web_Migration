(function () {
    'use strict';
    function init() {
    var form = document.getElementById('kitel-task-form');
    if (!form) return;
    var list = document.getElementById('hw-field-list');
    var template = document.getElementById('hw-field-template');
    var counter = 0;
    document.getElementById('hw-add-field').addEventListener('click', function () {
        if (list.children.length >= 20) return;
        var field = template.content.firstElementChild.cloneNode(true);
        var id = 'answer_' + Date.now().toString(36) + '_' + (++counter).toString(36);
        field.querySelector('[name="field_id[]"]').value = id;
        list.appendChild(field);
        field.querySelector('[name="field_title[]"]').focus();
    });
    list.addEventListener('click', function (event) {
        if (event.target.classList.contains('js-remove-field')) event.target.closest('.kitel-hw-admin__field').remove();
    });
    form.addEventListener('submit', function () {
        list.querySelectorAll('.kitel-hw-admin__field').forEach(function (field) {
            var height = field.querySelector('.js-field-preview').getBoundingClientRect().height;
            field.querySelector('.js-field-height').value = Math.max(120, Math.min(900, Math.round(height)));
        });
    });
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
    else init();
})();
