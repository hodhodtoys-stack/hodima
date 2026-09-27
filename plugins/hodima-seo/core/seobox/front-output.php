<?php
declare(strict_types=1);
if (!defined('ABSPATH')) exit;

/**
 * عنوان صفحه.
 *
 * بازنویسی‌شده: نسخه قبلی برای آرشیوها و صفحات بدون شیء کوئری، از
 * get_the_ID() استفاده می‌کرد. وردپرس متغیر سراسری $post را روی اولین
 * نتیجه کوئری ست می‌کند، بنابراین آرشیو نویسنده/تاریخ/جستجو عنوان
 * «اولین نوشته لیست» را می‌گرفت. حالا هر حالت صریحا مدیریت می‌شود.
 */
function seobox_get_frontend_title() {

    static $final_title = null;
    if ($final_title !== null) return $final_title;

    $context   = seobox_get_query_context();
    $object_id = $context['object_id'];
    $is_term   = $context['is_term'];

    $meta_title = '';
    if ($is_term && $object_id) {
        $meta_title = (string) get_term_meta($object_id, '_seobox_title', true);
    } elseif ($context['is_singular'] && $object_id) {
        $meta_title = (string) get_post_meta($object_id, '_seobox_title', true);
    }

    $site_name  = (string) get_bloginfo('name');
    $pure_title = '';

    if (is_404()) {
        $pure_title = 'صفحه پیدا نشد';
    } elseif (is_search()) {
        $pure_title = 'نتایج جستجو برای: ' . get_search_query();
    } elseif (is_front_page()) {
        $pure_title = $site_name;
    } elseif (is_home()) {
        $blog_page_id = (int) get_option('page_for_posts');
        $pure_title   = $blog_page_id ? (string) get_the_title($blog_page_id) : $site_name;
    } elseif ($is_term) {
        $pure_title = (string) single_term_title('', false);
    } elseif (function_exists('is_shop') && is_shop()) {
        $shop_id    = function_exists('wc_get_page_id') ? (int) wc_get_page_id('shop') : 0;
        $pure_title = $shop_id > 0 ? (string) get_the_title($shop_id) : $site_name;
    } elseif (is_post_type_archive()) {
        $pure_title = (string) post_type_archive_title('', false);
    } elseif (is_author()) {
        $pure_title = (string) get_the_author_meta('display_name', (int) get_queried_object_id());
    } elseif (is_day() || is_month() || is_year()) {
        $pure_title = (string) get_the_archive_title();
    } elseif ($context['is_singular'] && $object_id) {
        $pure_title = (string) get_the_title($object_id);
    } else {
        $pure_title = $site_name;
    }

    // صفحه‌بندی را به عنوان اضافه کن تا عنوان صفحات ۲ به بعد تکراری نباشد
    $paged = (int) get_query_var('paged');
    if ($paged > 1) {
        $pure_title .= ' - صفحه ' . $paged;
    }

    $raw_title = $meta_title !== '' ? $meta_title : '%title% %sep% %sitename%';

    $title = str_replace(
        ['%title%', '%sitename%', '%sep%'],
        [$pure_title, $site_name, '-'],
        $raw_title
    );

    if (function_exists('seobox_parse_variables') && $object_id) {
        $title = seobox_parse_variables($title, (int) $object_id, $is_term ? 'term' : 'post');
    }

    $final_title = trim($title);
    return $final_title;
}

/**
 * تشخیص یک‌باره نوع صفحه.
 *
 * نکته کلیدی: object_id فقط وقتی پر می‌شود که واقعا شیء کوئری وجود
 * داشته باشد. هیچ‌وقت به get_the_ID() فالبک نمی‌کند، چون در آرشیوها
 * آن مقدار به اولین نوشته لوپ اشاره می‌کند نه به خود صفحه.
 */
function seobox_get_query_context(): array {

    static $context = null;
    if ($context !== null) return $context;

    $is_term     = is_category() || is_tag() || is_tax();
    $is_singular = is_singular();
    $object_id   = ($is_term || $is_singular) ? (int) get_queried_object_id() : 0;

    return $context = [
        'object_id'   => $object_id,
        'is_term'     => $is_term,
        'is_singular' => $is_singular,
    ];
}

add_filter('pre_get_document_title', 'seobox_get_frontend_title', 9999);

