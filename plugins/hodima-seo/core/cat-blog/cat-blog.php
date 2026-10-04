<?php
/**
 * مسیر: wp-content/plugins/hodima-seo/core/cat-blog/cat-blog.php
 * پشتیبانی اختصاصی برای دسته‌بندی مقالات (وبلاگ) + رابط کاربری بهینه‌شده
 *
 * ویرایشگر پیشرفته توضیحات برای دسته محصول هم هست (قبلا در inc/enqueue.php
 * قالب — بازسازی قالب، مرحله ۲). قالب پیش‌فرض وردپرس را پنهان و نام فیلدش را
 * حذف می‌کند تا دو فیلد «description» در یک فرم نباشند (نسخه قالب فقط پنهان
 * می‌کرد). تصویر دسته فقط برای دسته نوشته‌ها؛ دسته محصول تصویر ووکامرس را دارد.
 */
declare(strict_types=1);

namespace Hodima\Core\CatBlog;

if ( ! defined( 'ABSPATH' ) ) exit;

class Cat_Blog {

    /** طبقه‌بندی‌هایی که ویرایشگر پیشرفته توضیحات دارند. */
    private array $editor_taxonomies;

    public function __construct() {
        $taxonomies = (array) apply_filters( 'hodima_rich_term_description_taxonomies', [ 'category', 'product_cat' ] );

        // قالب هدیما قبل از 2.3.0 ویرایشگر دسته محصول را خودش (با همین شناسه‌ها) اضافه می‌کند
        if ( function_exists( 'hodima_theme_has_legacy_logic' ) && hodima_theme_has_legacy_logic() ) {
            $taxonomies = array_diff( $taxonomies, [ 'product_cat' ] );
        }

        $this->editor_taxonomies = array_values( $taxonomies );

        // ۱. فیلترهای امنیتی برای پذیرش HTML در توضیحات دسته
        remove_filter('pre_term_description', 'wp_filter_kses');
        add_filter('pre_term_description', 'wp_filter_post_kses');
        remove_filter('term_description', 'wp_kses_data');

        // ۲. هوک‌های بارگذاری فایل‌ها
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('admin_head', [$this, 'inject_css']);
        add_action('admin_footer', [$this, 'inject_js']);

        // ۳. هوک‌های ویرایشگر متنی
        foreach ( $this->editor_taxonomies as $taxonomy ) {
            add_action( "{$taxonomy}_add_form_fields", [ $this, 'add_editor_to_create_form' ], 10 );
            add_action( "{$taxonomy}_edit_form_fields", [ $this, 'add_editor_to_edit_form' ], 10, 1 );
        }

        // ۴. هوک‌های آپلود تصویر
        add_action('category_add_form_fields', [$this, 'add_image_to_create_form'], 20);
        add_action('category_edit_form_fields', [$this, 'add_image_to_edit_form'], 20, 1);
        add_action('created_category', [$this, 'save_category_image_meta'], 10, 1);
        add_action('edited_category', [$this, 'save_category_image_meta'], 10, 1);

        // ۵. ستون تصویر در لیست دسته‌بندی‌ها
        add_filter('manage_edit-category_columns', [$this, 'add_image_column_to_list']);
        add_action('manage_category_custom_column', [$this, 'render_image_column_content'], 10, 3);
    }

    public function enqueue_assets(string $hook): void {
        if (in_array($hook, ['edit-tags.php', 'term.php'])) {
            wp_enqueue_media();
            wp_enqueue_editor(); 
        }
    }

    private function is_category_screen(): bool {
        global $current_screen;
        return isset( $current_screen->id ) && in_array( $current_screen->id, array_map( static fn( string $tax ): string => "edit-{$tax}", $this->editor_taxonomies ), true );
    }

