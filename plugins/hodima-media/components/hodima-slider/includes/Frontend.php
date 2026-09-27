<?php
/**
 * Hodima Slider — front end
 * Version: 3.0.0
 */
declare(strict_types=1);

namespace Hodima\Slider;

if (!defined('ABSPATH')) exit;

final class Frontend {

    /** نشانه جایگاه تصویر اول هر اسلایدر در HTML کش‌شده (زمان رندر جایگزین می‌شود). */
    private const PRIO_TOKEN = ' data-h-prio="1"';

    /** تصویر شفاف ۱×۱ برای src اسلایدهای بعدی (HTML معتبر، بدون درخواست شبکه). */
    private const BLANK = 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';

    private static bool $priority_used = false;

    public static function init(): void {
        add_shortcode('hodima-slider1', [self::class, 'render']);
        add_shortcode('hodima-slider2', [self::class, 'render']);
        add_shortcode('hodima-slider3', [self::class, 'render']);

        add_action('wp_enqueue_scripts', [self::class, 'maybe_enqueue_early']);
        add_action('wp_head', [self::class, 'inject_hero_preload'], 1);
    }

    /* =================================================================
     * دارایی‌ها
     * ================================================================= */

    /**
     * CSS در head.
     * نسخه قبلی فقط هنگام اجرای شورت‌کد صف می‌کرد، یعنی CSS در فوتر چاپ
     * می‌شد: بنر بالای صفحه اول بدون استایل رندر می‌شد (همه اسلایدها زیر
     * هم با اندازه کامل) و بعد جمع می‌شد — بزرگ‌ترین جابه‌جایی ممکن (CLS)
     * روی بزرگ‌ترین عنصر صفحه.
     */
    public static function maybe_enqueue_early(): void {

        $has = is_front_page() || is_home();

        if (!$has && is_singular()) {
            $content = (string) get_post_field('post_content', (int) get_queried_object_id());
            foreach (['hodima-slider1', 'hodima-slider2', 'hodima-slider3'] as $tag) {
                if (has_shortcode($content, $tag)) { $has = true; break; }
            }
        }

        if ($has || apply_filters('hodima_slider_enqueue_everywhere', false)) {
            self::enqueue_assets();
        }
    }

    private static function enqueue_assets(): void {
        static $enqueued = false;
        if ($enqueued) return;

        wp_enqueue_style('hodima-slider-front', Core::asset_url('front.css'), [], Core::asset_version('front.css'));
        wp_enqueue_script('hodima-slider-front', Core::asset_url('front.js'), [], Core::asset_version('front.js'), true);
        wp_localize_script('hodima-slider-front', 'HodimaSliderFront', [
            'pause' => __('توقف اسلایدر', 'hodima'),
            'play'  => __('پخش اسلایدر', 'hodima'),
        ]);

        $enqueued = true;
    }

    /* =================================================================
     * preload تصویر LCP
     * ================================================================= */

    /**
     * فقط اسلایدر بالای صفحه (پیش‌فرض اسلایدر ۱).
     * نسخه قبلی اسلایدر ۳ را هم preload می‌کرد — اسلایدری که معمولا پایین
     * صفحه است و دانلودش با تصویر LCP رقابت می‌کرد.
     *     add_filter( 'hodima_slider_hero', fn() => 's3' );   // اگر بنر بالا اسلایدر ۳ است
     */
    public static function inject_hero_preload(): void {

        if (!is_front_page() && !is_home()) return;

        $hero   = (string) apply_filters('hodima_slider_hero', 's1');
        $option = ['s1' => Core::OPTION_S1, 's3' => Core::OPTION_S3][$hero] ?? '';
        $items  = $option ? Helpers::get_items($option) : [];

        if (empty($items)) return;

        $img_id = absint($items[0]['image_id'] ?? 0);
        $mob_id = absint($items[0]['mobile_image_id'] ?? 0);
        if (!$img_id) return;

        $tag = static function (int $id, string $media = ''): string {
            $src    = wp_get_attachment_image_url($id, 'hodima-slider-1920');
            $srcset = wp_get_attachment_image_srcset($id, 'hodima-slider-1920');
            if (!$src) return '';
            return sprintf(
                '<link rel="preload" as="image" href="%s"%s%s fetchpriority="high">' . "\n",
                esc_url($src),
                $srcset ? ' imagesrcset="' . esc_attr($srcset) . '" imagesizes="100vw"' : '',
                $media ? ' media="' . esc_attr($media) . '"' : ''
            );
        };

        echo $mob_id
            ? $tag($mob_id, '(max-width: 768px)') . $tag($img_id, '(min-width: 769px)')
            : $tag($img_id);
    }