/*
 * متاتگ robots تکراری.
 * هسته وردپرس با فیلتر wp_robots یک <meta name="robots"> جدا با
 * max-image-preview:large چاپ می‌کرد، در حالی که سئوباکس همین دستور را در
 * متاتگ robots خودش دارد: دو متاتگ robots در هر صفحه. فقط همین یک فیلتر
 * برداشته می‌شود؛ بقیه حفاظ‌های هسته (مثل noindex نتایج جستجو) می‌مانند.
 */
remove_filter( 'wp_robots', 'wp_robots_max_image_preview_large' );

add_action('init', function() {
    remove_action('wp_head', '_wp_render_title_tag', 1);
    remove_action('wp_head', 'rel_canonical');
}, 9999);

/**
 * توضیحات جایگزین وقتی مدیر توضیحات دستی ننوشته.
 * ترتیب: توضیح کوتاه/خلاصه → متن محتوا → توضیح ترم → شعار سایت (فقط صفحه اصلی).
 */
function seobox_fallback_description( array $context ): string {

    $text = '';

    if ( $context['is_singular'] && $context['object_id'] ) {
        $post = get_post( (int) $context['object_id'] );
        // توضیحات خودکار از متن نوشته رمزدار، همان متن را بدون رمز در
        // متاتگ description و og:description منتشر می‌کرد.
        if ( $post instanceof WP_Post && ! post_password_required( $post ) ) {
            $text = '' !== trim( (string) $post->post_excerpt ) ? (string) $post->post_excerpt : (string) $post->post_content;
        }
    } elseif ( $context['is_term'] && $context['object_id'] ) {
        $term = get_term( (int) $context['object_id'] );
        if ( $term instanceof WP_Term ) {
            $text = (string) $term->description;
        }
    } elseif ( is_front_page() ) {
        $text = (string) get_bloginfo( 'description' );
    }

    $text = wp_strip_all_tags( strip_shortcodes( $text ) );
    $text = trim( (string) preg_replace( '/\s+/u', ' ', $text ) );

    if ( mb_strlen( $text ) > 160 ) {
        $cut  = mb_substr( $text, 0, 157 );
        $last = mb_strrpos( $cut, ' ' );
        $text = ( false !== $last && $last > 100 ? mb_substr( $cut, 0, $last ) : $cut ) . '…';
    }

    return $text;
}

/**
 * قیمت برای نمایش و برای Open Graph.
 *
 * برچسب نمایشی با واحد واقعی فروشگاه ساخته می‌شود. برای Open Graph کد
 * ISO 4217 لازم است؛ «IRT» کد رسمی نیست، پس تومان به ریال (×۱۰) و IRR
 * تبدیل می‌شود.
 *
 * @return array{label:string, amount:string, currency:string}
 */
function seobox_product_price_meta( string $price ): array {

    if ( '' === $price || ! is_numeric( $price ) || (float) $price <= 0 ) {
        return [ 'label' => '', 'amount' => '', 'currency' => '' ];
    }

    $currency = function_exists( 'get_woocommerce_currency' ) ? strtoupper( (string) get_woocommerce_currency() ) : 'IRR';
    $amount   = (float) $price;

    $names = [ 'IRR' => 'ریال', 'IRT' => 'تومان', 'IRHR' => 'هزار ریال', 'IRHT' => 'هزار تومان' ];
    $label = number_format( $amount ) . ' ' . ( $names[ $currency ] ?? $currency );

    // تبدیل به ریال برای کد ISO
    $to_rial = [ 'IRR' => 1, 'IRT' => 10, 'IRHR' => 1000, 'IRHT' => 10000 ];

    if ( isset( $to_rial[ $currency ] ) ) {
        return [
            'label'    => $label,
            'amount'   => (string) (int) round( $amount * $to_rial[ $currency ] ),
            'currency' => 'IRR',
        ];
    }

    return [ 'label' => $label, 'amount' => (string) $amount, 'currency' => $currency ];
}

/** نوع MIME ویدیو، یا رشته خالی اگر آدرس فایل مستقیم ویدیو نیست. */
function seobox_video_mime( string $url ): string {

    if ( '' === $url ) {
        return '';
    }

    $ext = strtolower( pathinfo( (string) wp_parse_url( $url, PHP_URL_PATH ), PATHINFO_EXTENSION ) );

    return [ 'mp4' => 'video/mp4', 'm4v' => 'video/mp4', 'webm' => 'video/webm', 'mov' => 'video/quicktime', 'ogv' => 'video/ogg' ][ $ext ] ?? '';
}

