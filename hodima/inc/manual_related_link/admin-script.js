/* Path: /wp-content/themes/hodima/inc/manual_related_link/admin-script.js */

(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {

        // wp.media از وابستگی media-editor می‌آید. اگر به هر دلیلی لود
        // نشده باشد، به جای خطای رانتایم که بقیه اسکریپت را می‌شکند،
        // دکمه‌ها غیرفعال می‌شوند.
        var mediaReady = (typeof wp !== 'undefined' && typeof wp.media === 'function');

        var strings = (typeof hodima_mrl_vars !== 'undefined') ? hodima_mrl_vars : {
            i18n_title: 'انتخاب تصویر',
            i18n_button: 'استفاده از این تصویر'
        };

        function previewMarkup(url) {
            var img = document.createElement('img');
            img.src = url;
            img.alt = '';
            return img;
        }

        document.querySelectorAll('.hodima_upload_image').forEach(function (button) {

            if (!mediaReady) {
                button.disabled = true;
                button.title = 'کتابخانه رسانه وردپرس در دسترس نیست.';
                return;
            }

            var frame = null;

            button.addEventListener('click', function (e) {
                e.preventDefault();

                if (frame) {
                    frame.open();
                    return;
                }

                var target = button.getAttribute('data-target');

                frame = wp.media({
                    title: strings.i18n_title,
                    button: { text: strings.i18n_button },
                    library: { type: 'image' },
                    multiple: false
                });

                frame.on('select', function () {

                    var selection = frame.state().get('selection').first();
                    if (!selection) return;

                    var attachment = selection.toJSON();

                    var idField = document.getElementById(target + '_id');
                    var preview = document.getElementById('preview_' + target);
                    if (!idField || !preview) return;

                    idField.value = attachment.id;

                    var imgUrl = (attachment.sizes && attachment.sizes.thumbnail)
                        ? attachment.sizes.thumbnail.url
                        : attachment.url;

                    // به جای innerHTML با رشته، عنصر ساخته می‌شود تا آدرس
                    // فایل هیچ‌وقت به عنوان HTML تفسیر نشود.
                    preview.textContent = '';
                    preview.appendChild(previewMarkup(imgUrl));

                    var removeBtn = button.parentElement
                        ? button.parentElement.querySelector('.hodima_remove_image')
                        : null;

                    if (removeBtn) removeBtn.hidden = false;
                });

                frame.open();
            });
        });

        document.querySelectorAll('.hodima_remove_image').forEach(function (button) {

            button.addEventListener('click', function (e) {
                e.preventDefault();

                var target  = button.getAttribute('data-target');
                var idField = document.getElementById(target + '_id');
                var preview = document.getElementById('preview_' + target);

                if (idField) idField.value = '';
                if (preview) preview.textContent = '';

                button.hidden = true;
            });
        });
    });
})();