    /* =================================================================
     * رندر
     * ================================================================= */

    public static function render(array|string $atts = [], ?string $content = null, string $tag = ''): string {

        self::enqueue_assets();

        $html = match ($tag) {
            'hodima-slider2' => self::render_slider2(),
            'hodima-slider3' => self::render_single(Core::OPTION_S3, 's3', 'h-main'),
            default          => self::render_single(Core::OPTION_S1, 's1', 'h-main'),
        };

        return self::apply_priority($html);
    }

    /**
     * اولویت بارگذاری در زمان رندر، نه در کش.
     *
     * نسخه قبلی fetchpriority="high" را روی اولین اسلاید *هر* اسلایدر
     * می‌گذاشت: ۱ + ۴ ستون اسلایدر ۲ + اسلایدر ۳ = شش تصویر «اولویت بالا»
     * که با هم رقابت می‌کردند و اولویت عملا بی‌معنا می‌شد. حالا فقط اولین
     * اسلایدری که در صفحه چاپ می‌شود اولویت بالا می‌گیرد؛ بقیه lazy.
     */
    private static function apply_priority(string $html): string {

        if ('' === $html || !str_contains($html, self::PRIO_TOKEN)) {
            return $html;
        }

        $is_first_slider = !self::$priority_used;
        self::$priority_used = true;
        $first_image = true;

        return (string) preg_replace_callback('/' . preg_quote(self::PRIO_TOKEN, '/') . '/', static function () use ($is_first_slider, &$first_image): string {
            if ($is_first_slider && $first_image) {
                $first_image = false;
                return ' fetchpriority="high" loading="eager"';
            }
            return $is_first_slider ? ' loading="eager"' : ' loading="lazy"';
        }, $html);
    }

    private static function render_single(string $option, string $cacheKey, string $class): string {

        $items = Helpers::get_items($option);
        if (empty($items)) return '';

        $transient_key = Core::transient_key($cacheKey);
        $html = get_transient($transient_key);

        if (!is_string($html) || '' === $html) {
            $settings = get_option(Core::OPTION_SETTINGS, []);
            $s = (array) ($settings[$cacheKey] ?? []);
            $effect = SliderEffect::sanitize((string) ($s['effect'] ?? 'fade'));

            /*
             * اسلایدر ۱: ۵ اسلاید اول بدون تنبل‌بار (نمایش فوری هنگام چرخش).
             *     add_filter( 'hodima_slider_eager_count', fn() => 3 );
             */
            $eager = 's1' === $cacheKey ? max(1, (int) apply_filters('hodima_slider_eager_count', 5)) : 1;
            $html  = self::build_slider_html($items, "{$class} hodima-slider-{$cacheKey} h-effect-{$effect}", true, $s, '100vw', $eager);
            set_transient($transient_key, $html, max(1, absint($settings['cache_time'] ?? 12)) * HOUR_IN_SECONDS);
        }

        return $html;
    }

    private static function render_slider2(): string {

        $groups = [
            'a' => Helpers::get_items(Core::OPTION_S2A),
            'b' => Helpers::get_items(Core::OPTION_S2B),
            'c' => Helpers::get_items(Core::OPTION_S2C),
            'd' => Helpers::get_items(Core::OPTION_S2D),
        ];

        if (!array_filter($groups)) return '';

        $transient_key = Core::transient_key('s2');
        $html = get_transient($transient_key);

        if (!is_string($html) || '' === $html) {
            $mobile   = (array) get_option(Core::OPTION_S2_MOBILE, ['a' => 1, 'b' => 1, 'c' => 1, 'd' => 1]);
            $settings = get_option(Core::OPTION_SETTINGS, []);
            $s        = (array) ($settings['s2'] ?? []);
            $effect   = SliderEffect::sanitize((string) ($s['effect'] ?? 'flip'), SliderEffect::Flip);

            $html = '<div class="h-slider2">';
            foreach ($groups as $key => $items) {
                if (empty($items)) continue;
                $class = "h-s2-col h-effect-{$effect} hodima-slider-s2-comp" . (empty($mobile[$key]) ? ' h-hide-mobile' : '');
                $html .= self::build_slider_html($items, $class, false, $s, '(max-width: 768px) 50vw, 25vw');
            }
            $html .= '</div>';

            set_transient($transient_key, $html, max(1, absint($settings['cache_time'] ?? 12)) * HOUR_IN_SECONDS);
        }

        return $html;
    }