function seobox_output_front_meta() {

    if (is_admin()) return;

    $context     = seobox_get_query_context();
    $object_id   = $context['object_id'];
    $is_term     = $context['is_term'];
    $is_singular = $context['is_singular'];

    // post_id فقط برای صفحات تکی معنا دارد. نسخه قبلی اینجا به
    // get_the_ID() فالبک می‌کرد که در آرشیوها به اولین نوشته لوپ اشاره
    // می‌کند، و اگر آن هم خالی بود کل تابع return می‌شد — یعنی صفحه ۴۰۴
    // هیچ تگ <title> نداشت، چون _wp_render_title_tag هم حذف شده است.
    $post_id = ($is_singular && $object_id) ? $object_id : 0;

    $meta_source = [
        'desc'        => '',
        'robots_mode' => '',
        'canonical'   => '',
        'max_snippet' => '',
        'max_video'   => '',
        'max_image'   => '',
    ];

    $keys = [
        'desc'        => '_seobox_description',
        'robots_mode' => '_seobox_robots',
        'canonical'   => '_seobox_canonical',
        'max_snippet' => '_seobox_adv_snippet',
        'max_video'   => '_seobox_adv_video',
        'max_image'   => '_seobox_adv_image',
    ];

    if ($is_term && $object_id) {
        foreach ($keys as $slot => $meta_key) {
            $meta_source[$slot] = get_term_meta($object_id, $meta_key, true);
        }
    } elseif ($post_id) {
        foreach ($keys as $slot => $meta_key) {
            $meta_source[$slot] = get_post_meta($post_id, $meta_key, true);
        }
    }

    $site_name = (string) get_bloginfo('name');
    $locale    = get_locale();
    $title     = seobox_get_frontend_title();

    $desc = !empty($meta_source['desc']) ? (string) $meta_source['desc'] : '';
    if ($desc !== '' && $object_id) {
        $desc = seobox_parse_variables($desc, (int) $object_id, $is_term ? 'term' : 'post');
    }

    /*
     * فالبک توضیحات.
     * نسخه قبلی فقط وقتی متاتگ description و og:description چاپ می‌کرد که
     * مدیر دستی توضیحات نوشته بود. یعنی بیشتر محصولات و دسته‌ها هیچ
     * توضیحی نداشتند و پیش‌نمایش لینک در تلگرام و واتساپ فقط عنوان بود.
     */
    if ($desc === '') {
        $desc = seobox_fallback_description($context);
    }

    /* ── دستورات ربات ────────────────────────────────────────────────
     * نسخه قبلی اگر _seobox_robots یک آرایه خالی بود، خروجی
     * <meta name="robots" content=""> تولید می‌کرد. حالا آرایه خالی هم
     * مثل «تنظیم‌نشده» رفتار می‌کند و به پیش‌فرض برمی‌گردد.
     * صفحات بدون کنونیکال پایدار (۴۰۴ و جستجو) صریحا noindex می‌شوند. */
    $robots_flags = seobox_normalize_robots($meta_source['robots_mode'] ?? []);

    if (is_404() || is_search()) {
        $robots_flags['index'] = false;
    }

    $robots_mode = [
        $robots_flags['index'] ? 'index' : 'noindex',
        $robots_flags['follow'] ? 'follow' : 'nofollow',
    ];

    /*
     * دستورات پیش‌نمایش.
     *
     * نسخه قبلی فقط مقادیری را چاپ می‌کرد که در متاباکس ذخیره شده بودند؛
     * برای صفحه‌ای که هرگز در سئوباکس ذخیره نشده بود، هیچ دستوری نبود و
     * گوگل پیش‌نمایش تصویر را «استاندارد» (کوچک) در نظر می‌گرفت —
     * در حالی که Google Discover تصویر بزرگ را الزامی می‌داند.
     *
     * پیش‌فرض‌ها همان مقادیر توصیه‌شده گوگل‌اند و مقادیر ذخیره‌شده دوباره
     * اعتبارسنجی می‌شوند، چون مستقیم در متاتگ قرار می‌گیرند.
     */
    if ($robots_flags['index']) {

        $snippet = (string) ($meta_source['max_snippet'] ?? '');
        $video   = (string) ($meta_source['max_video'] ?? '');
        $image   = (string) ($meta_source['max_image'] ?? '');

        $robots_mode[] = 'max-snippet:' . ((is_numeric($snippet) && (int) $snippet >= 0) ? (int) $snippet : -1);
        $robots_mode[] = 'max-video-preview:' . ((is_numeric($video) && (int) $video >= 0) ? (int) $video : -1);
        $robots_mode[] = 'max-image-preview:' . (in_array($image, ['none', 'standard'], true) ? $image : 'large');
    }

    $robots_content = implode(', ', array_unique($robots_mode));

    /* ── کنونیکال ────────────────────────────────────────────────────
     * از موتور مشترک inc/helpers.php می‌آید تا دقیقا همان آدرسی باشد
     * که schema/ در فیلد @id می‌نویسد. صفحه‌بندی هم در آن لحاظ شده،
     * پس صفحه دوم آرشیو دیگر به صفحه اول کنونیکال نمی‌شود. */
    $canonical = function_exists('hodima_get_canonical_url') ? hodima_get_canonical_url() : '';

    if ($canonical === '' && !empty($meta_source['canonical'])) {
        $canonical = (string) $meta_source['canonical'];
    }

    $og_url  = $canonical;
    $og_type = 'website';
    if ($is_singular && $post_id) {
        $og_type = (get_post_type($post_id) === 'product') ? 'product' : 'article';
    }

    $img_url = ''; $img_width = ''; $img_height = ''; $img_type = ''; $img_alt = '';
    
    if ($post_id && has_post_thumbnail($post_id)) {
        $image_id = get_post_thumbnail_id($post_id);
        $img_src  = wp_get_attachment_image_src($image_id, 'full');
        if ($img_src) {
            $img_url    = $img_src[0];
            $img_width  = $img_src[1];
            $img_height = $img_src[2];
            $img_type   = get_post_mime_type($image_id);
            $img_alt    = get_post_meta($image_id, '_wp_attachment_image_alt', true) ?: $title;
        }
    }
    
    // فالبک ۱: لوگوی مرکزی اسکیما (پنل «اسکیما ← صفحه اصلی»).
    // این همان تصویری است که قبلا به صورت ثابت در header.php چاپ می‌شد؛
    // حالا فقط زمانی استفاده می‌شود که صفحه تصویر شاخص نداشته باشد،
    // بنابراین تصویر واقعی محصول/مقاله دیگر override نمی‌شود.
    if (!$img_url) {
        $schema_logo = (string) get_option('hodima_schema_homepage_logo', '');
        if ($schema_logo !== '') {
            $img_url  = $schema_logo;
            $logo_id  = attachment_url_to_postid($schema_logo);
            if ($logo_id) {
                $logo_src = wp_get_attachment_image_src($logo_id, 'full');
                if ($logo_src) {
                    $img_width  = $logo_src[1];
                    $img_height = $logo_src[2];
                    $img_type   = get_post_mime_type($logo_id);
                }
            }
        }
    }

    if (!$img_url) {
        $icon_id = get_option('site_icon');
        if ($icon_id) {
            $img_src = wp_get_attachment_image_src($icon_id, 'full');
            if ($img_src) {
                $img_url    = $img_src[0];
                $img_width  = $img_src[1];
                $img_height = $img_src[2];
                $img_type   = get_post_mime_type($icon_id);
            }
        }
    }

    $updated_time = '';
    $published_time = '';
    if ($post_id) {
        $raw_modified = get_post_field('post_modified', $post_id);
        $raw_published = get_post_field('post_date', $post_id);
        if ($raw_modified) {
            $dt = new DateTime($raw_modified, wp_timezone());
            $updated_time = $dt->format('c');
        }
        if ($raw_published) {
            $dt_pub = new DateTime($raw_published, wp_timezone());
            $published_time = $dt_pub->format('c');
        }
    }

    $social_desc = $desc;
    $price = ''; $stock_status = ''; $is_instock = false;
    $price_label = ''; $og_amount = ''; $og_currency = '';

    if ($og_type === 'product' && $post_id && function_exists('wc_get_product')) {

        $product = wc_get_product($post_id);

        if ($product instanceof WC_Product) {

            $price        = (string) $product->get_price();
            $is_instock   = $product->is_in_stock();
            $stock_status = $is_instock ? 'موجود' : 'ناموجود';

            $prices = seobox_product_price_meta($price);
            $price_label = $prices['label'];
            $og_amount   = $prices['amount'];
            $og_currency = $prices['currency'];

            if ($price_label !== '') {
                $status_icon = $is_instock ? '✅' : '❌';
                $social_desc = "[$status_icon $stock_status | $price_label] - " . $social_desc;
            }
        }
    }

    $video_url = '';
    if ($post_id) {
        if (function_exists('hook_get_media_data')) {
            $media_data = hook_get_media_data($post_id, 'post');
            if (!empty($media_data['video_url'])) {
                $video_url = $media_data['video_url'];
            }
        }
        if (empty($video_url)) {
            $video_url = get_post_meta($post_id, 'video_url', true);
        }
    }

    /*
     * og:video فقط برای فایل مستقیم ویدیو.
     * نسخه قبلی برای هر آدرسی (از جمله صفحه آپارات یا یوتیوب) نوع را
     * video/mp4 اعلام می‌کرد؛ پلتفرم‌ها سعی می‌کردند یک صفحه HTML را به
     * عنوان فایل ویدیو پخش کنند و پیش‌نمایش کلا خراب می‌شد.
     */
    $video_mime = seobox_video_mime((string) $video_url);
    if ($video_mime === '') {
        $video_url = '';
    }

    echo "\n<!-- SEOBOX UNIVERSAL META -->\n";
    
    /*
     * <link rel="preload" as="image"> حذف شد.
     * نسخه قبلی تصویر شاخص را در اندازه *کامل* (full) با بالاترین اولویت
     * روی هر صفحه پیش‌بارگذاری می‌کرد — حتی وقتی صفحه آن اندازه را اصلا
     * نمایش نمی‌داد (گالری محصول اندازه دیگری دارد) یا تصویر فالبک لوگو
     * بود. این با منابع واقعی LCP رقابت می‌کرد و روی موبایل چند مگابایت
     * هدر می‌داد. لایت‌اسپید با گزینه Viewport Images تصویر واقعی LCP را
     * تشخیص و پیش‌بارگذاری می‌کند.
     */

    echo '<title>' . esc_html($title) . '</title>' . "\n";
    if ($desc !== '') echo '<meta name="description" content="' . esc_attr($desc) . '">' . "\n";
    echo '<meta name="robots" content="' . esc_attr($robots_content) . '">' . "\n";
    if ($canonical !== '') echo '<link rel="canonical" href="' . esc_url($canonical) . '">' . "\n";
    echo '<meta name="theme-color" content="#25316a">' . "\n"; 

    // تگ‌های itemprop حذف شدند: بدون itemscope میکرودیتای نامعتبرند و
    // همه این اطلاعات در JSON-LD ماژول schema/ وجود دارد.

    echo '<meta property="og:locale" content="' . esc_attr($locale) . '">' . "\n";
    echo '<meta property="og:type" content="' . esc_attr($og_type) . '">' . "\n";
    echo '<meta property="og:title" content="' . esc_attr($title) . '">' . "\n";
    if ($social_desc !== '') echo '<meta property="og:description" content="' . esc_attr($social_desc) . '">' . "\n";
    if ($og_url !== '') echo '<meta property="og:url" content="' . esc_url($og_url) . '">' . "\n";
    echo '<meta property="og:site_name" content="' . esc_attr($site_name) . '">' . "\n";
    if ($updated_time !== '') echo '<meta property="og:updated_time" content="' . esc_attr($updated_time) . '">' . "\n";

    if ($img_url) {
        echo '<meta property="og:image" content="' . esc_url($img_url) . '">' . "\n";
        if (strpos($img_url, 'https://') === 0) {
            echo '<meta property="og:image:secure_url" content="' . esc_url($img_url) . '">' . "\n";
        }
        if ($img_width)  echo '<meta property="og:image:width" content="' . esc_attr((string)$img_width) . '">' . "\n";
        if ($img_height) echo '<meta property="og:image:height" content="' . esc_attr((string)$img_height) . '">' . "\n";
        if ($img_alt)    echo '<meta property="og:image:alt" content="' . esc_attr($img_alt) . '">' . "\n";
        if ($img_type)   echo '<meta property="og:image:type" content="' . esc_attr($img_type) . '">' . "\n";
    }

    if ($video_url) {
        echo '<meta property="og:video" content="' . esc_url($video_url) . '">' . "\n";
        if (strpos($video_url, 'https://') === 0) {
            echo '<meta property="og:video:secure_url" content="' . esc_url($video_url) . '">' . "\n";
        }
        echo '<meta property="og:video:type" content="' . esc_attr($video_mime) . '">' . "\n";
    }

    if ($og_type === 'article' && $post_id) {
        if ($published_time !== '') echo '<meta property="article:published_time" content="' . esc_attr($published_time) . '">' . "\n";
        if ($updated_time !== '') echo '<meta property="article:modified_time" content="' . esc_attr($updated_time) . '">' . "\n";
        
        $author_id = get_post_field('post_author', $post_id);
        $author_name = get_the_author_meta('display_name', $author_id);
        if ($author_name) {
            echo '<meta property="article:author" content="' . esc_attr($author_name) . '">' . "\n";
        }
        
        $categories = get_the_category($post_id);
        if (!empty($categories)) {
            echo '<meta property="article:section" content="' . esc_attr($categories[0]->name) . '">' . "\n";
        }
    }

    if ($og_type === 'product' && $og_amount !== '') {
        echo '<meta property="product:price:amount" content="' . esc_attr($og_amount) . '">' . "\n";
        echo '<meta property="product:price:currency" content="' . esc_attr($og_currency) . '">' . "\n";
        echo '<meta property="product:availability" content="' . esc_attr($is_instock ? 'instock' : 'outofstock') . '">' . "\n";
    }

    /*
     * کارت «player» توییتر یک صفحه HTML قابل‌جاسازی روی HTTPS می‌خواهد و
     * باید توسط توییتر تأیید شود؛ نسخه قبلی آدرس فایل ویدیو را می‌داد و
     * کارت نامعتبر می‌شد. summary_large_image همیشه کار می‌کند.
     */
    echo '<meta name="twitter:card" content="summary_large_image">' . "\n";
    echo '<meta name="twitter:title" content="' . esc_attr($title) . '">' . "\n";
    if ($social_desc !== '') echo '<meta name="twitter:description" content="' . esc_attr($social_desc) . '">' . "\n";
    if ($img_url) echo '<meta name="twitter:image" content="' . esc_url($img_url) . '">' . "\n";

    if ($og_type === 'product' && $price_label !== '') {
        echo '<meta name="twitter:label1" content="قیمت">' . "\n";
        echo '<meta name="twitter:data1" content="' . esc_attr($price_label) . '">' . "\n";
        echo '<meta name="twitter:label2" content="دسترسی">' . "\n";
        echo '<meta name="twitter:data2" content="' . esc_attr($stock_status) . '">' . "\n";
    }

    echo "<!-- /SEOBOX UNIVERSAL META -->\n";
}
add_action('wp_head', 'seobox_output_front_meta', 1);

