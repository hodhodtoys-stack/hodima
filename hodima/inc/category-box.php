<?php
if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'admin_footer', 'hodima_wrap_category_form_in_box' );
function hodima_wrap_category_form_in_box() {
    $screen = get_current_screen();
    
    if ( ! $screen || empty( $screen->taxonomy ) || ! in_array( $screen->taxonomy, array( 'category', 'product_cat' ), true ) ) {
        return;
    }
    
    ?>
    <style>
        .hodima-postbox { background: #fff; border: 1px solid #c3c4c7; box-shadow: 0 1px 1px rgba(0,0,0,.04); margin-top: 20px; }
        .hodima-postbox-header { border-bottom: 1px solid #c3c4c7; padding: 15px; margin: 0; font-size: 14px; font-weight: 600; }
        .hodima-postbox-inside { padding: 20px; }
        .hodima-postbox-inside #addtag .submit { padding: 0; margin-bottom: 0; }
        .hodima-postbox-inside table.form-table { margin-top: 0; }
        
        div.term-thumbnail-wrap { border-bottom: 1px solid #c3c4c7; padding-bottom: 20px; margin-bottom: 20px !important; }
        tr.term-thumbnail-wrap th, tr.term-thumbnail-wrap td { border-bottom: 1px solid #c3c4c7; padding-bottom: 20px; }
    </style>

    <script>
        jQuery(function($) {
            // تابع کمکی برای جلوگیری از تکرار کد
            function wrapInBox($element, title) {
                if ( $element.length && ! $element.parent().hasClass('hodima-postbox-inside') ) {
                    $element.wrap('<div class="hodima-postbox"><div class="hodima-postbox-inside"></div></div>');
                    $element.closest('.hodima-postbox').prepend('<h2 class="hodima-postbox-header">' + title + '</h2>');
                }
            }

            // بررسی و اعمال روی صفحه افزودن
            var $addFormWrap = $('.form-wrap');
            if ( $addFormWrap.length && $('#addtag').length ) {
                wrapInBox($addFormWrap, 'افزودن دسته تازه');
                $addFormWrap.find('h2').first().hide(); 
            }

            // بررسی و اعمال روی صفحه ویرایش
            wrapInBox($('#edittag'), 'ویرایش دسته');
        });
    </script>
    <?php
}
