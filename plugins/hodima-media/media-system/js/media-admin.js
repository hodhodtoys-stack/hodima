/**
 * Phase 2.4: Media System Admin UI - Scripts
 * File: wp-content/themes/hodima/media-system/js/media-admin.js
 * Version: 1.2.0
 */
jQuery(document).ready(function($) {

    function reindexFields(container, itemSelector) {
        container.find(itemSelector).each(function(index) {
            $(this).find('input, select, textarea').each(function() {
                var name = $(this).attr('name');
                if (name) {
                    var newName = name.replace(/\[([^\]]+)\]/, '[' + index + ']');
                    $(this).attr('name', newName);
                }
            });
        });
    }

    $(document).on('click', '.hook-add-faq', function(e) {
        e.preventDefault();
        var $container = $(this).closest('.hook-admin-box').find('.hook-faq-container');
        var index = $container.find('.hook-faq-item').length; 

        // مارک‌آپ باید دقیقا با چیزی که PHP رندر می‌کند یکی باشد،
        // وگرنه ردیف‌های تازه‌اضافه‌شده استایل متفاوتی می‌گیرند.
        var newFaqItem = `
            <div class="hook-faq-item">
                <input type="text" name="hook_faq[${index}][q]" placeholder="سؤال (پرسش)..." />
                <textarea name="hook_faq[${index}][a]" placeholder="پاسخ..."></textarea>
                <button type="button" class="button-link button-link-delete hook-remove-faq">حذف این سؤال</button>
            </div>`;
            
        $container.append(newFaqItem);
        reindexFields($container, '.hook-faq-item');
    });

    $(document).on('click', '.hook-remove-faq', function(e) {
        e.preventDefault();
        if (confirm('آیا از حذف این سؤال مطمئن هستید؟')) {
            var $container = $(this).closest('.hook-faq-container');
            $(this).closest('.hook-faq-item').remove();
            reindexFields($container, '.hook-faq-item');
        }
    });

    $(document).on('click', '.hook-upload-video-cover', function(e) {
        e.preventDefault();
        var $button = $(this);
        var $inputField = $button.closest('.hook-video-cover-wrap').find('.hook-video-cover-input');
        
        var customUploader = wp.media({
            title: 'انتخاب کاور ویدیو',
            button: { text: 'انتخاب این تصویر' },
            multiple: false,
            library: { type: 'image' } 
        });

        customUploader.on('select', function() {
            var attachment = customUploader.state().get('selection').first().toJSON();
            $inputField.val(attachment.url).trigger('input');
        });

        customUploader.open();
    });

    // پیش‌نمایش کاور — هم با انتخاب از گالری، هم با تایپ یا چسباندن آدرس
    $(document).on('input change', '.hook-video-cover-input', function() {
        var url = $.trim($(this).val());
        var $preview = $(this).closest('.hook-admin-box').find('.hook-video-cover-preview');
        if (/^https?:\/\//i.test(url)) {
            $preview.attr('src', url).prop('hidden', false);
        } else {
            $preview.removeAttr('src').prop('hidden', true);
        }
    });

});
