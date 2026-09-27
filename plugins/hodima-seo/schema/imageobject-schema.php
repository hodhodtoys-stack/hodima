<?php
/**
 * HOOK IMAGE OBJECT SCHEMA - HYBRID MASTER EDITION (Dynamic Targeting & Highly Optimized)
 */
if (!defined('ABSPATH')) exit;

add_action('wp_head', 'hook_generate_imageobject_schema', 1);

function hook_generate_imageobject_schema() {
    if (is_admin()) return;

    if (get_option('hodima_schema_image_enable') !== '1') return;

    $targets        = get_option('hodima_schema_image_targets', ['product', 'post', 'product_cat', 'category']);
    if (empty($targets)) return;

    $opt_credit     = sanitize_text_field(get_option('hodima_schema_image_credit', 'عکس متعلق به ' . get_bloginfo('name') . ' است.'));
    $opt_license    = esc_url(get_option('hodima_schema_image_license', trailingslashit(home_url()) . 'terms/'));
    $opt_max_imgs   = (int)get_option('hodima_schema_image_max_count', 4);
    $opt_def_width  = get_option('hodima_schema_image_def_width', '500'); 
    $opt_def_height = get_option('hodima_schema_image_def_height', '500');

    $page_name      = '';
    $page_url       = '';
    $image_objects  = []; 
    $category_product_thumbs = [];
    // 🛠️ باگ رفع‌شده: wp_date('Y') روی این سایت از مبدل تقویم شمسی وردپرس
    // عبور می‌کند (طبق کشف تأییدشده در category/product-schema-pro.php) و
    // به‌جای سال میلادی («۲۰۲۶») سال شمسی با اعداد فارسی («۱۴۰۵») برمی‌گرداند
    // — که در متن copyrightNotice هر تصویر سایت چاپ می‌شد. gmdate با افست
    // ساعت سایت، امن و همیشه میلادی است.
    $current_year   = gmdate( 'Y', time() + (int) ( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS ) );
    $site_url       = trailingslashit(home_url());
    $site_name      = get_bloginfo('name');
    $process_allowed= false;

    if ( is_singular() ) {
        $current_post_type = get_post_type();
        if ( in_array($current_post_type, $targets) ) {
            $process_allowed = true;
            $post_id   = get_the_ID();
            $page_name = get_the_title($post_id);
            $page_url  = get_permalink($post_id);
            // تصاویر داخل متن نوشته رمزدار نباید در اسکیما فهرست شوند
            $content   = post_password_required( $post_id ) ? '' : get_post_field('post_content', $post_id);
            $thumb_id  = get_post_thumbnail_id($post_id);
        }
    } 
    elseif ( is_tax() || is_category() || is_tag() ) {
        $term = get_queried_object();
        if ( $term && in_array($term->taxonomy, $targets) ) {
            $process_allowed = true;
            $page_name = single_term_title('', false);
            $page_url  = get_term_link($term);
            $thumb_id  = get_term_meta($term->term_id, 'thumbnail_id', true) ?: get_term_meta($term->term_id, 'product_cat_thumbnail_id', true);
            $content   = get_term_meta($term->term_id, 'hook_content', true) . ' ' . term_description($term->term_id);

            $transient_key = 'hook_cat_imgs_' . $term->term_id;
            $cached_thumbs = get_transient($transient_key);

            if ( false === $cached_thumbs ) {
                $post_type_target = ($term->taxonomy === 'product_cat') ? 'product' : 'post';
                $cat_posts = get_posts([
                    'post_type'              => $post_type_target,
                    'posts_per_page'         => 4,
                    'tax_query'              => [
                        [
                            'taxonomy' => $term->taxonomy,
                            'field'    => 'term_id',
                            'terms'    => $term->term_id,
                        ],
                    ],
                    'fields'                 => 'ids',
                    'no_found_rows'          => true,
                    'update_post_meta_cache' => false,
                    'update_post_term_cache' => false,
                ]);

                $cached_thumbs = [];
                foreach ($cat_posts as $pid) {
                    $pid_thumb = get_post_thumbnail_id($pid);
                    if ($pid_thumb) $cached_thumbs[] = $pid_thumb;
                }
                set_transient($transient_key, $cached_thumbs, 12 * HOUR_IN_SECONDS);
            }
            $category_product_thumbs = $cached_thumbs;
        }
    }

    if ( ! $process_allowed ) return;

    /*
     * نویسنده و سازنده تصویر — بازگشت به شکل اصلی که بدون خطا کار می‌کرد.
     *
     * دو تلاش قبلی برای اتصال این فیلد به «#organization» گراف اصلی هر دو
     * خطای «Invalid object type for field creator» در Image Metadata دادند.
     * علت: گوگل نودهای هم‌شناسه را ادغام می‌کند و نود اصلی سازمان از نوع
     * WholesaleStore است؛ اعتبارسنج Image Metadata آن را برای creator
     * نمی‌پذیرد، هرچند در schema.org زیرنوع Organization است.
     *
     * پس عمدا *بدون* @id: یک Organization درون‌خطی مستقل. به @id اضافه نکنید.
     */
    $org_name = get_option( 'hodima_schema_homepage_org_name' ) ?: $site_name;

    $org_ref = [
        '@type' => 'Organization',
        'name'  => (string) $org_name,
        'url'   => $site_url,
    ];

    $add_schema = function($url, $width, $height, $alt) use (&$image_objects, &$page_name, &$page_url, $current_year, $site_url, $site_name, $opt_credit, $opt_license, $org_ref) {
        $url = esc_url_raw($url);
        if (strpos($url, 'http') !== 0 && strpos($url, '//') !== 0) {
            $url = $site_url . ltrim($url, '/');
        }
        if (empty($url) || isset($image_objects[$url])) return;
        
        $alt = sanitize_text_field($alt) ?: $page_name . " - " . $site_name;
        
        $image_objects[$url] = [
            "@context"           => "https://schema.org",
            "@type"              => "ImageObject",
            "url"                => $url,
            "contentUrl"         => $url,
            "width"              => (string)absint($width),
            "height"             => (string)absint($height),
            "caption"            => $alt,
            "name"               => $page_name,
            "author"             => $org_ref,
            "creator"            => $org_ref,
            "creditText"         => $opt_credit,
            "copyrightNotice"    => "© " . $current_year . " " . $site_name . ". استفاده بدون کسب اجازه ممنوع است.",
            "license"            => $opt_license, 
            "acquireLicensePage" => $page_url
        ];
    };

    $process_id = function($img_id) use ($add_schema) {
        if (!$img_id) return;
        $data = wp_get_attachment_image_src($img_id, 'full');
        if ($data) {
            $alt = get_post_meta($img_id, '_wp_attachment_image_alt', true);
            $add_schema($data[0], $data[1], $data[2], $alt);
        }
    };

    $process_html = function($html) use ($add_schema, $opt_max_imgs, $opt_def_width, $opt_def_height) {
        if (empty($html)) return;
        
        $html = wp_specialchars_decode($html);
        if (function_exists('mb_convert_encoding')) {
            $html = mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8');
        }
        
        $dom = new DOMDocument();
        @$dom->loadHTML('<?xml encoding="utf-8" ?>' . $html, LIBXML_NOERROR | LIBXML_NOWARNING);
        $images = $dom->getElementsByTagName('img');
        
        $count = 0;
        foreach ($images as $img) {
            if ($count >= $opt_max_imgs) break;
            
            // 🛠️ رفع‌شده: قبلاً فقط data-src و src بررسی می‌شد. خیلی از
            // افزونه‌ها/قالب‌های lazy-load از نام‌های دیگری استفاده می‌کنند
            // (data-lazy-src، data-original، data-echo) و تصویرشان کلاً از
            // این استخراج جا می‌ماند.
            $src = $img->getAttribute('data-src')
                ?: $img->getAttribute('data-lazy-src')
                ?: $img->getAttribute('data-original')
                ?: $img->getAttribute('data-echo')
                ?: $img->getAttribute('src');
            if (empty($src) || strpos($src, 'data:image') === 0) continue;
            
            $alt = $img->getAttribute('alt');
            $width = $img->getAttribute('width');
            $height = $img->getAttribute('height');

            if (empty($width) || empty($height)) {
                $attachment_id = attachment_url_to_postid($src);
                if ($attachment_id) {
                    $img_data = wp_get_attachment_image_src($attachment_id, 'full');
                    if ($img_data) {
                        $width  = $img_data[1];
                        $height = $img_data[2];
                    }
                }
            }
            
            $width  = !empty($width) ? $width : $opt_def_width;
            $height = !empty($height) ? $height : $opt_def_height;

            $add_schema($src, $width, $height, $alt);
            $count++;
        }
    };

    if (isset($thumb_id)) $process_id($thumb_id);

    if (!empty($category_product_thumbs)) {
        foreach ($category_product_thumbs as $c_thumb) {
            $process_id($c_thumb);
        }
    }

    if ( isset($post_id) && is_singular('product') ) {
        if ($gallery = get_post_meta($post_id, '_product_image_gallery', true)) {
            foreach (explode(',', $gallery) as $gid) $process_id($gid);
        }
    }
    
    if (isset($content)) $process_html($content);

    if (!empty($image_objects)) {
        $final_schemas = array_values($image_objects);

        // 🛠️ برگردانده شد: در اصلاح دور قبل، اینجا یک سقف کلی روی خروجی
        // نهایی اعمال شده بود (فکر می‌کردم چون توضیح پنل ادمین می‌گفت این
        // عدد «سقف کلی» تصاویر از محتوا+گالری+دسته‌بندی است). ولی طبق تست
        // واقعی شما در Rich Results گوگل، این باعث شد تعداد ImageObject
        // صفحه دسته‌بندی از ۹ به ۴ (مقدار پیش‌فرض همین تنظیم) سقوط کند —
        // یعنی تصاویر واقعی گالری/دسته‌بندی که قبلاً درست چاپ می‌شدند، حذف
        // شدند. آن تغییر اشتباه بود؛ رفتار قبلی (سقف فقط روی اسکن HTML
        // محتوا، نه روی تصویر شاخص/گالری/دسته‌بندی) برگردانده شد.
        echo "\n\n<!-- HOOK ImageObject Schema (Hybrid Dynamic Extractor - Optimized) | creator-fix-v3 -->\n";
        echo '<script type="application/ld+json" id="hook-imageobject-schema">' . wp_json_encode($final_schemas, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) . "</script>\n";
        echo "\n";
    }
}