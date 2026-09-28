<?php
declare(strict_types=1);
namespace Hodima\Slider;

if (!defined('ABSPATH')) exit;

final class Admin {
    private static bool $saved = false;

    public static function init(): void {
        add_action('admin_menu', [self::class, 'add_menu']);
        add_action('admin_enqueue_scripts', [self::class, 'assets']);
        add_action('admin_init', [self::class, 'handle_save']);
    }

    public static function add_menu(): void {
        // زیر «ابزارهای هدیما» (آدرس admin.php?page=hodima-slider تغییری نکرده)؛
        // بدون Hodima Core مثل قبل منوی سطح اول
        $parent = function_exists('hodima_admin_menu_parent') ? hodima_admin_menu_parent() : '';
        if ($parent !== '') {
            add_submenu_page($parent, __('اسلایدر صفحه اصلی', 'hodima'), __('اسلایدر صفحه اصلی', 'hodima'), 'manage_options', 'hodima-slider', [self::class, 'render']);
            return;
        }

        add_menu_page(
            __('اسلایدر صفحه اصلی', 'hodima'),
            __('اسلایدر صفحه اصلی', 'hodima'),
            'manage_options',
            'hodima-slider',
            [self::class, 'render'],
            'dashicons-images-alt2',
            41
        );
    }

    public static function assets(string $hook): void {
        if (!str_ends_with($hook, '_page_hodima-slider')) return;

        wp_enqueue_media();

        wp_enqueue_style('hodima-slider-admin', Core::asset_url('admin.css'), [], Core::asset_version('admin.css'));
        wp_enqueue_script('hodima-slider-admin', Core::asset_url('admin.js'), [], Core::asset_version('admin.js'), true);

        wp_localize_script('hodima-slider-admin', 'HodimaSliderAdmin', [
            'maxItems' => Core::MAX_ITEMS,
            'i18n' => [
                'maxReached' => __('حداکثر تعداد مجاز رعایت شده است.', 'hodima'),
                'confirmDelete' => __('حذف شود؟', 'hodima'),
                'mediaTitle' => __('انتخاب تصویر', 'hodima'),
            ],
        ]);
    }

