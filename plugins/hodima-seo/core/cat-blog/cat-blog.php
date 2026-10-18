<?php
/**
 * مسیر: wp-content/plugins/hodima-seo/core/cat-blog/cat-blog.php
 * پشتیبانی اختصاصی برای دسته‌بندی مقالات (وبلاگ) + رابط کاربری بهینه‌شده
 *
 * ویرایشگر پیشرفته توضیحات برای دسته محصول هم هست (قبلا در inc/enqueue.php
 * قالب — بازسازی قالب، مرحله ۲). قالب پیش‌فرض وردپرس را پنهان و نام فیلدش را
 * حذف می‌کند تا دو فیلد «description» در یک فرم نباشند (نسخه قالب فقط پنهان
 * می‌کرد). تصویر دسته فقط برای دسته نوشته‌ها؛ دسته محصول تصویر ووکامرس را دارد.
 *
 * از 2.3.1: «ساختار تیترها» زیر ویرایشگر (assets/heading-guide.js|css): منوی قالب‌بندی بدون H1
 * و با نام فارسی سطح‌ها، بررسی زنده ترتیب تیترها (پرش سطح، H1، تیتر خالی) و «مرتب‌سازی خودکار».
 * چرا: عنوان دسته H1 صفحه است و PageSpeed (heading-order) پرش سطح در توضیح دسته را ایراد
 * می‌گرفت (دسته «اکسسوری»: h5 بعد از h3). متن ذخیره‌شده فقط با کار خود مدیر عوض می‌شود.
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

            if ( $this->is_category_screen() ) {
                $this->enqueue_heading_guide();
            }
        }
    }

    /** CSS/JS «ساختار تیترها»؛ نسخه = زمان تغییر فایل (به‌روزرسانی از کش مرورگر پنهان نماند). */
    private function enqueue_heading_guide(): void {
        $ver = static function ( string $file ): string {
            $path = __DIR__ . '/assets/' . $file;
            return is_file( $path ) ? (string) filemtime( $path ) : HODIMA_SEO_VERSION;
        };

        wp_enqueue_style( 'hodima-heading-guide', HODIMA_SEO_URL . '/core/cat-blog/assets/heading-guide.css', [ 'dashicons' ], $ver( 'heading-guide.css' ) );
        wp_enqueue_script( 'hodima-heading-guide', HODIMA_SEO_URL . '/core/cat-blog/assets/heading-guide.js', [ 'editor' ], $ver( 'heading-guide.js' ), [
            'in_footer' => true,
        ] );
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
                'toolbar1'      => 'formatselect,fontsizeselect,bold,italic,underline,forecolor,backcolor,bullist,numlist,alignleft,aligncenter,alignright,link,unlink',
                'toolbar2'      => 'strikethrough,hr,blockquote,pastetext,removeformat,charmap,outdent,indent,undo,redo,wp_help',
                // H1 نیست (عنوان دسته H1 صفحه است)؛ نام هر سطح کاربردش را می‌گوید (راهنما: «ساختار تیترها» زیر ویرایشگر)
                'block_formats' => 'پاراگراف=p;تیتر بخش (H2)=h2;زیربخش (H3)=h3;جزئیات (H4)=h4;ریزجزئیات (H5)=h5;ریزترین (H6)=h6',
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
            <?php $this->render_heading_guide( 'category_description_add' ); ?>
        </div>
        <?php
    }

    public function add_editor_to_edit_form(object $term): void {
        ?>
        <tr class="form-field term-description-wrap-custom" id="hodima-cat-editor-edit">
            <th scope="row" valign="top"><label for="category_description_edit">توضیحات حرفه‌ای دسته‌بندی</label></th>
            <td>
                <?php wp_editor(htmlspecialchars_decode($term->description ?? ''), 'category_description_edit', $this->get_advanced_editor_settings(15)); ?>
                <?php $this->render_heading_guide( 'category_description_edit' ); ?>
            </td>
        </tr>
        <?php
    }

    /**
     * کادر «ساختار تیترها» زیر ویرایشگر.
     *
     * بخش‌های ثابت (عنوان، توضیح، راهنما) همین‌جا؛ فهرست تیترها، وضعیت و دکمه‌ها را
     * assets/heading-guide.js زنده پر می‌کند. بدون جاوااسکریپت پنهان می‌ماند (hidden).
     */
    private function render_heading_guide( string $editor_id ): void {
        $levels = [
            [ 'پاراگراف', '—', 'متن معمولی', 'کلیپس‌های ما در سه سایز تولید می‌شوند…' ],
            [ 'تیتر بخش', 'H2', 'بخش‌های اصلی متن', 'انواع کلیپس · بسته‌بندی و ارسال' ],
            [ 'زیربخش', 'H3', 'زیر یک «تیتر بخش»', 'کلیپس پلومریا · کلیپس فلزی' ],
            [ 'جزئیات', 'H4', 'زیر یک «زیربخش»', 'سایزها · رنگ‌بندی' ],
            [ 'ریزجزئیات', 'H5', 'زیر «جزئیات»', 'سایز کوچک' ],
            [ 'ریزترین', 'H6', 'زیر «ریزجزئیات» (به‌ندرت لازم می‌شود)', '—' ],
        ];
        ?>
        <div class="hodima-hg" data-editor="<?php echo esc_attr( $editor_id ); ?>" hidden>
            <div class="hodima-hg__head">
                <span class="dashicons dashicons-editor-ol" aria-hidden="true"></span>
                <h3 class="hodima-hg__title">ساختار تیترها</h3>
                <span class="hodima-hg__status" data-state="empty"></span>
            </div>

            <p class="hodima-hg__lead">
                عنوان دسته خودش تیتر اصلی (H1) صفحه است؛ پس تیترهای این متن از <strong>«تیتر بخش (H2)»</strong> شروع می‌شوند
                و هر تیتر <strong>حداکثر یک پله</strong> پایین‌تر از تیتر بالایی‌اش است. گوگل و صفحه‌خوان نابینایان ساختار صفحه را
                از همین ترتیب می‌فهمند (PageSpeed ← «ترتیب تیترها»).
            </p>

            <ol class="hodima-hg__outline" aria-label="فهرست تیترهای متن"></ol>

            <div class="hodima-hg__actions">
                <button type="button" class="button button-primary hodima-hg__fix" disabled>
                    <span class="dashicons dashicons-sort" aria-hidden="true"></span>
                    مرتب‌سازی خودکار تیترها
                </button>
                <button type="button" class="button hodima-hg__undo" hidden>
                    <span class="dashicons dashicons-undo" aria-hidden="true"></span>
                    بازگردانی
                </button>
                <span class="hodima-hg__msg" role="status" aria-live="polite"></span>
            </div>

            <details class="hodima-hg__help">
                <summary>راهنمای سطح تیترها</summary>
                <table class="hodima-hg__table">
                    <thead>
                        <tr><th scope="col">گزینه منوی ویرایشگر</th><th scope="col">کاربرد</th><th scope="col">نمونه</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $levels as [ $name, $tag, $use, $sample ] ) : ?>
                            <tr>
                                <th scope="row"><?php echo esc_html( $name ); ?><?php if ( '—' !== $tag ) : ?> <span class="hodima-hg__badge"><?php echo esc_html( $tag ); ?></span><?php endif; ?></th>
                                <td><?php echo esc_html( $use ); ?></td>
                                <td class="hodima-hg__sample"><?php echo esc_html( $sample ); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <ul class="hodima-hg__tips">
                    <li><strong>پایین رفتن فقط یک پله:</strong> H2 ← H3 ← H4. پرش (مثلا H2 ← H4 یا H3 ← H5) ایراد است.</li>
                    <li><strong>بالا رفتن آزاد است:</strong> بعد از H4 می‌توانید بخش تازه را دوباره با H2 شروع کنید.</li>
                    <li><strong>برای کوچک یا پررنگ کردن یک خط، سطح تیتر را پایین نبرید؛</strong> از «پررنگ» (B) یا اندازه قلم استفاده کنید.</li>
                    <li><strong>H1 در منو نیست:</strong> عنوان دسته همیشه H1 است و هر صفحه فقط یک H1 دارد.</li>
                    <li><strong>متن چسبانده‌شده</strong> (از Word یا سایت دیگر) را همین‌جا بررسی کنید؛ «مرتب‌سازی خودکار» فقط سطح تیترها را درست می‌کند و متن، لینک و قالب‌بندی را دست نمی‌زند.</li>
                    <li>روی هر تیتر فهرست بالا بزنید تا در ویرایشگر انتخاب شود.</li>
                </ul>
            </details>
        </div>
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