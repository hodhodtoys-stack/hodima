/**
 * Video Watch — admin meta box
 * (قبلا به صورت <script> درون‌خطی داخل متاباکس)
 */
(function () {
    'use strict';

    function rowHTML(i) {
        return '' +
            '<div class="hvw-repeater-row">' +
                '<div class="hvw-field hvw-flex-1"><label class="hvw-label">عنوان لینک</label>' +
                    '<input type="text" name="hod_related_links[' + i + '][title]" class="hvw-input"></div>' +
                '<div class="hvw-field hvw-flex-1"><label class="hvw-label">آدرس لینک</label>' +
                    '<input type="url" name="hod_related_links[' + i + '][url]" class="hvw-input" dir="ltr"></div>' +
                '<div class="hvw-field hvw-flex-15"><label class="hvw-label">آدرس عکس کاور</label>' +
                    '<div class="hvw-inline"><input type="url" name="hod_related_links[' + i + '][cover]" class="hvw-input" dir="ltr">' +
                    '<button type="button" class="button hvw-btn-action hvw-media-upload-btn">انتخاب</button></div></div>' +
                '<button type="button" class="hvw-remove-row" aria-label="حذف">✕</button>' +
            '</div>';
    }

    function init() {

        var container = document.querySelector('.hvw-repeater-rows');
        var addBtn    = document.getElementById('hvw-add-link-btn');

        function reindex() {
            if (!container) return;
            container.querySelectorAll('.hvw-repeater-row').forEach(function (row, i) {
                row.querySelectorAll('input[name^="hod_related_links"]').forEach(function (input) {
                    input.name = input.name.replace(/hod_related_links\[\d+\]/, 'hod_related_links[' + i + ']');
                });
            });
        }

        if (container && addBtn) {
            addBtn.addEventListener('click', function (e) {
                e.preventDefault();
                var count = container.querySelectorAll('.hvw-repeater-row').length;
                if (count >= 99) {
                    alert('حداکثر ۹۹ لینک مجاز است.');
                    return;
                }
                container.insertAdjacentHTML('beforeend', rowHTML(count));
            });

            container.addEventListener('click', function (e) {
                var btn = e.target.closest('.hvw-remove-row');
                if (!btn) return;
                e.preventDefault();
                btn.closest('.hvw-repeater-row').remove();
                reindex();
            });
        }

        document.addEventListener('click', function (e) {
            var button = e.target.closest('.hvw-media-upload-btn');
            if (!button) return;
            e.preventDefault();

            var input = button.parentNode.querySelector('input[type="url"]');
            if (!input || typeof wp === 'undefined' || !wp.media) return;

            var frame = wp.media({
                title: 'انتخاب تصویر',
                button: { text: 'استفاده از این تصویر' },
                library: { type: 'image' },
                multiple: false
            });
            frame.on('select', function () {
                input.value = frame.state().get('selection').first().toJSON().url;
                input.dispatchEvent(new Event('change', { bubbles: true }));
            });
            frame.open();
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
