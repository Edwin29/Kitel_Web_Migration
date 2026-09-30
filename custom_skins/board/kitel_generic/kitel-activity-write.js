(function () {
    function init() {
        var form = document.querySelector('.kitel-board__write-form');
        var extras = form && form.querySelector('[data-kitel-activity-category]');
        var category = form && form.querySelector('select[name="category_srl"]');
        if (!extras || !category) return;
        var image = extras.querySelector('input[type="file"]');
        if (image) image.accept = 'image/jpeg,image/png,image/gif,image/webp';
        function update() {
            var visible = category.value === extras.dataset.kitelActivityCategory;
            extras.hidden = !visible;
            extras.querySelectorAll('input, select, textarea').forEach(function (field) { field.disabled = !visible; });
        }
        category.addEventListener('change', update);
        update();
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
    else init();
}());
