<?php
/**
 * Front-end rendering for Notifications - Optimized Image-Only Version
 *
 * @version 2.0.4
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

final class HodimaNotificationFront
{
    private const POST_TYPE = 'hd_notification';
    private array $notificationsToRender = [];

    public function __construct()
    {
        add_action('wp_enqueue_scripts', [$this, 'prepareAndEnqueueAssets']);
        add_action('wp_footer', [$this, 'renderNotificationsHTML']);
    }

    public function prepareAndEnqueueAssets(): void
    {
        $validNotifications = get_transient('hd_active_notifications');

        if (false === $validNotifications) {
            $query = new WP_Query([
                'post_type'      => self::POST_TYPE,
                'post_status'    => 'publish',
                'posts_per_page' => -1,
                'fields'         => 'ids',
            ]);

            $validNotifications = [];

            if ($query->have_posts()) {
                foreach ($query->posts as $postId) {
                    $meta = $this->getNotificationMeta((int) $postId);
                    if ($meta['hd_notif_is_active'] === '0') continue;

                    $meta['_post_id'] = $postId;
                    $meta['_title']   = (string) get_the_title((int) $postId);
                    $validNotifications[] = $meta;
                }
            }
            
            usort($validNotifications, static function (array $a, array $b): int {
                $pA = (int) ($a['hd_notif_priority'] ?? 1);
                $pB = (int) ($b['hd_notif_priority'] ?? 1);
                return $pA <=> $pB;
            });

            set_transient('hd_active_notifications', $validNotifications, 12 * HOUR_IN_SECONDS);
        }

        if (empty($validNotifications)) return;

        foreach ($validNotifications as $notifMeta) {
            if ($this->passesDisplayRules($notifMeta)) {
                $this->notificationsToRender[] = $notifMeta;
            }
        }

        if (!empty($this->notificationsToRender)) {
            $base = '/components/notification/assets/';
            $ver  = static fn(string $f): string => (string) (@filemtime(get_template_directory() . $base . $f) ?: '3.0.0');
            // نسخه ثابت «2.0.4» به‌روزرسانی را از مرورگر و کش پنهان می‌کرد
            wp_enqueue_style('hd-notification-front-css', get_template_directory_uri() . $base . 'css/notification-front.css', [], $ver('css/notification-front.css'));
            wp_enqueue_script('hd-notification-front-js', get_template_directory_uri() . $base . 'js/notification-front.js', [], $ver('js/notification-front.js'), true);
        }
    }

    public function renderNotificationsHTML(): void
    {
        if (empty($this->notificationsToRender)) return;

        foreach ($this->notificationsToRender as $notifMeta) {
            $this->renderView((int) $notifMeta['_post_id'], $notifMeta);
        }
    }

    private function getNotificationMeta(int $postId): array
    {
        $defaults = $this->getDefaults();
        $meta = [];
        foreach ($defaults as $key => $defaultValue) {
            $val = get_post_meta($postId, '_' . $key, true);
            $meta[$key] = ($val !== '') ? $val : $defaultValue;
        }
        return $meta;
    }

    private function getDefaults(): array
    {
        return [
            'hd_notif_is_active'          => '1',
            'hd_notif_priority'           => '01', // پیش‌فرض
            'hd_notif_main_link'          => '',
            'hd_notif_close_color'        => '#ff0000',
            // نبودن این کلید هشدار PHP «Undefined array key» در هر نمایش می‌داد
            'hd_notif_bg_color'           => '#ffffff',
            'hd_notif_bg_image'           => '',
            'hd_notif_close_btn'          => '1',
            'hd_notif_bg_transparent'     => '0',
            'hd_notif_target_type'        => 'all',
            'hd_notif_target_page'        => '',
            'hd_notif_start_date'         => '',
            'hd_notif_end_date'           => '',
            'hd_notif_delay'              => '0',
            'hd_notif_auto_close_time'    => '0',
            'hd_notif_show_count'         => '0',
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
            'hd_notif_target_role'        => 'all',
            'hd_notif_target_post_type'   => 'all',
            'hd_notif_target_utm'         => '',
            'hd_notif_target_referrer'    => '',
        ];
    }

    private function passesDisplayRules(array $meta): bool
    {
        if ($meta['hd_notif_is_active'] === '0') return false;

        $userStatus = strtolower(trim((string) $meta['hd_notif_user_status']));
        $userStatus = match ($userStatus) { 'guest', 'not_logged_in', 'logged_out' => 'logged_out', 'loggedin', 'logged_in' => 'logged_in', default => 'all' };

        if ($userStatus === 'logged_in' && !is_user_logged_in()) return false;
        if ($userStatus === 'logged_out' && is_user_logged_in()) return false;

        if ($meta['hd_notif_target_role'] !== 'all') {
            if (!is_user_logged_in()) return false;
            $user = wp_get_current_user();
            if (!in_array($meta['hd_notif_target_role'], (array) $user->roles, true)) return false;
        }

        if ($meta['hd_notif_target_post_type'] !== 'all') {
            $target_pt = $meta['hd_notif_target_post_type'];
            if (!is_singular($target_pt) && !(is_home() && $target_pt === 'post') && !(function_exists('is_shop') && is_shop() && $target_pt === 'product')) {
                return false;
            }
        }

        $targetType = (string) $meta['hd_notif_target_type'];
        $targetPage = trim((string) $meta['hd_notif_target_page']);
        $requestUri = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '/';
        $currentPath = (string) wp_parse_url($requestUri, PHP_URL_PATH);
        $currentPath = '/' . ltrim($currentPath, '/');
        $currentPath = untrailingslashit($currentPath);
        $currentPath = $currentPath === '' ? '/' : $currentPath;

        if ($targetType === 'specific' && $targetPage !== '') {
            $lines = array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $targetPage)));
            $isMatch = false;
            foreach ($lines as $line) {
                if (is_numeric($line)) {
                    if (is_singular() && (int) get_queried_object_id() === (int) $line) { $isMatch = true; break; }
                } else {
                    $tp = '/' . ltrim((string) wp_parse_url($line, PHP_URL_PATH), '/');
                    $tp = untrailingslashit($tp);
                    $tp = $tp === '' ? '/' : $tp;
                    if ($currentPath === $tp || (untrailingslashit($currentPath) === $tp) || ($tp !== '/' && str_starts_with($currentPath . '/', $tp . '/'))) {
                        $isMatch = true; break;
                    }
                }
            }
            if (!$isMatch) return false;
        }

        if ($targetType === 'exclude' && $targetPage !== '') {
            $lines = array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $targetPage)));
            foreach ($lines as $line) {
                if (is_numeric($line)) {
                    if (is_singular() && (int) get_queried_object_id() === (int) $line) return false;
                } else {
                    $tp = '/' . ltrim((string) wp_parse_url($line, PHP_URL_PATH), '/');
                    $tp = untrailingslashit($tp);
                    $tp = $tp === '' ? '/' : $tp;
                    if ($currentPath === $tp || (untrailingslashit($currentPath) === $tp) || ($tp !== '/' && str_starts_with($currentPath . '/', $tp . '/'))) {
                        return false;
                    }
                }
            }
        }

        /*
         * تاریخ‌ها: سرور فقط نوتیفیکیشن *منقضی‌شده* را کنار می‌گذارد؛ شروع و
         * پایان دقیق در مرورگر بررسی می‌شود (data-start / data-end).
         *
         * صفحات با لایت‌اسپید کش می‌شوند. نسخه قبلی تاریخ را فقط اینجا بررسی
         * می‌کرد: پاپ‌آپ کمپینی که امروز تمام شده تا منقضی شدن کش صفحه
         * (ممکن است چند روز) نمایش داده می‌شد، و پاپ‌آپی که فردا شروع می‌شود
         * در صفحاتی که امروز کش شده‌اند اصلا وجود نداشت.
         */
        $today = current_time('Y-m-d');
        if (!empty($meta['hd_notif_end_date']) && $today > $meta['hd_notif_end_date']) return false;

        return true;
    }

    private function buildInlineStyles(array $meta): string
    {
        $styles = '';
        if ($meta['hd_notif_bg_transparent'] === '0') {
            $styles .= 'background-color: ' . esc_attr($meta['hd_notif_bg_color']) . '; ';
        } else {
            $styles .= 'background-color: transparent; border: none; box-shadow: none; ';
        }

        if ((int) $meta['hd_notif_width'] > 0) {
            $styles .= 'width: ' . esc_attr($meta['hd_notif_width']) . 'px; max-width: 95vw; ';
        }

        if ((int) $meta['hd_notif_height'] > 0) {
            $styles .= 'height: ' . esc_attr($meta['hd_notif_height']) . 'px; ';
        }

        return $styles;
    }

    /** مرز روز در منطقه زمانی سایت → میلی‌ثانیه UTC برای مقایسه در مرورگر. */
    private function dayBoundaryMs(string $date, bool $endOfDay): int
    {
        if ('' === $date) {
            return 0;
        }
        try {
            $dt = new DateTimeImmutable($date . ($endOfDay ? ' 23:59:59' : ' 00:00:00'), wp_timezone());
            return $dt->getTimestamp() * 1000;
        } catch (Exception $e) {
            return 0;
        }
    }

    private function renderView(int $postId, array $meta): void
    {
        if (empty($meta['hd_notif_bg_image'])) {
            return;
        }

        $inlineStyles = $this->buildInlineStyles($meta);
        $hasCloseBtn  = ($meta['hd_notif_close_btn'] !== '0');
        $position     = (string) $meta['hd_notif_position'];
        $isModal      = ('center' === $position);
        $title        = trim((string) ($meta['_title'] ?? '')) ?: 'اطلاعیه';
        $mainLink     = esc_url((string) $meta['hd_notif_main_link']);
        ?>
        <div id="hd-notif-wrapper-<?php echo (int) $postId; ?>" class="hd-notif-wrapper hd-wrap-<?php echo esc_attr($position); ?>">
            <?php
            /*
             * پاپ‌آپ وسط صفحه: dialog واقعی (صفحه‌خوان آن را پنجره می‌شناسد،
             * Escape می‌بندد، فوکوس داخلش می‌رود). نوارها و گوشه‌ها: ناحیه
             * غیرمسدودکننده.
             *
             * data-start / data-end / data-utm / data-referrer: قوانینی که به
             * بازدیدکننده یا زمان وابسته‌اند در مرورگر بررسی می‌شوند، چون صفحه
             * کش‌شده برای همه یکسان است. فیلدهای UTM و ارجاع‌دهنده قبلا ذخیره
             * می‌شدند ولی *هیچ‌جا* بررسی نمی‌شدند.
             */
            ?>
            <div id="hd-notification-<?php echo (int) $postId; ?>"
                 class="hd-notification-popup hd-pos-<?php echo esc_attr($position); ?>"
                 style="<?php echo esc_attr($inlineStyles); ?>"
                 role="<?php echo $isModal ? 'dialog' : 'region'; ?>"
                 <?php echo $isModal ? 'aria-modal="true"' : ''; ?>
                 aria-label="<?php echo esc_attr($title); ?>"
                 tabindex="-1"
                 data-id="<?php echo (int) $postId; ?>"
                 data-delay="<?php echo (int) $meta['hd_notif_delay'] * 1000; ?>"
                 data-auto-close="<?php echo (int) $meta['hd_notif_auto_close_time'] * 1000; ?>"
                 data-max-count="<?php echo (int) $meta['hd_notif_show_count']; ?>"
                 data-anim-in="<?php echo esc_attr((string) $meta['hd_notif_anim_in']); ?>"
                 data-anim-out="<?php echo esc_attr((string) $meta['hd_notif_anim_out']); ?>"
                 data-trigger-exit="<?php echo esc_attr((string) $meta['hd_notif_trigger_exit']); ?>"
                 data-trigger-scroll="<?php echo (int) $meta['hd_notif_trigger_scroll']; ?>"
                 data-trigger-inactivity="<?php echo (int) $meta['hd_notif_trigger_inactivity']; ?>"
                 data-position="<?php echo esc_attr($position); ?>"
                 data-device="<?php echo esc_attr((string) $meta['hd_notif_device']); ?>"
                 data-start="<?php echo $this->dayBoundaryMs((string) $meta['hd_notif_start_date'], false); ?>"
                 data-end="<?php echo $this->dayBoundaryMs((string) $meta['hd_notif_end_date'], true); ?>"
                 data-utm="<?php echo esc_attr(mb_strtolower(trim((string) $meta['hd_notif_target_utm']))); ?>"
                 data-referrer="<?php echo esc_attr(mb_strtolower(trim((string) $meta['hd_notif_target_referrer']))); ?>">

                <?php if ($hasCloseBtn) : ?>
                    <button type="button" class="hd-notif-close" aria-label="بستن" style="color: <?php echo esc_attr((string) $meta['hd_notif_close_color']); ?> !important;">&times;</button>
                <?php endif; ?>

                <div class="hd-notif-main-area">
                    <?php
                    /*
                     * تصویر با data-src: فقط وقتی پاپ‌آپ واقعا نمایش داده می‌شود
                     * بارگذاری می‌شود. مرورگرها <img src> را حتی داخل ظرف پنهان
                     * دانلود می‌کنند؛ نسخه قبلی تصویر را در *هر* بازدید صفحه
                     * دانلود می‌کرد — حتی وقتی پاپ‌آپ هرگز نمایش داده نمی‌شد.
                     * alt = عنوان نوتیفیکیشن (قبلا «Notification Image»).
                     */
                    ?>
                    <img data-src="<?php echo esc_url((string) $meta['hd_notif_bg_image']); ?>" alt="<?php echo esc_attr($title); ?>" decoding="async">
                    <?php if ('' !== $mainLink) : ?>
                        <a href="<?php echo $mainLink; ?>" class="hd-notif-half-link" aria-label="<?php echo esc_attr('مشاهده: ' . $title); ?>"></a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
    }
}

new HodimaNotificationFront();