/* ============================================================
 * هدر X-Robots-Tag
 * ------------------------------------------------------------
 * قبلا روی فیلتر wp_headers ثبت شده بود. آن فیلتر داخل
 * WP::send_headers() اجرا می‌شود که *قبل از* query_posts() صدا زده
 * می‌شود؛ در آن لحظه get_queried_object_id() هنوز صفر است و
 * is_singular()/is_category() مقدار ندارند. بنابراین شرط‌ها همیشه
 * رد می‌شدند و این هدر هرگز ارسال نمی‌شد.
 *
 * template_redirect اولین هوکی است که هم کوئری کامل اجرا شده و هم
 * هنوز هیچ خروجی ارسال نشده، پس header() معتبر است.
 * ============================================================ */
add_action('template_redirect', 'seobox_send_x_robots_tag', 20);

function seobox_send_x_robots_tag(): void {

    if (is_admin() || headers_sent()) {
        return;
    }

    $context   = seobox_get_query_context();
    $object_id = $context['object_id'];

    $raw = [];

    if ($context['is_term'] && $object_id) {
        $raw = get_term_meta($object_id, '_seobox_robots', true);
    } elseif ($context['is_singular'] && $object_id) {
        $raw = get_post_meta($object_id, '_seobox_robots', true);
    }

    // همان نرمال‌ساز متاتگ؛ ردیف‌های قدیمی رشته‌ای هم پشتیبانی می‌شوند
    $flags      = seobox_normalize_robots($raw);
    $directives = [];

    if (! $flags['index'])  $directives[] = 'noindex';
    if (! $flags['follow']) $directives[] = 'nofollow';

    if (empty($directives)) {
        return;
    }

    header('X-Robots-Tag: ' . implode(', ', $directives), true);
}