    public static function handle_save(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['h_nonce'])) return;
        
        check_admin_referer('h_save', 'h_nonce');
        if (!current_user_can('manage_options')) return;

        /*
         * wp_unslash: بدون آن عنوانی که کوتیشن داشت با بک‌اسلش ذخیره می‌شد.
         * بررسی آرایه: با strict_types، مقدار غیرآرایه در normalize_items(array)
         * خطای مهلک TypeError می‌داد.
         */
        $field = static fn(string $key): array => is_array($_POST[$key] ?? null) ? (array) wp_unslash($_POST[$key]) : [];

        update_option(Core::OPTION_S1,  Helpers::normalize_items($field('hodima_slider1')), false);
        update_option(Core::OPTION_S2A, Helpers::normalize_items($field('hodima_slider2a')), false);
        update_option(Core::OPTION_S2B, Helpers::normalize_items($field('hodima_slider2b')), false);
        update_option(Core::OPTION_S2C, Helpers::normalize_items($field('hodima_slider2c')), false);
        update_option(Core::OPTION_S2D, Helpers::normalize_items($field('hodima_slider2d')), false);
        update_option(Core::OPTION_S3,  Helpers::normalize_items($field('hodima_slider3')), false);
        
        $settings = Helpers::sanitize_settings($field('h_settings'));
        update_option(Core::OPTION_SETTINGS, $settings, false);

        $mobile_settings = [
            'a' => isset($_POST['s2_mobile']['a']) ? 1 : 0,
            'b' => isset($_POST['s2_mobile']['b']) ? 1 : 0,
            'c' => isset($_POST['s2_mobile']['c']) ? 1 : 0,
            'd' => isset($_POST['s2_mobile']['d']) ? 1 : 0,
        ];
        update_option(Core::OPTION_S2_MOBILE, $mobile_settings, false);

        self::$saved = true;
    }

    public static function render(): void {
        if (!current_user_can('manage_options')) wp_die(__('دسترسی غیرمجاز', 'hodima'));

        $itemsS1 = Helpers::get_items(Core::OPTION_S1);
        $itemsS3 = Helpers::get_items(Core::OPTION_S3);
        $settings = get_option(Core::OPTION_SETTINGS, []);
        $cache_time = $settings['cache_time'] ?? 12;
        $s2_mobile = get_option(Core::OPTION_S2_MOBILE, ['a' => 1, 'b' => 1, 'c' => 1, 'd' => 1]);
        
        $s2_columns = [
            'a' => ['label' => __('بخش A (ستون اول)', 'hodima'), 'items' => Helpers::get_items(Core::OPTION_S2A), 'opt' => 'hodima_slider2a'],
            'b' => ['label' => __('بخش B (ستون دوم)', 'hodima'), 'items' => Helpers::get_items(Core::OPTION_S2B), 'opt' => 'hodima_slider2b'],
            'c' => ['label' => __('بخش C (ستون سوم)', 'hodima'), 'items' => Helpers::get_items(Core::OPTION_S2C), 'opt' => 'hodima_slider2c'],
            'd' => ['label' => __('بخش D (ستون چهارم)', 'hodima'), 'items' => Helpers::get_items(Core::OPTION_S2D), 'opt' => 'hodima_slider2d'],
        ];
        ?>
        <div class="wrap hd-wrap h-admin">
            <?php
            hodima_admin_header([
                'title'       => __('اسلایدر صفحه اصلی', 'hodima'),
                'description' => 'اندازه پیشنهادی: دسکتاپ 1920×533 و موبایل 1920×190 (اسلایدر ۱ و ۳) — اسلایدر ۲: حداکثر 600×600 و حداقل 400×400.',
                'icon'        => 'dashicons-images-alt2',
                'badge'       => 'ابزارهای هدیما · نسخه ' . Core::VERSION,
            ]);
            ?>

            <?php if (self::$saved): ?>
                <div class="h-notice"><span><?php esc_html_e('تنظیمات با موفقیت ذخیره شد.', 'hodima'); ?></span><button type="button" class="h-close-btn" aria-label="بستن" onclick="this.parentElement.hidden=true"><span class="dashicons dashicons-no-alt" aria-hidden="true"></span></button></div>
            <?php endif; ?>

            <form method="post" class="h-card">
                <?php wp_nonce_field('h_save', 'h_nonce'); ?>

                <section class="h-section">
                    <h2 class="h-section-title"><?php esc_html_e('تنظیمات پایه و افکت‌ها', 'hodima'); ?></h2>
                    <div class="h-cache-field">
                        <label for="h-cache-time"><?php esc_html_e('زمان کش مرورگر (ساعت):', 'hodima'); ?></label>
                        <input type="number" id="h-cache-time" name="h_settings[cache_time]" value="<?php echo esc_attr((string)$cache_time); ?>" min="1" max="72" class="h-input h-input-dim">
                    </div>
                    <div class="h-dim-wrap">
                        <?php foreach(['s1'=>__('اسلایدر 1', 'hodima'), 's2'=>__('اسلایدر 2', 'hodima'), 's3'=>__('اسلایدر 3', 'hodima')] as $k => $label): 
                            $curr_effect = $settings[$k]['effect'] ?? 'fade';
                        ?>
                            <div class="h-dim-row">
                                <strong class="h-dim-header">
                                    <?php echo esc_html($label); ?>
                                    <select name="h_settings[<?php echo esc_attr($k); ?>][effect]" class="h-effect-select">
                                        <option value="fade" <?php selected($curr_effect, 'fade'); ?>><?php esc_html_e('محو شدن (Fade)', 'hodima'); ?></option>
                                        <option value="slide" <?php selected($curr_effect, 'slide'); ?>><?php esc_html_e('لغزنده (Slide)', 'hodima'); ?></option>
                                        <option value="zoom" <?php selected($curr_effect, 'zoom'); ?>><?php esc_html_e('زوم (Zoom In)', 'hodima'); ?></option>
                                        <option value="flip" <?php selected($curr_effect, 'flip'); ?>><?php esc_html_e('چرخش (Flip)', 'hodima'); ?></option>
                                    </select>
                                </strong>
                                <div class="h-dim-input-group">
                                    <span class="h-dim-label"><?php esc_html_e('دسکتاپ', 'hodima'); ?>:</span>
                                    <input type="number" name="h_settings[<?php echo esc_attr($k); ?>][w]" class="h-input h-input-dim" placeholder="<?php esc_attr_e('عرض', 'hodima'); ?>" value="<?php echo esc_attr($settings[$k]['w'] ?? ''); ?>">
                                    <input type="number" name="h_settings[<?php echo esc_attr($k); ?>][h]" class="h-input h-input-dim" placeholder="<?php esc_attr_e('ارتفاع', 'hodima'); ?>" value="<?php echo esc_attr($settings[$k]['h'] ?? ''); ?>">
                                </div>
                                <div class="h-dim-input-group">
                                    <span class="h-dim-label"><?php esc_html_e('موبایل', 'hodima'); ?>:</span>
                                    <input type="number" name="h_settings[<?php echo esc_attr($k); ?>][w_mobile]" class="h-input h-input-dim" placeholder="<?php esc_attr_e('عرض', 'hodima'); ?>" value="<?php echo esc_attr($settings[$k]['w_mobile'] ?? ''); ?>">
                                    <input type="number" name="h_settings[<?php echo esc_attr($k); ?>][h_mobile]" class="h-input h-input-dim" placeholder="<?php esc_attr_e('ارتفاع', 'hodima'); ?>" value="<?php echo esc_attr($settings[$k]['h_mobile'] ?? ''); ?>">
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>

                <section class="h-section">
                    <h2 class="h-section-title"><?php esc_html_e('اسلایدر 1 : نمایش در فرانت', 'hodima'); ?><span class="h-shortcode-tag" dir="ltr">[hodima-slider1]</span></h2>
                    <div id="h-list-s1"><?php foreach ($itemsS1 as $i => $item) self::row($i, $item, 'hodima_slider1', true); ?></div>
                    <button type="button" class="h-btn h-btn-add" data-h-add data-list="h-list-s1" data-template="h-template-s1"><span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span> <?php esc_html_e('افزودن اسلاید', 'hodima'); ?></button>
                </section>

                <section class="h-section">
                    <h2 class="h-section-title"><?php esc_html_e('اسلایدر 2 : نمایش در فرانت (دارای ۴ ستون)', 'hodima'); ?><span class="h-shortcode-tag" dir="ltr">[hodima-slider2]</span></h2>
                    
                    <?php foreach ($s2_columns as $col_id => $col_data): ?>
                        <div class="h-sub-title <?php echo $col_id !== 'a' ? 'h-sub-title--spaced' : ''; ?>">
                            <span><?php echo esc_html($col_data['label']); ?></span>
                            <label class="h-mobile-toggle"><input type="checkbox" name="s2_mobile[<?php echo $col_id; ?>]" value="1" <?php checked($s2_mobile[$col_id], 1); ?>> <?php esc_html_e('نمایش در موبایل', 'hodima'); ?></label>
                        </div>
                        <div id="h-list-<?php echo $col_id; ?>"><?php foreach ($col_data['items'] as $i => $item) self::row($i, $item, $col_data['opt']); ?></div>
                        <button type="button" class="h-btn h-btn-add" data-h-add data-list="h-list-<?php echo $col_id; ?>" data-template="h-template-<?php echo $col_id; ?>"><span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span> <?php esc_html_e('افزودن اسلاید', 'hodima'); ?></button>
                    <?php endforeach; ?>
                </section>

                <section class="h-section">
                    <h2 class="h-section-title"><?php esc_html_e('اسلایدر 3 : نمایش در فرانت', 'hodima'); ?><span class="h-shortcode-tag" dir="ltr">[hodima-slider3]</span></h2>
                    <div id="h-list-s3"><?php foreach ($itemsS3 as $i => $item) self::row($i, $item, 'hodima_slider3', true); ?></div>
                    <button type="button" class="h-btn h-btn-add" data-h-add data-list="h-list-s3" data-template="h-template-s3"><span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span> <?php esc_html_e('افزودن اسلاید', 'hodima'); ?></button>
                </section>

                <div class="hd-actions h-actions">
                    <button type="submit" class="h-btn h-btn-primary"><?php esc_html_e('ذخیره همه تغییرات', 'hodima'); ?></button>
                </div>
            </form>

            <template id="h-template-s1"><?php self::row('__I__', [], 'hodima_slider1', true); ?></template>
            <template id="h-template-a"><?php self::row('__I__', [], 'hodima_slider2a'); ?></template>
            <template id="h-template-b"><?php self::row('__I__', [], 'hodima_slider2b'); ?></template>
            <template id="h-template-c"><?php self::row('__I__', [], 'hodima_slider2c'); ?></template>
            <template id="h-template-d"><?php self::row('__I__', [], 'hodima_slider2d'); ?></template>
            <template id="h-template-s3"><?php self::row('__I__', [], 'hodima_slider3', true); ?></template>
        </div>
        <?php
    }

    private static function row(mixed $i, array $item, string $name, bool $has_mobile = false): void {
        $img_id = absint($item['image_id'] ?? 0);
        $mob_id = absint($item['mobile_image_id'] ?? 0); 
        ?>
        <div class="h-grid">
            <?php // ترتیب: دستگیره کشیدن، شماره، بالا/پایین — ترتیب ذخیره = ترتیب همین فهرست ?>
            <div class="h-order">
                <span class="h-drag dashicons dashicons-move" title="برای جابه‌جایی بکشید" aria-hidden="true"></span>
                <span class="h-num">1</span>
                <button type="button" class="h-move h-move-up" aria-label="انتقال به بالا"><span class="dashicons dashicons-arrow-up-alt2" aria-hidden="true"></span></button>
                <button type="button" class="h-move h-move-down" aria-label="انتقال به پایین"><span class="dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span></button>
            </div>
            <div class="h-images-wrapper">
                <div class="h-img-box">
                    <span class="h-img-label"><?php echo $has_mobile ? 'دسکتاپ' : 'تصویر'; ?></span>
                    <img src="<?php echo esc_url(Helpers::get_image_url($img_id)); ?>" class="h-preview" alt="پیش‌نمایش">
                    <input type="hidden" name="<?php echo esc_attr($name); ?>[<?php echo esc_attr((string)$i); ?>][image_id]" value="<?php echo esc_attr((string)$img_id); ?>" class="h-img-id">
                </div>
                <?php if ($has_mobile): ?>
                <div class="h-img-box">
                    <span class="h-img-label">موبایل <small>(اختیاری)</small></span>
                    <img src="<?php echo esc_url(Helpers::get_image_url($mob_id)); ?>" class="h-preview" alt="پیش‌نمایش">
                    <input type="hidden" name="<?php echo esc_attr($name); ?>[<?php echo esc_attr((string)$i); ?>][mobile_image_id]" value="<?php echo esc_attr((string)$mob_id); ?>" class="h-img-id">
                </div>
                <?php endif; ?>
            </div>
            <div class="h-grid-fields">
                <input type="url" name="<?php echo esc_attr($name); ?>[<?php echo esc_attr((string)$i); ?>][link_url]" class="h-input" placeholder="https://example.com" value="<?php echo esc_url($item['link_url'] ?? ''); ?>">
                <input type="number" name="<?php echo esc_attr($name); ?>[<?php echo esc_attr((string)$i); ?>][duration]" class="h-input" min="2" max="20" placeholder="<?php esc_attr_e('زمان (ثانیه)', 'hodima'); ?>" value="<?php echo esc_attr((string)($item['duration'] ?? Core::DEFAULT_DUR)); ?>">
                
                <?php
                /*
                 * «عنوان» حالا متن جایگزین تصویر (alt) است — وقتی خود تصویر در
                 * کتابخانه رسانه alt ندارد. فیلدهای «توضیحات» و «متن دکمه» حذف شدند:
                 * فقط در یک بلوک پنهان «مخصوص سئو» استفاده می‌شدند که طبق سیاست
                 * اسپم گوگل «متن پنهان» است.
                 */
                ?>
                <input type="text" name="<?php echo esc_attr($name); ?>[<?php echo esc_attr((string)$i); ?>][title]" class="h-input" placeholder="<?php esc_attr_e('متن جایگزین تصویر (alt) — توصیف کوتاه بنر', 'hodima'); ?>" value="<?php echo esc_attr($item['title'] ?? ''); ?>">
            </div>
            <button type="button" class="h-btn h-btn-danger"><?php esc_html_e('حذف', 'hodima'); ?></button>
        </div>
    <?php }
}