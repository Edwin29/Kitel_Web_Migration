(function () {
    'use strict';
    function init() {
        var form = document.getElementById('kitel-task-form');
        if (!form) return;
        var list = document.getElementById('hw-field-list');
        var template = document.getElementById('hw-field-template');
        var counter = 0;
        var imageTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
        var maxImageBytes = 3 * 1024 * 1024;

        function renumber() {
            Array.prototype.forEach.call(list.children, function (field, index) {
                field.querySelector('h3').textContent = '문항 ' + (index + 1);
                field.querySelector('.js-prompt-editor').setAttribute('aria-label', '문항 ' + (index + 1) + ' 질문 내용');
            });
        }
        function insertImage(editor, file, range) {
            if (imageTypes.indexOf(file.type) < 0 || file.size > maxImageBytes) {
                window.alert('JPG, PNG, WebP, GIF 이미지만 3MB 이하로 넣을 수 있습니다.');
                return;
            }
            var reader = new FileReader();
            reader.onload = function () {
                editor.focus();
                var selection = window.getSelection();
                if (range && editor.contains(range.commonAncestorContainer)) {
                    selection.removeAllRanges();
                    selection.addRange(range);
                }
                var current = selection.rangeCount ? selection.getRangeAt(0) : null;
                if (!current || !editor.contains(current.commonAncestorContainer)) {
                    current = document.createRange();
                    current.selectNodeContents(editor);
                    current.collapse(false);
                }
                current.deleteContents();
                var image = document.createElement('img');
                image.src = reader.result;
                image.alt = '';
                current.insertNode(image);
                current.setStartAfter(image);
                current.collapse(true);
                selection.removeAllRanges();
                selection.addRange(current);
            };
            reader.readAsDataURL(file);
        }
        function imageFrom(items) {
            for (var i = 0; i < items.length; i++) {
                var item = items[i];
                var file = item.kind === 'file' ? item.getAsFile() : item;
                if (file && imageTypes.indexOf(file.type) >= 0) return file;
            }
            return null;
        }
        document.getElementById('hw-add-field').addEventListener('click', function () {
            if (list.children.length >= 21) return;
            var field = template.content.firstElementChild.cloneNode(true);
            field.querySelector('[name="field_id[]"]').value = 'answer_' + Date.now().toString(36) + '_' + (++counter).toString(36);
            list.appendChild(field);
            renumber();
            field.querySelector('.js-prompt-editor').focus();
        });
        list.addEventListener('click', function (event) {
            var field = event.target.closest('.kitel-hw-admin__field');
            if (!field) return;
            if (event.target.closest('.js-remove-field')) { field.remove(); renumber(); }
            if (event.target.closest('.js-insert-image')) field.querySelector('.js-image-picker').click();
        });
        list.addEventListener('mousedown', function (event) {
            if (!event.target.closest('.js-insert-image')) return;
            var field = event.target.closest('.kitel-hw-admin__field');
            var editor = field.querySelector('.js-prompt-editor');
            var selection = window.getSelection();
            field._imageRange = selection.rangeCount && editor.contains(selection.getRangeAt(0).commonAncestorContainer) ? selection.getRangeAt(0).cloneRange() : null;
        });
        list.addEventListener('change', function (event) {
            if (!event.target.matches('.js-image-picker')) return;
            var field = event.target.closest('.kitel-hw-admin__field');
            var editor = field.querySelector('.js-prompt-editor');
            var file = event.target.files[0];
            if (file) insertImage(editor, file, field._imageRange);
            field._imageRange = null;
            event.target.value = '';
        });
        list.addEventListener('paste', function (event) {
            var editor = event.target.closest('.js-prompt-editor');
            if (!editor || !event.clipboardData) return;
            var file = imageFrom(event.clipboardData.items);
            if (file) {
                event.preventDefault();
                insertImage(editor, file, window.getSelection().rangeCount ? window.getSelection().getRangeAt(0).cloneRange() : null);
            } else {
                event.preventDefault();
                document.execCommand('insertText', false, event.clipboardData.getData('text/plain'));
            }
        });
        list.addEventListener('dragover', function (event) {
            var editor = event.target.closest('.js-prompt-editor');
            if (!editor || !event.dataTransfer || !Array.prototype.some.call(event.dataTransfer.items, function (item) { return imageTypes.indexOf(item.type) >= 0; })) return;
            event.preventDefault();
            editor.classList.add('is-dragover');
        });
        list.addEventListener('dragleave', function (event) {
            var editor = event.target.closest('.js-prompt-editor');
            if (editor) editor.classList.remove('is-dragover');
        });
        list.addEventListener('drop', function (event) {
            var editor = event.target.closest('.js-prompt-editor');
            if (!editor || !event.dataTransfer) return;
            var file = imageFrom(event.dataTransfer.items) || imageFrom(event.dataTransfer.files);
            if (!file) return;
            event.preventDefault();
            editor.classList.remove('is-dragover');
            var caret = document.caretRangeFromPoint ? document.caretRangeFromPoint(event.clientX, event.clientY) : null;
            insertImage(editor, file, caret);
        });
        form.addEventListener('submit', function (event) {
            Array.prototype.forEach.call(list.children, function (field) {
                var editor = field.querySelector('.js-prompt-editor');
                field.querySelector('.js-prompt-value').value = editor.innerHTML;
                var height = field.querySelector('.js-field-preview').getBoundingClientRect().height;
                field.querySelector('.js-field-height').value = Math.max(120, Math.min(900, Math.round(height)));
            });
        });
        renumber();
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
    else init();
})();