    /** srcset و ابعاد یک تصویر. */
    private static function image_data(int $id, string $size): array {
        $src = wp_get_attachment_image_src($id, $size);
        return [
            'src'    => $src ? (string) $src[0] : Helpers::get_placeholder(),
            'w'      => $src ? (int) $src[1] : 0,
            'h'      => $src ? (int) $src[2] : 0,
            'srcset' => (string) (wp_get_attachment_image_srcset($id, $size) ?: ''),
        ];
    }

    private static function build_slider_html(array $items, string $class, bool $showDots, array $settings, string $sizes, int $eager = 1): string {

        $size = str_contains($class, 'h-s2-col') ? 'hodima-slider2-main' : 'hodima-slider-1920';

        /*
         * اندازه سفارشی به صورت متغیر CSS در همین HTML.
         * نسخه قبلی این متغیرها را با JS (بعد از DOMContentLoaded) تنظیم می‌کرد:
         * بنر اول با اندازه پیش‌فرض رندر می‌شد و بعد از اجرای اسکریپت می‌پرید
         * (CLS)؛ با Delay JS لایت‌اسپید تا اولین تعامل کاربر.
         */
        $vars = [];
        foreach (['w' => '--h-w', 'h' => '--h-h', 'w_mobile' => '--h-w-mobile', 'h_mobile' => '--h-h-mobile'] as $k => $var) {
            $v = absint($settings[$k] ?? 0);
            if ($v > 0) $vars[] = "{$var}:{$v}px";
        }
        $classes = trim($class . ($vars ? ' h-has-custom-size' : ''));
        $count   = count($items);

        ob_start(); ?>
        <div class="h-slider <?php echo esc_attr($classes); ?>" role="region" aria-roledescription="اسلایدر" aria-label="<?php esc_attr_e('اسلایدر', 'hodima'); ?>"<?php echo $vars ? ' style="' . esc_attr(implode(';', $vars)) . '"' : ''; ?>>
            <div class="h-slides-wrapper">
                <?php foreach (array_values($items) as $i => $item):
                    $dur    = max(2000, min(20000, absint($item['duration'] ?? Core::DEFAULT_DUR) * 1000));
                    $img_id = absint($item['image_id']);
                    $mob_id = absint($item['mobile_image_id'] ?? 0);
                    $title  = (string) ($item['title'] ?? '');

                    /*
                     * متن جایگزین: alt خود تصویر ← عنوان اسلاید ← متن عمومی.
                     * عنوان اسلاید جای درست و مجازش همین‌جاست؛ نسخه قبلی آن را
                     * در یک <h2> پنهان با CSS می‌گذاشت (پایین را ببینید).
                     */
                    $alt = (string) (get_post_meta($img_id, '_wp_attachment_image_alt', true) ?: $title ?: __('تصویر اسلایدر', 'hodima'));

                    $desk  = self::image_data($img_id, $size);
                    $mob   = $mob_id ? self::image_data($mob_id, $size) : null;
                    $first = (0 === $i);

                    /*
                     * $early: اسلایدهای ۲ تا $eager (پیش‌فرض ۵ در اسلایدر ۱) با src
                     * واقعی و بدون تنبل‌بار، ولی fetchpriority="low": بلافاصله
                     * دانلود می‌شوند ولی *بعد از* منابع حیاتی، تا با تصویر اول (LCP،
                     * fetchpriority="high") رقابت نکنند. اسلایدهای بعدی data-src.
                     */
                    $early = !$first && $i < $eager;

                    /*
                     * اسلایدهای بعدی: data-src / data-srcset و JS درست پیش از
                     * نمایش بارگذاری می‌کند. همه اسلایدها با opacity:0 روی هم و
                     * *داخل* دیدند، پس loading="lazy" جلوی دانلودشان را نمی‌گرفت و
                     * همه بنرهای ۱۹۲۰ پیکسلی همزمان با تصویر LCP دانلود می‌شدند.
                     */
                    $real       = $first || $early;
                    $srcAttr    = $real ? 'src' : 'data-src';
                    $srcsetAttr = $real ? 'srcset' : 'data-srcset';
                    $prioAttr   = $first ? self::PRIO_TOKEN : ($early ? ' fetchpriority="low"' : '');
                    ?>
                    <div class="h-slide<?php echo $first ? ' active' : ''; ?>" data-dur="<?php echo (int) $dur; ?>" role="group" aria-roledescription="اسلاید" aria-label="<?php echo esc_attr(sprintf('%d از %d', $i + 1, $count)); ?>"<?php echo $first ? '' : ' aria-hidden="true"'; ?>>
                        <?php if (!empty($item['link_url'])): ?>
                            <a href="<?php echo esc_url($item['link_url']); ?>"<?php echo $first ? '' : ' tabindex="-1"'; ?>>
                        <?php endif; ?>

                        <?php if ($mob): ?>
                            <picture>
                                <source media="(max-width: 768px)" <?php echo $srcsetAttr; ?>="<?php echo esc_attr($mob['srcset'] ?: $mob['src']); ?>" sizes="<?php echo esc_attr($sizes); ?>"<?php echo $mob['w'] ? ' width="' . $mob['w'] . '" height="' . $mob['h'] . '"' : ''; ?>>
                                <img <?php echo $real ? '' : 'src="' . self::BLANK . '" '; ?><?php echo $srcAttr; ?>="<?php echo esc_url($desk['src']); ?>"<?php echo $desk['srcset'] ? ' ' . $srcsetAttr . '="' . esc_attr($desk['srcset']) . '" sizes="' . esc_attr($sizes) . '"' : ''; ?><?php echo $desk['w'] ? ' width="' . $desk['w'] . '" height="' . $desk['h'] . '"' : ''; ?> alt="<?php echo esc_attr($alt); ?>" decoding="async"<?php echo $prioAttr; ?>>
                            </picture>
                        <?php else: ?>
                            <img <?php echo $real ? '' : 'src="' . self::BLANK . '" '; ?><?php echo $srcAttr; ?>="<?php echo esc_url($desk['src']); ?>"<?php echo $desk['srcset'] ? ' ' . $srcsetAttr . '="' . esc_attr($desk['srcset']) . '" sizes="' . esc_attr($sizes) . '"' : ''; ?><?php echo $desk['w'] ? ' width="' . $desk['w'] . '" height="' . $desk['h'] . '"' : ''; ?> alt="<?php echo esc_attr($alt); ?>" decoding="async"<?php echo $prioAttr; ?>>
                        <?php endif; ?>

                        <?php if (!empty($item['link_url'])): ?></a><?php endif; ?>

                        <?php
                        /*
                         * بلوک «h-seo-content» حذف شد.
                         * نسخه قبلی عنوان (h2)، توضیح و متن دکمه هر اسلاید را با
                         * CSS از دید بازدیدکننده پنهان می‌کرد («مخصوص سئو»). متنی که
                         * برای موتور جستجو نوشته و از کاربر پنهان شود، طبق سیاست
                         * اسپم گوگل «متن پنهان» است و می‌تواند به جریمه دستی صفحه
                         * اصلی برسد؛ چند h2 نامرئی هم ساختار سرتیترها را شلوغ می‌کرد.
                         */
                        ?>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php if ($count > 1): ?>
                <div class="h-controls">
                    <?php
                    /*
                     * دکمه توقف/پخش حذف شد؛ توقف با قرار گرفتن موس روی اسلایدر،
                     * فوکوس کیبورد، خارج شدن از دید و پنهان شدن تب انجام می‌شود،
                     * و جابه‌جایی با کشیدن موس یا سوایپ لمسی (front.js).
                     */
                    if ($showDots): ?>
                        <div class="h-dots">
                            <?php for ($i = 0; $i < $count; $i++): ?>
                                <button type="button" class="h-dot<?php echo 0 === $i ? ' active' : ''; ?>" aria-label="<?php echo esc_attr(sprintf(__('رفتن به اسلاید %d', 'hodima'), $i + 1)); ?>" aria-current="<?php echo 0 === $i ? 'true' : 'false'; ?>" data-index="<?php echo (int) $i; ?>"></button>
                            <?php endfor; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
        return (string) ob_get_clean();
    }
}
