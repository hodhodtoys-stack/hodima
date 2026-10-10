<?php
/**
 * Meta Boxes for Notification Settings
 *
 * @version 2.0.4
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/notification-save.php';

final class HodimaNotificationSettings
{
    private const POST_TYPE = 'hd_notification';
    private const NONCE_ACTION = 'hodima_save_notification_data';
    public const NONCE_NAME = 'hodima_notification_meta_nonce';

    public function __construct()
    {
        add_action('admin_enqueue_scripts', [$this, 'enqueueScripts']);
        add_action('add_meta_boxes', [$this, 'addMetaBox']);
    }

    public function enqueueScripts(string $hook): void
    {
        global $typenow;

        if ($typenow === self::POST_TYPE) {
            wp_enqueue_media();
            $base = '/components/notification/assets/';
            $ver  = static fn(string $f): string => (string) (@filemtime(HODIMA_MEDIA_DIR . $base . $f) ?: '3.0.0');
            wp_enqueue_style('hd-notification-metabox-css', HODIMA_MEDIA_URL . $base . 'css/notification-metabox.css', [], $ver('css/notification-metabox.css'));
            wp_enqueue_script('hd-notification-metabox-js', HODIMA_MEDIA_URL . $base . 'js/notification-metabox.js', [], $ver('js/notification-metabox.js'), true);
        }
    }

    public function addMetaBox(): void
    {
        add_meta_box('hd_notification_settings', 'تنظیمات پیشرفته نوتفیکیشن', [$this, 'renderMetaBox'], self::POST_TYPE, 'normal', 'high');
    }

    private function getDefaults(): array
    {
        return [
            'hd_notif_is_active'          => '1',
            'hd_notif_priority'           => '01', // پیش‌فرض 01
            'hd_notif_main_link'          => '',
            'hd_notif_bg_color'           => '#ffffff',
            'hd_notif_bg_transparent'     => '0',
            'hd_notif_close_color'        => '#ff0000',
            'hd_notif_bg_image'           => '',
            'hd_notif_start_date'         => '',
            'hd_notif_end_date'           => '',
            'hd_notif_show_count'         => '0',
            'hd_notif_target_type'        => 'all',
            'hd_notif_target_page'        => '',
            'hd_notif_delay'              => '0',
            'hd_notif_close_btn'          => '1',
            'hd_notif_auto_close_time'    => '0',
            'hd_notif_device'             => 'all',
            'hd_notif_user_status'        => 'all',
            'hd_notif_position'           => 'center',
            'hd_notif_width'              => '400',
            'hd_notif_height'             => '0',
            'hd_notif_trigger_exit'       => '0',
            'hd_notif_trigger_scroll'     => '0',
            'hd_notif_trigger_inactivity' => '0',
            'hd_notif_anim_in'            => 'zoom_in',
            'hd_notif_anim_out'           => 'fade_out',
            'hd_notif_shadow'             => 'soft',
            'hd_notif_target_role'        => 'all',
            'hd_notif_target_post_type'   => 'all',
            'hd_notif_target_utm'         => '',
            'hd_notif_target_referrer'    => '',
        ];
    }

    public function renderMetaBox(WP_Post $post): void
    {
        wp_nonce_field(self::NONCE_ACTION, self::NONCE_NAME);

        $defaults = $this->getDefaults();
        $meta = [];

        foreach ($defaults as $key => $default_value) {
            $saved_value = get_post_meta($post->ID, '_' . $key, true);
            $meta[$key] = ($saved_value !== '') ? $saved_value : $default_value;
        }
        ?>
        <div class="hdn-admin-wrapper">
            <header class="hdn-header">
                <div class="hdn-header-info">
                    <div class="hdn-icon"><span class="dashicons dashicons-megaphone" aria-hidden="true"></span></div>
                    <div>
                        <h1>تنظیمات نوتفیکیشن پاپ‌آپ</h1>
                        <p>تصویر پاپ‌آپ را آپلود کنید و لینک دلخواه خود را قرار دهید.</p>
                    </div>
                </div>
                <div class="hdn-version-badge">v 2.0.4</div>
            </header>

            <section class="hdn-card">
                <h4 class="hdn-card-title">وضعیت و اولویت</h4>
                <table class="form-table">
                    <tr class="hdn-row-divider">
                        <th>فعال بودن</th>
                        <td>
                            <label class="hdn-status-toggle <?php echo ($meta['hd_notif_is_active'] === '1') ? '' : 'inactive'; ?>" id="hd_status_label">
                                <input type="checkbox" id="hd_notif_is_active" name="hd_notif_is_active" value="1" <?php checked($meta['hd_notif_is_active'], '1'); ?>>
                                <span id="hd_status_text"><?php echo ($meta['hd_notif_is_active'] === '1') ? esc_html('نوتفیکیشن فعال است و در سایت نمایش داده می‌شود') : esc_html('نوتفیکیشن غیرفعال است'); ?></span>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="hd_notif_priority">اولویت در صف نمایش</label></th>
                        <td>
                            <div class="hdn-inline-addon">
                                <!-- تبدیل به فیلد متنی برای نمایش دقیق صفرِ پشت عدد -->
                                <input type="text" id="hd_notif_priority" name="hd_notif_priority" value="<?php echo esc_attr($meta['hd_notif_priority']); ?>" maxlength="2" pattern="[0-9]*" style="width: 80px; text-align: center;">
                            </div>
                            <span style="font-size: 11px; color: #64748b; margin-right: 10px;">(از 01 الی 99. پیش‌فرض: 01)</span>
                        </td>
                    </tr>
                </table>
            </section>

            <section class="hdn-card" id="hdn-appearance-box">
                <h4 class="hdn-card-title">محتوا و عکس پاپ‌آپ</h4>
                <table class="form-table">
                    <tr class="hdn-row-divider">
                        <th><label for="hd_notif_position">موقعیت و ابعاد نمایش</label></th>
                        <td>
                            <div class="hdn-flex-row">
                                <div class="hdn-flex-col">
                                    <label for="hd_notif_position">موقعیت</label>
                                    <select id="hd_notif_position" name="hd_notif_position" style="min-width: 200px; text-align: right;">
                                        <optgroup label="پاپ‌آپ (مرکز صفحه)">
                                            <option value="center" <?php selected($meta['hd_notif_position'], 'center'); ?>>پاپ‌آپ وسط صفحه (Modal)</option>
                                        </optgroup>
                                        <optgroup label="موقعیت‌های گوشه (کوچک)">
                                            <option value="bottom_right" <?php selected($meta['hd_notif_position'], 'bottom_right'); ?>>گوشه پایین سمت راست</option>
                                            <option value="bottom_left" <?php selected($meta['hd_notif_position'], 'bottom_left'); ?>>گوشه پایین سمت چپ</option>
                                        </optgroup>
                                    </select>
                                </div>

                                <div class="hdn-flex-col">
                                    <label for="hd_notif_width">عرض (Width)</label>
                                    <div class="hdn-inline-addon">
                                        <input type="number" id="hd_notif_width" name="hd_notif_width" value="<?php echo esc_attr($meta['hd_notif_width']); ?>" min="100" max="2000" style="width: 100px;">
                                        <span>پیکسل</span>
                                    </div>
                                </div>

                                <div class="hdn-flex-col">
                                    <label for="hd_notif_height">ارتفاع (Height)</label>
                                    <div class="hdn-inline-addon">
                                        <input type="number" id="hd_notif_height" name="hd_notif_height" value="<?php echo esc_attr($meta['hd_notif_height']); ?>" min="0" max="2000" style="width: 100px;">
                                        <span>پیکسل</span>
                                    </div>
                                    <span style="font-size: 11px; color: #94a3b8; margin-top: 4px; text-align: center;">(عدد 0 = ارتفاع خودکار براساس تصویر)</span>
                                </div>
                            </div>
                        </td>
                    </tr>

                    <tr>
                        <th>تصویر و لینک پاپ‌آپ</th>
                        <td>
                            <div class="hdn-flex-row" style="align-items: flex-start;">
                                <div class="hdn-flex-col" style="flex: 1;">
                                    <label>آپلود تصویر اصلی</label>
                                    <div class="hdn-media-container" style="display:inline-flex;">
                                        <div class="hdn-img-preview-box <?php echo empty($meta['hd_notif_bg_image']) ? 'empty' : ''; ?>">
                                            <span class="placeholder dashicons dashicons-format-image" aria-hidden="true"></span>
                                            <img id="hd_notif_bg_preview" src="<?php echo esc_url($meta['hd_notif_bg_image']); ?>" alt="Background Preview">
                                        </div>
                                        <div class="hdn-media-actions">
                                            <input type="hidden" id="hd_notif_bg_image" name="hd_notif_bg_image" value="<?php echo esc_url($meta['hd_notif_bg_image']); ?>">
                                            <button type="button" class="button hdn-upload-btn" data-target="#hd_notif_bg_image" data-preview="#hd_notif_bg_preview" data-remove="#hd_remove_bg_btn">انتخاب تصویر</button>
                                            <button type="button" class="button hdn-remove-btn" id="hd_remove_bg_btn" <?php echo empty($meta['hd_notif_bg_image']) ? 'style="display:none;"' : ''; ?>>حذف تصویر</button>
                                        </div>
                                    </div>
                                </div>

                                <div class="hdn-flex-col" style="flex: 2; padding-top: 25px;">
                                    <label for="hd_notif_main_link">لینک مقصد (کاربر با کلیک روی عکس به این لینک می‌رود)</label>
                                    <input type="url" id="hd_notif_main_link" name="hd_notif_main_link" value="<?php echo esc_url($meta['hd_notif_main_link']); ?>" placeholder="https://..." style="text-align: left; direction: ltr;">
                                    <span style="font-size: 11px; color: #94a3b8; margin-top: 4px;">در صورت خالی بودن، پاپ‌آپ قابلیت کلیک نخواهد داشت.</span>
                                </div>
                            </div>
                        </td>
                    </tr>
                </table>
            </section>

            <section class="hdn-card">
                <h4 class="hdn-card-title">تنظیمات پس‌زمینه و استایل</h4>
                <table class="form-table">
                    <tr>
                        <th>استایل پس‌زمینه</th>
                        <td>
                            <div class="hdn-flex-row" style="align-items: center;">
                                <div class="hdn-flex-col" style="flex: 1;">
                                    <label for="hd_notif_bg_color">رنگ پس‌زمینه (در صورت عدم شفافیت)</label>
                                    <input type="color" id="hd_notif_bg_color" name="hd_notif_bg_color" value="<?php echo esc_attr($meta['hd_notif_bg_color']); ?>">
                                </div>

                                <div class="hdn-flex-col" style="flex: 1;">
                                    <label style="cursor:pointer; display:flex; align-items:center; gap:8px;">
                                        <input type="checkbox" id="hd_notif_bg_transparent" name="hd_notif_bg_transparent" value="1" <?php checked($meta['hd_notif_bg_transparent'], '1'); ?>>
                                        <strong>پس‌زمینه کاملاً شفاف باشد</strong>
                                    </label>
                                    <span style="font-size: 11px; color: #94a3b8;">در این حالت فقط عکسی که آپلود کرده‌اید دیده می‌شود.</span>
                                </div>
                            </div>
                        </td>
                    </tr>
                </table>
            </section>

            <section class="hdn-card">
                <h4 class="hdn-card-title">تریگرها و انیمیشن</h4>
                <table class="form-table">
                    <tr class="hdn-row-divider">
                        <th>محرک‌های نمایش (Triggers)</th>
                        <td>
                            <div class="hdn-flex-row">
                                <div class="hdn-flex-col" style="flex: 1;">
                                    <label style="cursor:pointer; display:flex; align-items:center; gap:8px;">
                                        <input type="checkbox" id="hd_notif_trigger_exit" name="hd_notif_trigger_exit" value="1" <?php checked($meta['hd_notif_trigger_exit'], '1'); ?>>
                                        <strong>خروج از صفحه (Exit Intent)</strong>
                                    </label>
                                </div>
                                <div class="hdn-flex-col" style="flex: 1;">
                                    <label for="hd_notif_trigger_scroll">پس از اسکرول صفحه (%)</label>
                                    <input type="number" id="hd_notif_trigger_scroll" name="hd_notif_trigger_scroll" value="<?php echo esc_attr($meta['hd_notif_trigger_scroll']); ?>" min="0" max="100" style="width: 100px;">
                                </div>
                                <div class="hdn-flex-col" style="flex: 1;">
                                    <label for="hd_notif_delay">تاخیر زمانی (ثانیه)</label>
                                    <input type="number" id="hd_notif_delay" name="hd_notif_delay" value="<?php echo esc_attr($meta['hd_notif_delay']); ?>" min="0" style="width: 100px;">
                                </div>
                                <div class="hdn-flex-col" style="flex: 1;">
                                    <label for="hd_notif_trigger_inactivity">عدم فعالیت (ثانیه)</label>
                                    <input type="number" id="hd_notif_trigger_inactivity" name="hd_notif_trigger_inactivity" value="<?php echo esc_attr($meta['hd_notif_trigger_inactivity']); ?>" min="0" style="width: 100px;">
                                </div>
                            </div>
                        </td>
                    </tr>

                    <tr class="hdn-row-divider">
                        <th>انیمیشن‌ها</th>
                        <td>
                            <div class="hdn-flex-row">
                                <div class="hdn-flex-col" style="flex: 1;">
                                    <label for="hd_notif_anim_in">انیمیشن ورود</label>
                                    <select id="hd_notif_anim_in" name="hd_notif_anim_in">
                                        <option value="zoom_in" <?php selected($meta['hd_notif_anim_in'], 'zoom_in'); ?>>بزرگ‌نمایی (Zoom In)</option>
                                        <option value="fade_in" <?php selected($meta['hd_notif_anim_in'], 'fade_in'); ?>>محو شدن (Fade In)</option>
                                        <option value="slide_up" <?php selected($meta['hd_notif_anim_in'], 'slide_up'); ?>>پرش به بالا (Slide Up)</option>
                                        <option value="bounce" <?php selected($meta['hd_notif_anim_in'], 'bounce'); ?>>جهش (Bounce)</option>
                                    </select>
                                </div>
                                <div class="hdn-flex-col" style="flex: 1;">
                                    <label for="hd_notif_anim_out">انیمیشن خروج</label>
                                    <select id="hd_notif_anim_out" name="hd_notif_anim_out">
                                        <option value="fade_out" <?php selected($meta['hd_notif_anim_out'], 'fade_out'); ?>>محو شدن (Fade Out)</option>
                                        <option value="slide_down" <?php selected($meta['hd_notif_anim_out'], 'slide_down'); ?>>سقوط (Slide Down)</option>
                                    </select>
                                </div>
                            </div>
                        </td>
                    </tr>
                </table>
            </section>

            <section class="hdn-card" id="hdn-rules-box">
                <h4 class="hdn-card-title">قوانین و رفتار نمایش</h4>
                <table class="form-table">
                    <tr class="hdn-row-divider">
                        <th><label for="hd_notif_close_btn">دکمه بستن (X)</label></th>
                        <td>
                            <div class="hdn-flex-row" style="align-items: center;">
                                <div class="hdn-flex-col">
                                    <label style="cursor:pointer; display:flex; align-items:center; gap:8px;">
                                        <input type="checkbox" id="hd_notif_close_btn" name="hd_notif_close_btn" value="1" <?php checked($meta['hd_notif_close_btn'], '1'); ?>>
                                        فعال بودن دکمه بستن پاپ‌آپ
                                    </label>
                                </div>
                                <div class="hdn-flex-col" style="margin-right: 30px;">
                                    <label for="hd_notif_close_color" style="display:inline-block; margin-left: 10px;">رنگ دکمه بستن</label>
                                    <input type="color" id="hd_notif_close_color" name="hd_notif_close_color" value="<?php echo esc_attr($meta['hd_notif_close_color']); ?>">
                                </div>
                            </div>
                        </td>
                    </tr>

                    <tr class="hdn-row-divider">
                        <th><label>دستگاه و کاربران هدف</label></th>
                        <td>
                            <div class="hdn-flex-row">
                                <div class="hdn-flex-col" style="flex: 1; min-width: 200px;">
                                    <label for="hd_notif_device">دستگاه هدف</label>
                                    <select id="hd_notif_device" name="hd_notif_device">
                                        <option value="all" <?php selected($meta['hd_notif_device'], 'all'); ?>>همه دستگاه‌ها</option>
                                        <option value="desktop" <?php selected($meta['hd_notif_device'], 'desktop'); ?>>فقط دسکتاپ</option>
                                        <option value="mobile" <?php selected($meta['hd_notif_device'], 'mobile'); ?>>فقط موبایل</option>
                                    </select>
                                </div>

                                <div class="hdn-flex-col" style="flex: 1; min-width: 200px;">
                                    <label for="hd_notif_user_status">وضعیت لاگین</label>
                                    <select id="hd_notif_user_status" name="hd_notif_user_status">
                                        <option value="all" <?php selected($meta['hd_notif_user_status'], 'all'); ?>>همه کاربران</option>
                                        <option value="logged_in" <?php selected($meta['hd_notif_user_status'], 'logged_in'); ?>>کاربران وارد شده (لاگین)</option>
                                        <option value="logged_out" <?php selected($meta['hd_notif_user_status'], 'logged_out'); ?>>کاربران مهمان</option>
                                    </select>
                                </div>
                            </div>
                        </td>
                    </tr>

                    <tr class="hdn-row-divider">
                        <th><label>محتوا و صفحات نمایش</label></th>
                        <td>
                            <div class="hdn-flex-row">
                                <div class="hdn-flex-col" style="flex: 1; min-width: 200px;">
                                    <label for="hd_notif_target_post_type">نوع پست (Post Type)</label>
                                    <select id="hd_notif_target_post_type" name="hd_notif_target_post_type">
                                        <option value="all" <?php selected($meta['hd_notif_target_post_type'], 'all'); ?>>همه صفحات سایت</option>
                                        <option value="post" <?php selected($meta['hd_notif_target_post_type'], 'post'); ?>>فقط مقاله‌ها (Posts)</option>
                                        <option value="page" <?php selected($meta['hd_notif_target_post_type'], 'page'); ?>>فقط برگه‌ها (Pages)</option>
                                        <option value="product" <?php selected($meta['hd_notif_target_post_type'], 'product'); ?>>فقط محصولات (Products)</option>
                                    </select>
                                </div>

                                <div class="hdn-flex-col" style="flex: 1; min-width: 200px;">
                                    <label for="hd_notif_target_type">نمایش بر اساس لینک</label>
                                    <select id="hd_notif_target_type" name="hd_notif_target_type">
                                        <option value="all" <?php selected($meta['hd_notif_target_type'], 'all'); ?>>همه صفحات</option>
                                        <option value="specific" <?php selected($meta['hd_notif_target_type'], 'specific'); ?>>چند صفحه / چند لینک خاص</option>
                                        <option value="exclude" <?php selected($meta['hd_notif_target_type'], 'exclude'); ?>>همه صفحات به‌جز لینک‌های زیر</option>
                                    </select>
                                </div>

                                <div class="hdn-flex-col hdn-target-page-wrapper" id="hdn_target_page_wrapper" style="flex: 2; min-width: 250px;">
                                    <label for="hd_notif_target_page">لینک یا لیست لینک‌ها</label>
                                    <textarea id="hd_notif_target_page" name="hd_notif_target_page" rows="4" style="width: 100%; min-height: 110px;" placeholder="/about-us/"><?php echo esc_textarea($meta['hd_notif_target_page']); ?></textarea>
                                </div>
                            </div>
                            
                            <div class="hdn-flex-row" style="margin-top: 15px;">
                                <div class="hdn-flex-col" style="flex: 1;">
                                    <label for="hd_notif_target_utm">پارامتر تبلیغاتی (UTM Source)</label>
                                    <input type="text" id="hd_notif_target_utm" name="hd_notif_target_utm" value="<?php echo esc_attr($meta['hd_notif_target_utm']); ?>" placeholder="مثال: yektanet">
                                </div>

                                <div class="hdn-flex-col" style="flex: 1;">
                                    <label for="hd_notif_target_referrer">ارجاع‌دهنده (Referrer)</label>
                                    <input type="text" id="hd_notif_target_referrer" name="hd_notif_target_referrer" value="<?php echo esc_attr($meta['hd_notif_target_referrer']); ?>" placeholder="مثال: google.com یا yektanet.com">
                                </div>
                            </div>
                        </td>
                    </tr>

                    <tr class="hdn-row-divider">
                        <th><label>زمان و دفعات نمایش</label></th>
                        <td>
                            <div class="hdn-flex-row">
                                <div class="hdn-flex-col" style="flex: 1; min-width: 150px;">
                                    <label for="hd_notif_start_date">تاریخ شروع</label>
                                    <input type="date" id="hd_notif_start_date" name="hd_notif_start_date" value="<?php echo esc_attr($meta['hd_notif_start_date']); ?>">
                                </div>

                                <div class="hdn-flex-col" style="flex: 1; min-width: 150px;">
                                    <label for="hd_notif_end_date">تاریخ پایان</label>
                                    <input type="date" id="hd_notif_end_date" name="hd_notif_end_date" value="<?php echo esc_attr($meta['hd_notif_end_date']); ?>">
                                </div>

                                <div class="hdn-flex-col" style="flex: 1; min-width: 150px;">
                                    <label for="hd_notif_show_count">دفعات نمایش (0 = نامحدود)</label>
                                    <div class="hdn-inline-addon">
                                        <input type="number" id="hd_notif_show_count" name="hd_notif_show_count" value="<?php echo esc_attr($meta['hd_notif_show_count']); ?>" min="0" style="width: 100px;">
                                    </div>
                                </div>
                            </div>
                        </td>
                    </tr>
                </table>
            </section>

            <section class="hdn-card hdn-preview-section" style="margin-bottom:0;">
                <h4 class="hdn-card-title" style="text-align: right;">پیش‌نمایش زنده</h4>
                <p style="text-align: right; color: var(--hdn-muted); font-size: 13px; margin-bottom: 20px;">
                    با کلیک روی دکمه زیر، ظاهر پاپ‌آپ خود را مشاهده کنید. 
                </p>
                <button type="button" id="hdn_btn_generate_preview" class="button button-primary button-large" style="background: var(--hdn-primary); border-color: var(--hdn-primary); box-shadow: none;">
                    مشاهده پیش‌نمایش زنده
                </button>

                <div id="hdn_preview_canvas" class="hdn-preview-canvas"></div>
            </section>
        </div>
        <?php
    }
}

new HodimaNotificationSettings();