    public function inject_css(): void {
        if (!$this->is_category_screen()) return;
        
        echo '<style>
            #wpbody { --brand-primary: #25316a; --brand-secondary: #607bbd; }
            .term-description-wrap { display: none !important; }
            .term-description-wrap-custom { display: block !important; margin-bottom: 20px; }
            tr.term-description-wrap-custom { display: table-row !important; }
            .hodima-image-preview { margin-top: 10px; display: block; max-width: 250px; border-radius: 8px; border: 2px dashed var(--brand-secondary); padding: 4px; background: #fff; }
            .hodima-image-preview img { width: 100%; height: auto; border-radius: 4px; display: block; }
            .hodima-btn-custom { background: var(--brand-primary) !important; color: #fff !important; border-color: var(--brand-primary) !important; border-radius: 6px !important; margin-top: 10px !important; }
            .hodima-btn-custom:hover { background: var(--brand-secondary) !important; }
            .column-cat_image { width: 74px; text-align: center !important; }
            .hodima-cat-thumbnail { width: 48px; height: 48px; border-radius: 6px; object-fit: cover; border: 1px solid #ddd; }
        </style>';
    }

    private function get_advanced_editor_settings(int $rows): array {
        return [
            'textarea_name' => 'description', // ذخیره اتوماتیک در دیتابیس توسط وردپرس
            'textarea_rows' => $rows,
            'teeny'         => false,
            'media_buttons' => true,
            'quicktags'     => true, 
            'tinymce'       => [
                'toolbar1' => 'formatselect,fontsizeselect,bold,italic,underline,forecolor,backcolor,bullist,numlist,alignleft,aligncenter,alignright,link,unlink',
                'toolbar2' => 'strikethrough,hr,blockquote,pastetext,removeformat,charmap,outdent,indent,undo,redo,wp_help'
            ]
        ];
    }

    // ==========================================
    // لیست دسته‌بندی‌ها (ستون تصویر)
    // ==========================================

    public function add_image_column_to_list(array $columns): array {
        $new_columns = [];
        foreach ($columns as $key => $title) {
            if ($key === 'name') {
                $new_columns['cat_image'] = 'تصویر';
            }
            $new_columns[$key] = $title;
        }
        return $new_columns;
    }

    public function render_image_column_content(string $content, string $column_name, int $term_id): string {
        if ($column_name === 'cat_image') {
            $image_id = get_term_meta($term_id, 'category_image_id', true);
            if ($image_id) {
                $image_url = wp_get_attachment_image_url((int)$image_id, 'thumbnail');
                if ($image_url) {
                    $content = '<img src="' . esc_url($image_url) . '" class="hodima-cat-thumbnail" alt="تصویر شاخص" />';
                }
            } else {
                $content = '<span style="color:#aaa; font-size:12px;">-</span>';
            }
        }
        return $content;
    }

    // ==========================================
    // فرم‌های ویرایشگر متنی
    // ==========================================

    public function add_editor_to_create_form(): void {
        ?>
        <div class="form-field form-required term-description-wrap-custom" id="hodima-cat-editor-add">
            <label for="category_description_add">توضیحات حرفه‌ای دسته‌بندی</label>
            <?php wp_editor('', 'category_description_add', $this->get_advanced_editor_settings(10)); ?>
        </div>
        <?php
    }

    public function add_editor_to_edit_form(object $term): void {
        ?>
        <tr class="form-field term-description-wrap-custom" id="hodima-cat-editor-edit">
            <th scope="row" valign="top"><label for="category_description_edit">توضیحات حرفه‌ای دسته‌بندی</label></th>
            <td>
                <?php wp_editor(htmlspecialchars_decode($term->description ?? ''), 'category_description_edit', $this->get_advanced_editor_settings(15)); ?>
            </td>
        </tr>
        <?php
    }

    // ==========================================
    // فرم‌های آپلود تصویر و ذخیره‌سازی
    // ==========================================

    public function add_image_to_create_form(): void {
        wp_nonce_field('hodima_cat_blog_meta_action', 'hodima_cat_blog_meta_nonce');
        ?>
        <div class="form-field" id="hodima-cat-image-add">
            <label>تصویر شاخص دسته‌بندی</label>
            <input type="hidden" id="category_image_id" name="category_image_id" value="" />
            <div id="hodima-image-preview-container"></div>
            <button type="button" class="button hodima-btn-custom" id="hodima-upload-btn">انتخاب / آپلود تصویر</button>
            <button type="button" class="button button-link-delete" id="hodima-remove-btn" style="display:none; color:#dc2626; margin-top: 10px;">حذف تصویر</button>
        </div>
        <?php
    }

    public function add_image_to_edit_form(object $term): void {
        wp_nonce_field('hodima_cat_blog_meta_action', 'hodima_cat_blog_meta_nonce');
        $image_id = get_term_meta((int) $term->term_id, 'category_image_id', true);
        $image_url = $image_id ? wp_get_attachment_image_url((int) $image_id, 'medium') : '';
        ?>
        <tr class="form-field" id="hodima-cat-image-edit">
            <th scope="row"><label>تصویر شاخص دسته‌بندی</label></th>
            <td>
                <input type="hidden" id="category_image_id" name="category_image_id" value="<?php echo esc_attr($image_id); ?>" />
                <div id="hodima-image-preview-container">
                    <?php if ($image_url): ?>
                        <span class="hodima-image-preview"><img src="<?php echo esc_url($image_url); ?>" alt="پیش‌نمایش" /></span>
                    <?php endif; ?>
                </div>
                <button type="button" class="button hodima-btn-custom" id="hodima-upload-btn">انتخاب / آپلود تصویر</button>
                <button type="button" class="button button-link-delete" id="hodima-remove-btn" style="<?php echo $image_id ? '' : 'display:none;'; ?> color:#dc2626; margin-top: 10px;">حذف تصویر</button>
            </td>
        </tr>
        <?php
    }

    public function save_category_image_meta(int $term_id): void {
        if (!isset($_POST['hodima_cat_blog_meta_nonce']) || !wp_verify_nonce($_POST['hodima_cat_blog_meta_nonce'], 'hodima_cat_blog_meta_action')) return;
        if (!current_user_can('manage_categories')) return;

        if (isset($_POST['category_image_id'])) {
            $image_id = absint($_POST['category_image_id']);
            if ($image_id > 0) {
                update_term_meta($term_id, 'category_image_id', $image_id);
            } else {
                delete_term_meta($term_id, 'category_image_id');
            }
        }
    }

    // ==========================================
    // جاوااسکریپت اجرایی (مدیریت DOM و رویدادها)
    // ==========================================

    public function inject_js(): void {
        if (!$this->is_category_screen()) return;
        ?>
        <script>
        document.addEventListener('DOMContentLoaded', () => {
            
            // [حل باگ امنیتی/تداخلی]: حذف ویژگی name از تکست‌اریای دیفالت وردپرس
            // تا هنگام ذخیره‌سازی، محتوای آن جایگزین ادیتور پیشرفته ما نشود.
            const defaultTextareas = document.querySelectorAll('.term-description-wrap textarea');
            defaultTextareas.forEach(el => el.removeAttribute('name'));

            // ۱. جابجایی فیلدها به زیر "دسته مادر"
            const termParentWrap = document.querySelector('.term-parent-wrap');
            
            const imgAdd = document.getElementById('hodima-cat-image-add');
            const edAdd = document.getElementById('hodima-cat-editor-add');
            if (termParentWrap && imgAdd && edAdd) {
                termParentWrap.after(imgAdd);
                imgAdd.after(edAdd);
            }
            
            const imgEdit = document.getElementById('hodima-cat-image-edit');
            const edEdit = document.getElementById('hodima-cat-editor-edit');
            if (termParentWrap && imgEdit && edEdit) {
                termParentWrap.after(imgEdit);
                imgEdit.after(edEdit);
            }

            // ۲. منطق آپلود و حذف رسانه
            const uploadBtn = document.getElementById('hodima-upload-btn');
            const removeBtn = document.getElementById('hodima-remove-btn');
            const inputField = document.getElementById('category_image_id');
            const previewContainer = document.getElementById('hodima-image-preview-container');
            let mediaFrame;

            if (uploadBtn) {
                uploadBtn.addEventListener('click', (e) => {
                    e.preventDefault();
                    if (mediaFrame) { mediaFrame.open(); return; }
                    mediaFrame = wp.media({ title: 'انتخاب تصویر', button: { text: 'ثبت تصویر' }, multiple: false });
                    mediaFrame.on('select', () => {
                        const attachment = mediaFrame.state().get('selection').first().toJSON();
                        inputField.value = attachment.id;
                        const imgUrl = attachment.sizes && attachment.sizes.medium ? attachment.sizes.medium.url : attachment.url;
                        previewContainer.innerHTML = `<span class="hodima-image-preview"><img src="${imgUrl}" alt="پیش‌نمایش" /></span>`;
                        removeBtn.style.display = 'inline-block';
                    });
                    mediaFrame.open();
                });
            }

            if (removeBtn) {
                removeBtn.addEventListener('click', (e) => {
                    e.preventDefault();
                    inputField.value = '';
                    previewContainer.innerHTML = '';
                    removeBtn.style.display = 'none';
                });
            }

            // ۳. همگام‌سازی TinyMCE با فرم Ajax در وردپرس
            const submitBtn = document.getElementById('submit');
            if (submitBtn) {
                submitBtn.addEventListener('click', () => {
                    if (typeof tinyMCE !== 'undefined') {
                        tinyMCE.triggerSave();
                    }
                });
            }
            
            // ۴. پاکسازی فرم پس از ثبت موفقیت‌آمیز ایجکس (در صفحه افزودن دسته جدید)
            if (typeof jQuery !== 'undefined') {
                jQuery(document).ajaxComplete(function(event, xhr, settings) {
                    if (settings.data && settings.data.includes('action=add-tag')) {
                        if (typeof tinyMCE !== 'undefined' && tinyMCE.get('category_description_add')) {
                            tinyMCE.get('category_description_add').setContent('');
                        }
                    }
                });
            }
        });
        </script>
        <?php
    }
}

new Cat_Blog();