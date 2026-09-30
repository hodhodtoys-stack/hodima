<?php
/**
 * HOOK PRODUCT SCHEMA PRO - Single Source of Truth Updated
 * Path: wp-content/plugins/hodima-seo/schema/product-schema-pro.php
 */

if (!defined('ABSPATH')) exit;

if (get_option('hodima_schema_product_enable', '1') === '1') {
    add_filter( 'woocommerce_structured_data_product', '__return_empty_array' );
    add_action('wp_head', 'hook_generate_custom_product_schema', 99);
}

function hook_generate_custom_product_schema() {
    if ( ! is_singular('product') && ! (function_exists('is_product') && is_product()) ) return;

    $_product = wc_get_product( get_the_ID() );
    if ( ! $_product ) return;

    $product_id = $_product->get_id();
    $site_name  = get_bloginfo('name');

    $opt_brand         = get_option('hodima_schema_product_brand', 'هدهدلی');
    // فروشنده خالی = نام سازمان (همان نام #organization)؛ قبلا پیش‌فرض کد
    // «بازرگانی هدهد» و پیش‌فرض پنل «بازرگانی هدیما» بود.
    $opt_seller        = get_option('hodima_schema_product_seller') ?: ( function_exists( 'hodima_seo_schema_org_name' ) ? hodima_seo_schema_org_name() : get_bloginfo( 'name' ) );
    // تصویر پیش‌فرض دیگر گزینه‌ی جداگانه‌ای در این ماژول نیست؛ طبق درخواست شما،
    // تنها منبع لوگو/تصویر مرکزی همان تنظیمات صفحه اصلی (Homepage) است.
    // باگ رفع‌شده: فالبک به دامنه اشتباه (hodima.com) اشاره می‌کرد.
    $opt_def_img       = function_exists( 'hodima_seo_schema_logo_url' ) ? hodima_seo_schema_logo_url() : ( get_option('hodima_schema_homepage_logo') ?: trailingslashit( home_url() ) . 'wp-content/uploads/2025/06/logo2.png' );
    $opt_desc_tpl      = get_option('hodima_schema_product_desc_tpl', 'خرید عمده [product_name] با بهترین قیمت از [site_name].');
    $opt_return_days   = (int)get_option('hodima_schema_product_return_days', 7);
    $opt_shipping_cost = get_option('hodima_schema_product_shipping_cost', '0');
    $opt_h_min         = (int)get_option('hodima_schema_product_handling_min', 1);
    $opt_h_max         = (int)get_option('hodima_schema_product_handling_max', 2);
    $opt_t_min         = (int)get_option('hodima_schema_product_transit_min', 1);
    $opt_t_max         = (int)get_option('hodima_schema_product_transit_max', 4);

    $name = $_product->get_name();
    $url = get_permalink( $product_id );
    $sku = $_product->get_sku() ?: 'HOOK-' . $product_id;

    $replace_vars = ['[product_name]' => $name, '[site_name]' => $site_name];
    $final_desc_tpl   = strtr($opt_desc_tpl, $replace_vars);

    $media_data = function_exists('hook_get_media_data') ? hook_get_media_data($product_id, 'post') : array();

    // =========================================================================
    // ۱. استخراج مشخصات از کلاس جدول
    $specs_text = '';
    $additional_properties = [];

    if ( class_exists( 'Hodima_Product_Specs_Table' ) ) {
        $specs_table_instance = Hodima_Product_Specs_Table::get_instance();
        $prepared_data = $specs_table_instance->_prepare_specs_data( $_product );
        
        if ( ! empty( $prepared_data['schema_properties'] ) ) {
            $additional_properties = $prepared_data['schema_properties'];
        }

        if ( ! empty( $prepared_data['specs_data'] ) ) {
            $specs_parts = [];
            foreach ( $prepared_data['specs_data'] as $spec ) {
                $specs_parts[] = $spec['label'] . ': ' . wp_strip_all_tags( $spec['value'] );
            }
            if ( ! empty( $specs_parts ) ) {
                $specs_text = implode( ' | ', $specs_parts );
            }
        }
    }

    // ۲. تولید فیلد Description نهایی
    $raw_short_desc = trim( wp_strip_all_tags( strip_shortcodes( $_product->get_short_description() ) ) );

    if ( ! empty( $raw_short_desc ) ) {
        $clean_description = wp_trim_words( $raw_short_desc, 40 );
        if ( ! empty( $specs_text ) ) {
            $clean_description .= ' - ویژگی‌ها: ' . $specs_text;
        }
    } else {
        if ( ! empty( $specs_text ) ) {
            $clean_description = 'ویژگی‌ها: ' . $specs_text;
        } else {
            $clean_description = $final_desc_tpl;
        }
    }
    // =========================================================================

    $image_id = $_product->get_image_id();
    $image_url = $image_id ? wp_get_attachment_url( $image_id ) : $opt_def_img;

    /*
     * موجودی از همان تابعی که قالب صفحه استفاده می‌کند
     * (inc/woocommerce/product-page.php). نسخه قبلی فقط is_in_stock()
     * ووکامرس را می‌خواند و فیلد «وضعیت موجودی» را نادیده می‌گرفت: روی
     * سایت محصولی با «موجود در انبار چین» در اسکیما InStock و ارسال ۱ تا ۴
     * روزه بود — ناهمخوانی availability با صفحه در Merchant Listings.
     */
    $stock_status   = function_exists( 'hodima_product_schema_availability' )
        ? hodima_product_schema_availability( $_product )
        : ( $_product->is_in_stock() ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock' );
    $stock_location = function_exists( 'hodima_product_stock_location' ) ? hodima_product_stock_location( $_product ) : '';
    $offer_url      = function_exists( 'hodima_product_schema_page_url' ) ? hodima_product_schema_page_url( $url ) : $url;
    // باگ واقعی و تأییدشده (دقیقاً همان چیزی که ابزار Rich Results گوگل
    // با خطای «Date/time not in ISO 8601 format in field priceValidUntil»
    // گزارش داد): wp_date() روی این سایت از تبدیل‌کننده‌ی تقویم شمسی وردپرس
    // عبور می‌کند (طبق کامنت خود پروژه در sitemap-core.php)، پس خروجی‌اش
    // یک تاریخ شمسی با اعداد فارسی بود، نه ISO 8601 میلادی استاندارد.
    // DateTime بومی PHP + wp_timezone() هم تایم‌زون سایت را رعایت می‌کند و
    // هم هرگز از آن لایه‌ی locale عبور نمی‌کند.
    try {
        $dt = new DateTime( '@' . strtotime( '+1 year' ) );
        $dt->setTimezone( wp_timezone() );
        $valid_until = $dt->format( 'Y-m-d' );
    } catch ( Exception $e ) {
        $valid_until = gmdate( 'Y-m-d', strtotime( '+1 year' ) );
    }
    /*
     * validFrom = آخرین تغییر محصول (قیمت از آن لحظه معتبر است).
     * نسخه قبلی «امروز» در هر رندر بود — تاریخی که هر روز عوض می‌شد و
     * هیچ اطلاعاتی نداشت.
     */
    $modified   = $_product->get_date_modified();
    $valid_from = $modified ? $modified->date( 'Y-m-d' ) : current_time( 'Y-m-d' );
    
    /*
     * فروشنده: سازمان درون‌خطی (بدون @id). ارجاع به #organization عمدا نیست:
     * نوع آن WholesaleStore است و اعتبارسنج گوگل همین را برای creator تصویر
     * نپذیرفت (imageobject-schema.php)؛ نام و آدرس همان سازمان است.
     */
    $seller_info = ['@type' => 'Organization', 'name' => $opt_seller, 'url' => trailingslashit( home_url() )];

    /*
     * سیاست مرجوعی از پنل (قبلا «مرجوعی رایگان، پستی» در کد ثابت بود).
     * مهلت ۰ روز = مرجوعی پذیرفته نمی‌شود؛ قبلا «۰ روز مهلت» چاپ می‌شد که
     * نامعتبر است.
     */
    if ( $opt_return_days > 0 ) {
        $merchant_return = [
            '@type' => 'MerchantReturnPolicy',
            'applicableCountry' => 'IR',
            'returnPolicyCategory' => 'https://schema.org/MerchantReturnFiniteReturnWindow',
            'merchantReturnDays' => $opt_return_days,
        ];

        $return_method = match ( get_option( 'hodima_schema_product_return_method', 'mail' ) ) {
            'store' => 'https://schema.org/ReturnInStore',
            'none'  => '',
            default => 'https://schema.org/ReturnByMail',
        };
        if ( '' !== $return_method ) {
            $merchant_return['returnMethod'] = $return_method;
        }

        $merchant_return['returnFees'] = 'free' === get_option( 'hodima_schema_product_return_fees', 'customer' )
            ? 'https://schema.org/FreeReturn'
            : 'https://schema.org/ReturnFeesCustomerResponsibility';
    } else {
        $merchant_return = [
            '@type' => 'MerchantReturnPolicy',
            'applicableCountry' => 'IR',
            'returnPolicyCategory' => 'https://schema.org/MerchantReturnNotPermitted',
        ];
    }

    $shipping_details = [
        '@type' => 'OfferShippingDetails',
        'shippingDestination' => [
            '@type' => 'DefinedRegion',
            'addressCountry' => 'IR'
        ],
    ];

    /*
     * هزینه ارسال. باگ قبلی: پیش‌فرض «0» بود یعنی به گوگل «ارسال رایگان»
     * اعلام می‌شد، در حالی که هزینه با مشتری و بسته به حجم بار است.
     *   متغیر (پیش‌فرض): مبلغی اعلام نمی‌شود، یا فقط سقف (maxValue)
     *   ثابت: همان مبلغ پنل (0 = رایگان)
     */
    if ( 'fixed' === get_option( 'hodima_schema_product_shipping_mode', 'customer' ) ) {
        $shipping_details['shippingRate'] = [
            '@type'    => 'MonetaryAmount',
            'value'    => (string) $opt_shipping_cost,
            'currency' => 'IRR',
        ];
    } elseif ( ( $shipping_max = (int) get_option( 'hodima_schema_product_shipping_max', 0 ) ) > 0 ) {
        $shipping_details['shippingRate'] = [
            '@type'    => 'MonetaryAmount',
            'maxValue' => $shipping_max,
            'currency' => 'IRR',
        ];
    }

    /*
     * زمان تحویل فقط برای کالای انبار ایران.
     * زمان‌های تنظیم‌شده (مثلا ۱ تا ۴ روز) برای کالای انبار چین درست نیستند.
     * ادعای زمان تحویل نادرست بدتر از نبودنش است (deliveryTime توصیه‌شده
     * است نه الزامی). برای کالای چین با زمان واقعی:
     *   add_filter( 'hodima_product_delivery_days', fn( $d, $loc ) => 'china' === $loc
     *       ? [ 'handling' => [ 2, 5 ], 'transit' => [ 20, 35 ] ] : $d, 10, 2 );
     */
    /*
     * انبار چین زمان جداگانه خودش را دارد (پنل اسکیما ← محصولات). قبلا
     * deliveryTime برای این محصولات حذف می‌شد (چون زمان انبار ایران درست
     * نبود) و Merchant Listings هشدار Missing field "deliveryTime" می‌داد.
     */
    $delivery_default = 'china' === $stock_location
        ? [
            'handling' => [ (int) get_option( 'hodima_schema_product_china_handling_min', 2 ), (int) get_option( 'hodima_schema_product_china_handling_max', 5 ) ],
            'transit'  => [ (int) get_option( 'hodima_schema_product_china_transit_min', 15 ), (int) get_option( 'hodima_schema_product_china_transit_max', 30 ) ],
          ]
        : [ 'handling' => [ $opt_h_min, $opt_h_max ], 'transit' => [ $opt_t_min, $opt_t_max ] ];

    $delivery = apply_filters( 'hodima_product_delivery_days', $delivery_default, $stock_location, $_product );

    if ( is_array( $delivery ) && isset( $delivery['handling'], $delivery['transit'] ) ) {
        $shipping_details['deliveryTime'] = [
            '@type'        => 'ShippingDeliveryTime',
            'handlingTime' => [ '@type' => 'QuantitativeValue', 'minValue' => (int) $delivery['handling'][0], 'maxValue' => (int) $delivery['handling'][1], 'unitCode' => 'd' ],
            'transitTime'  => [ '@type' => 'QuantitativeValue', 'minValue' => (int) $delivery['transit'][0], 'maxValue' => (int) $delivery['transit'][1], 'unitCode' => 'd' ],
        ];
    }

    $currency = get_woocommerce_currency();
    $is_irt = ($currency === 'IRT');
    if ($is_irt) $currency = 'IRR';

    if ( $_product->is_type( 'variable' ) ) {
        $prices = $_product->get_variation_prices( true );

        // نکته مهم: آرایه get_variation_prices بر اساس ترتیب واریانت‌ها است، نه مقدار قیمت.
        // current()/end() اولین/آخرین عضو آرایه را می‌دهند که لزوماً کمترین/بیشترین قیمت نیست.
        // برای صحت داده باید از min()/max() واقعی استفاده شود.
        $min_price = ! empty( $prices['price'] ) ? min( $prices['price'] ) : 0;
        $max_price = ! empty( $prices['price'] ) ? max( $prices['price'] ) : 0;

        if ( $is_irt ) {
            // باگ رفع‌شده: ضرب مستقیم فلوت در ۱۰ می‌تواند نویز اعشاری تولید
            // کند (مثلاً 123456.70000000001) که وارد JSON-LD می‌شد و در
            // اعتبارسنج‌های schema.org به‌عنوان قیمت نامعتبر/عجیب گزارش می‌شد.
            $min_price = round( (float) $min_price * 10, 2 );
            $max_price = round( (float) $max_price * 10, 2 );
        }
        // بدون قیمت معتبر → بدون Offer (نه lowPrice: 0)
        $has_price = $max_price > 0;

        $offers = [
            '@type'                   => 'AggregateOffer',
            'url'                     => $offer_url,
            'priceCurrency'           => $currency,
            'lowPrice'                => $min_price ?: '0',
            'highPrice'               => $max_price ?: '0',
            'offerCount'              => count( $prices['price'] ) ?: 1,
            'priceValidUntil'         => $valid_until, // <-- افزوده شد؛ قبلاً فقط در Offer ساده وجود داشت
            'validFrom'               => $valid_from, // <-- اضافه شدن برای محصولات متغیر
            'availability'            => $stock_status,
            'seller'                  => $seller_info,
            'hasMerchantReturnPolicy' => $merchant_return,
            'shippingDetails'         => $shipping_details
        ];

        if ( ! $has_price ) {
            $offers = null;
        }
    } else {
        $price = $_product->get_price();
        if ( $is_irt && '' !== (string) $price ) $price = round( (float) $price * 10, 2 );
        $offers = [
            '@type'                   => 'Offer',
            'url'                     => $offer_url,
            'priceCurrency'           => $currency,
            'price'                   => $price ?: '0',
            'priceValidUntil'         => $valid_until,
            'validFrom'               => $valid_from, // <-- اضافه شدن برای محصولات ساده
            'availability'            => $stock_status,
            'itemCondition'           => 'https://schema.org/NewCondition',
            'seller'                  => $seller_info,
            'hasMerchantReturnPolicy' => $merchant_return,
            'shippingDetails'         => $shipping_details
        ];

        // حداقل سفارش (eligibleTransactionVolume) عمدا در اسکیما نیست؛
        // Offer فقط قیمت محصول را اعلام می‌کند.

        /*
         * بدون قیمت → بدون Offer.
         * نسخه قبلی "price": "0" می‌گذاشت که گوگل آن را «رایگان» می‌خواند.
         * محصول همچنان با امتیاز/نظرات واجد Product snippet می‌ماند.
         */
        if ( '' === (string) $_product->get_price() || (float) $_product->get_price() <= 0 ) {
            $offers = null;
        }
    }

    /*
     * هویت محصول در گراف.
     *
     * نسخه قبلی نود Product را بدون @id چاپ می‌کرد — یک موجودیت شناور و
     * جدا از صفحه‌ای که روی آن است. حالا شناسه دارد و به نود صفحه
     * (ItemPage «#webpage» از homepage-schema.php) وصل است؛ آن نود هم از
     * طریق فیلتر پایین mainEntity اش را به همین محصول ارجاع می‌دهد.
     */
    $page_url = hodima_product_schema_page_url( $url );

    $schema = [
        '@context'         => 'https://schema.org/',
        '@type'            => 'Product',
        '@id'              => $page_url . '#product',
        'url'              => $page_url,
        'mainEntityOfPage' => [ '@id' => $page_url . '#webpage' ],
        'name'             => $name,
        'image'       => $image_url,
        'description' => $clean_description,
        'sku'         => $sku,
        // mpn (کد قطعه سازنده) حذف شد: همیشه برابر SKU خود فروشگاه گذاشته
        // می‌شد که داده ساختگی است. بارکد واقعی (gtin) در مرحله بعد.
        'brand'       => ['@type' => 'Brand', 'name'  => $opt_brand],
        'offers'      => $offers
    ];

    if ( null === $offers ) {
        unset( $schema['offers'] );
    }

    /*
     * کشور سازنده (فیلد محصول، آخرین ردیف جدول مشخصات). کد ISO کشور؛
     * همان اطلاعاتی که در جدول «کشور سازنده» نمایش داده می‌شود.
     */
    $origin_text = '';
    foreach ( [ 'تولید', 'pa_tolid', 'pa_country-of-origin', 'pa_origin', 'pa_country', 'pa_made-in', 'کشور سازنده', 'کشور', 'ساخت', 'ساخت کشور', 'مبدا' ] as $attr ) {
        $origin_text = trim( (string) $_product->get_attribute( $attr ) );
        if ( '' !== $origin_text ) {
            break;
        }
    }
    if ( '' === $origin_text ) {
        $origin_text = (string) get_post_meta( $product_id, '_hodima_country_of_origin', true );
    }

    // متن ویژگی یا کد فیلد → کد ISO (همان منابع ردیف «کشور سازنده» جدول)
    $origin_norm = mb_strtolower( str_replace( [ 'ی', 'ي' ], '', $origin_text ) );
    $origin = match ( true ) {
        in_array( $origin_norm, [ 'cn', 'چن', 'china' ], true ), str_contains( $origin_norm, 'چین' ) => 'CN',
        in_array( $origin_norm, [ 'ir', 'اران', 'iran' ], true ), str_contains( $origin_norm, 'ایران' ) => 'IR',
        default => '',
    };
    if ( '' !== $origin ) {
        $schema['countryOfOrigin'] = [ '@type' => 'Country', 'name' => $origin ];
    }

    /*
     * «سئو مدرن» برای محصولات خاموش است (hook_modern_seo_enabled در
     * media-system/media-helpers.php):
     *   - خلاصه هوش مصنوعی به انتهای description اضافه می‌شد؛ همان متن
     *     روی صفحه هم بود و توضیح اسکیما یک رشته الحاقی طولانی می‌شد.
     *   - عنوان Discover به عنوان alternateName (پایین) و موجودیت‌ها به عنوان
     *     مشخصه «مرتبط با» — هر دو کاربرد نادرست آن ویژگی‌ها.
     */
    $modern_seo = function_exists( 'hook_modern_seo_enabled' ) && hook_modern_seo_enabled( 'post', (int) $product_id );

    if ( $modern_seo && ! empty( $media_data['ai_summary'] ) ) {
        $schema['description'] .= ' | ' . wp_strip_all_tags( $media_data['ai_summary'] );
    }

    if ( $modern_seo && ! empty( $media_data['key_entities'] ) ) {
        $entities = explode( ',', $media_data['key_entities'] );
        foreach ( $entities as $entity ) {
            $entity = trim( $entity );
            if ( ! empty( $entity ) ) {
                // تبدیل about به property های استاندارد محصول
                $additional_properties[] = [
                    '@type' => 'PropertyValue',
                    'name'  => 'مرتبط با',
                    'value' => sanitize_text_field( $entity )
                ];
            }
        }
    }

    /*
     * نقطه اتصال مشترک additionalProperty.
     *
     * hodima-woo-table مقادیر پایه را از ویژگی‌های ووکامرس ساخته (بالاتر)
     * و مرجع است. ماژول‌های دیگر — مثل جدول دستی hodima-table — با
     * اولویت بالاتر به این فیلتر وصل می‌شوند و فقط نام‌هایی را اضافه
     * می‌کنند که قبلا پر نشده باشد.
     *
     * هر ماژول جدیدی از این به بعد فقط به این فیلتر وصل می‌شود و نیازی
     * به تغییر این فایل نیست.
     */
    $additional_properties = (array) apply_filters(
        'hodima_product_additional_properties',
        $additional_properties,
        (int) $product_id,
        $_product
    );

    if ( ! empty( $additional_properties ) ) {

        // پاکسازی نهایی: نام‌های تکراری حذف و سقف اعمال می‌شود.
        // گوگل فهرست‌های خیلی بلند را نادیده می‌گیرد و مقادیر متناقض
        // زیر یک نام، سیگنال کیفیت پایین است.
        $seen  = [];
        $clean = [];

        // نام متغیرها عمدا $prop_name / $prop_value است. نسخه قبلی از
        // $name استفاده می‌کرد و نام *محصول* را بازنویسی می‌کرد؛ توضیح ویدیو
        // (پایین‌تر) نام آخرین مشخصه جدول را می‌گرفت: «کیفیت آسیب به مو».
        foreach ( $additional_properties as $property ) {

            $prop_name  = isset( $property['name'] ) ? trim( wp_strip_all_tags( (string) $property['name'] ) ) : '';
            $prop_value = isset( $property['value'] ) ? trim( wp_strip_all_tags( (string) $property['value'] ) ) : '';

            if ( '' === $prop_name || '' === $prop_value ) {
                continue;
            }

            $key = mb_strtolower( preg_replace( '/\s+/u', ' ', $prop_name ) );

            if ( isset( $seen[ $key ] ) ) {
                continue;
            }

            $seen[ $key ] = true;

            $clean[] = [
                '@type' => 'PropertyValue',
                'name'  => $prop_name,
                'value' => $prop_value,
            ];

            if ( count( $clean ) >= 25 ) {
                break;
            }
        }

        if ( ! empty( $clean ) ) {
            $schema['additionalProperty'] = $clean;
        }
    }

    if ( ! empty( $media_data['video_url'] ) ) {
        $video_thumb = ! empty( $media_data['video_thumb'] ) ? $media_data['video_thumb'] : $image_url;
        $video_title = ! empty( $media_data['video_title'] ) ? sanitize_text_field( $media_data['video_title'] ) : 'فیلم معرفی ' . $name;
        
        // همان دو باگ رفع‌شده‌ی category-schema-pro.php: date('c') تایم‌زون
        // سرور را به‌جای تایم‌زون سایت به کار می‌برد، و strtotime() نامعتبر
        // بی‌سروصدا تاریخ را به ۱۹۷۰ سقوط می‌داد.
        $video_ts    = ! empty( $media_data['video_date'] ) ? strtotime( $media_data['video_date'] ) : false;
        // باگ رفع‌شده: بدون تاریخ معتبر «یک ماه پیش از امروز» گذاشته می‌شد که
        // هر روز عوض می‌شد؛ حالا تاریخ انتشار محصول (ارقام فارسی هم خوانده می‌شوند).
        if ( ! $video_ts && function_exists( 'hodima_seo_schema_post_video_date' ) ) {
            $video_ts = strtotime( hodima_seo_schema_post_video_date( $media_data['video_date'] ?? '', (int) $product_id ) );
        }
        // همان باگ Jalali/locale که در category-schema-pro.php رفع شد —
        // اینجا هم wp_date() جای خودش را به DateTime بومی PHP + wp_timezone()
        // می‌دهد تا لایه‌ی تقویم شمسی وردپرس این سایت اصلاً درگیر نشود.
        $upload_date = null;
        try {
            $ts = $video_ts ?: strtotime( '-1 month' );
            $dt = new DateTime( '@' . $ts );
            $dt->setTimezone( wp_timezone() );
            $upload_date = $dt->format( 'c' );
        } catch ( Exception $e ) {
            $upload_date = gmdate( 'c', $video_ts ?: strtotime( '-1 month' ) );
        }

        /*
         * ویدیو حالا یک نود مستقل سطح‌بالا با شناسه است و محصول با subjectOf
         * به آن ارجاع می‌دهد.
         *
         * قبلا کل VideoObject *داخل* Product بود. ابزار گوگل ویدیو را از هر
         * عمقی بیرون می‌کشد و «Videos» نشان می‌داد، ولی validator.schema.org
         * فقط موجودیت‌های سطح‌بالا را فهرست می‌کند — ویدیو آنجا دیده نمی‌شد
         * مگر با باز کردن Product. بدون @id هم ویدیو موجودیت مستقلی در گراف
         * نبود.
         */
        $video_raw = trim( (string) $media_data['video_url'] );
        $video_raw = esc_url_raw( (string) preg_replace( '/\s+/', '%20', $video_raw ) );

        $video_node = [
            '@type'            => 'VideoObject',
            '@id'              => $page_url . '#video',
            'name'             => $video_title,
            'description'      => 'بررسی و نمایش کیفیت ' . $name . ' توسط تیم ' . $opt_seller,
            /*
             * esc_url_raw به جای esc_url.
             * esc_url برای خروجی HTML است و «&» را به «&#038;» تبدیل می‌کند؛
             * داخل JSON یعنی آدرس خراب برای هر ویدیو یا تصویری که رشته کوئری
             * دارد (مثلا امبد آپارات).
             */
            'thumbnailUrl'     => [ esc_url_raw( (string) $video_thumb ) ],
            'uploadDate'       => $upload_date,
            'isPartOf'         => [ '@id' => $page_url . '#webpage' ],
            'mainEntityOfPage' => [ '@id' => $page_url . '#webpage' ],
            'about'            => [ '@id' => $page_url . '#product' ],
        ];

        /*
         * contentUrl یعنی خود فایل ویدیو؛ صفحه آپارات یا یوتیوب embedUrl است.
         * نسخه قبلی برای هر آدرسی contentUrl می‌گذاشت. تشخیص با همان تابعی که
         * media-schema.php برای بقیه صفحات استفاده می‌کند.
         */
        $is_file = function_exists( 'hook_is_direct_video_file' )
            ? (bool) hook_is_direct_video_file( $video_raw )
            : (bool) preg_match( '/\.(mp4|m4v|webm|mov|ogv)(\?|$)/i', (string) wp_parse_url( $video_raw, PHP_URL_PATH ) );

        $video_node[ $is_file ? 'contentUrl' : 'embedUrl' ] = $video_raw;

        if ( ! empty( $media_data['video_duration'] ) && function_exists( 'hook_format_duration_iso' ) ) {
            $duration = hook_format_duration_iso( $media_data['video_duration'] );
            if ( '' !== $duration ) {
                $video_node['duration'] = $duration;
            }
        }

        $schema['subjectOf'] = [ '@id' => $video_node['@id'] ];
    }

    if ( $modern_seo && ! empty( $media_data['discover_title'] ) ) {
        $schema['alternateName'] = sanitize_text_field( $media_data['discover_title'] );
    }

    if ( $_product->get_review_count() > 0 ) {
        $schema['aggregateRating'] = [
            '@type'       => 'AggregateRating',
            'ratingValue' => $_product->get_average_rating(),
            'reviewCount' => $_product->get_review_count(),
            'bestRating'  => '5',
            'worstRating' => '1'
        ];
    }

    unset( $schema['@context'] );

    $graph = [ $schema ];
    if ( isset( $video_node ) ) {
        $graph[] = $video_node;
    }

    hodima_schema_add( [ '@graph' => $graph ], 'hodima-seo: product-schema-pro' );
}

/**
 * آدرس پایه شناسه‌ها — دقیقا همانی که homepage-schema.php برای «#webpage»
 * استفاده می‌کند، تا ارجاع‌ها به یک موجودیت برسند.
 */
function hodima_product_schema_page_url( string $fallback = '' ): string {
    $canonical = function_exists( 'hodima_get_canonical_url' ) ? hodima_get_canonical_url() : '';
    return '' !== $canonical ? $canonical : $fallback;
}

/**
 * ItemPage محصول → mainEntity: #product
 * (ItemPage زیرمجموعه CreativeWork است و mainEntity را می‌پذیرد.)
 */
add_filter( 'hodima_schema_webpage_node', static function ( array $node, string $page_url ): array {

    if ( get_option( 'hodima_schema_product_enable', '1' ) !== '1' ) {
        return $node;
    }

    if ( ! is_singular( 'product' ) ) {
        return $node;
    }

    $node['mainEntity'] = [ '@id' => $page_url . '#product' ];

    return $node;
}, 10, 2 );
