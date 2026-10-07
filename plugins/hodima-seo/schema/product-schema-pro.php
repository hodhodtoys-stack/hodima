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

    // نام جدید سیستم رسانه (Hodima Media 1.2+)، با فالبک نام قدیمی
    $media_data = function_exists('hodima_media_get_data') ? hodima_media_get_data((int) $product_id, 'post')
        : ( function_exists('hook_get_media_data') ? hook_get_media_data($product_id, 'post') : array() );

    // =========================================================================
    // ۱. استخراج مشخصات از کلاس جدول
    $additional_properties = [];
    $schema_facts = [];

    if ( class_exists( 'Hodima_Product_Specs_Table' ) ) {
        $specs_table_instance = Hodima_Product_Specs_Table::get_instance();
        $prepared_data = $specs_table_instance->_prepare_specs_data( $_product );
        
        if ( ! empty( $prepared_data['schema_properties'] ) ) {
            $additional_properties = $prepared_data['schema_properties'];
        }

        // رنگ، جنس، سایز و وزن واقعی (بدون مقدار پیش‌فرض) برای ویژگی‌های اصلی Product
        $schema_facts = (array) ( $prepared_data['schema_facts'] ?? [] );
    }

    /*
     * ۲. توضیح محصول: متن خود محصول، نه فهرست مشخصات.
     *
     * قبلا «ویژگی‌ها: جنس: … | سایز: … | رنگ: … | تولید: …» به توضیح چسبانده
     * می‌شد (و بدون توضیح کوتاه، کل توضیح همین بود)؛ همان داده‌ها سومین بار،
     * بعد از ویژگی‌های اصلی و additionalProperty. ترتیب: توضیح کوتاه ←
     * اولین بند متن محصول ← الگوی پنل.
     */
    $raw_short_desc = trim( wp_strip_all_tags( strip_shortcodes( $_product->get_short_description() ) ) );

    if ( '' === $raw_short_desc ) {
        $content_text = trim( (string) preg_replace( "/[ \t]+/u", ' ', wp_strip_all_tags( strip_shortcodes( (string) $_product->get_description() ) ) ) );
        foreach ( (array) preg_split( '/\R\s*\R|\R/u', $content_text ) as $paragraph ) {
            if ( mb_strlen( trim( (string) $paragraph ) ) >= 20 ) {
                $raw_short_desc = trim( (string) $paragraph );
                break;
            }
        }
    }

    if ( '' !== $raw_short_desc ) {
        $clean_description = wp_trim_words( $raw_short_desc, 40 );
    } else {
        $clean_description = $final_desc_tpl;
    }
    // =========================================================================

    /*
     * تصاویر: تصویر اصلی + گالری (گوگل چند تصویر را ترجیح می‌دهد؛ قبلا فقط
     * تصویر اصلی بود). بدون تصویر → لوگو.
     */
    $images = [];
    foreach ( array_merge( [ (int) $_product->get_image_id() ], array_map( 'intval', (array) ( method_exists( $_product, 'get_gallery_image_ids' ) ? $_product->get_gallery_image_ids() : [] ) ) ) as $img_id ) {
        $img_url = $img_id ? (string) wp_get_attachment_url( $img_id ) : '';
        if ( '' !== $img_url && ! in_array( $img_url, $images, true ) && count( $images ) < 10 ) {
            $images[] = $img_url;
        }
    }
    $image_url = $images[0] ?? $opt_def_img;

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

    // سیاست مرجوعی از پنل — همان که روی سازمان هم هست (schema-helpers.php)
    $merchant_return = hodima_seo_schema_return_policy();

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
     * مبلغ پنل به تومان است و برای گوگل به ریال (IRR، کد ISO) تبدیل می‌شود.
     */
    if ( 'fixed' === get_option( 'hodima_schema_product_shipping_mode', 'customer' ) ) {
        $shipping_details['shippingRate'] = [
            '@type'    => 'MonetaryAmount',
            'value'    => (string) hodima_seo_schema_panel_amount_rial( $opt_shipping_cost ),
            'currency' => 'IRR',
        ];
    } elseif ( ( $shipping_max = hodima_seo_schema_panel_amount_rial( get_option( 'hodima_schema_product_shipping_max', '' ) ) ) > 0 ) {
        $shipping_details['shippingRate'] = [
            '@type'    => 'MonetaryAmount',
            'maxValue' => $shipping_max,
            'currency' => 'IRR',
        ];
    }

    /*
     * زمان تحویل. انبار چین زمان جداگانه خودش را دارد (پنل اسکیما ←
     * محصولات). قبلا deliveryTime برای این محصولات حذف می‌شد و Merchant
     * Listings هشدار Missing field "deliveryTime" می‌داد. زمان دلخواه:
     *   add_filter( 'hodima_product_delivery_days', fn( $d, $loc ) => 'china' === $loc
     *       ? [ 'handling' => [ 2, 5 ], 'transit' => [ 20, 35 ] ] : $d, 10, 2 );
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

    /*
     * پیشنهاد (Offer) یک محصول یا یک تنوع.
     *
     * قیمت فروشگاه تومان است؛ گوگل فقط کد ISO 4217 را می‌پذیرد و «IRT» کد
     * رسمی نیست، پس ×۱۰ و IRR (hodima_seo_schema_price). نسخه قبلی فقط
     * IRT را می‌شناخت و «هزار تومان» (IRHT) را بی‌تبدیل و با کد نامعتبر
     * چاپ می‌کرد.
     *
     * بدون قیمت → بدون Offer. نسخه قبلی "price": "0" می‌گذاشت که گوگل آن را
     * «رایگان» می‌خواند؛ محصول با امتیاز/نظرات همچنان Product snippet دارد.
     * حداقل سفارش (eligibleTransactionVolume) عمدا در اسکیما نیست.
     */
    $make_offer = static function ( $item, string $offer_url, string $availability ) use ( $valid_until, $valid_from, $seller_info, $merchant_return, $shipping_details ): ?array {

        // «قیمت تک» یا «حداقل سفارش» (تنظیمات قالب ← صفحه محصول؛ inc/product-price.php)
        $price = hodima_seo_schema_price( hodima_seo_product_feed_price( $item )['amount'] );

        if ( null === $price ) {
            return null;
        }

        return [
            '@type'                   => 'Offer',
            'url'                     => $offer_url,
            'priceCurrency'           => $price[1],
            'price'                   => $price[0],
            'priceValidUntil'         => $valid_until,
            'validFrom'               => $valid_from,
            'availability'            => $availability,
            'itemCondition'           => 'https://schema.org/NewCondition',
            'seller'                  => $seller_info,
            'hasMerchantReturnPolicy' => $merchant_return,
            'shippingDetails'         => $shipping_details,
        ];
    };

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
        '@type'            => 'Product',
        '@id'              => $page_url . '#product',
        'url'              => $page_url,
        'mainEntityOfPage' => [ '@id' => $page_url . '#webpage' ],
        'name'             => $name,
        'image'            => count( $images ) > 1 ? $images : $image_url,
        'description'      => $clean_description,
        'sku'              => $sku,
        // mpn (کد قطعه سازنده) عمدا نیست: قبلا همیشه برابر SKU خود فروشگاه
        // گذاشته می‌شد که داده ساختگی است.
        'brand'            => ['@type' => 'Brand', 'name'  => $opt_brand],
    ];

    $gtin = hodima_seo_schema_gtin( $_product );
    if ( '' !== $gtin ) {
        $schema['gtin'] = $gtin;
    }

    /*
     * محصول متغیر → ProductGroup با تنوع‌ها (hasVariant).
     *
     * نسخه قبلی یک AggregateOffer (بازه قیمت) می‌ساخت. گوگل AggregateOffer
     * را فقط برای Product snippet می‌پذیرد، نه برای Merchant listings
     * (نتایج فروش)؛ شیوه‌ی درست از ۲۰۲۴ گروه محصول است: هر رنگ/سایز یک
     * Product با قیمت، موجودی، SKU و آدرس خودش.
     */
    $variants = $_product->is_type( 'variable' ) ? hodima_product_schema_variants( $_product, $page_url, $sku, $name, $image_url, $stock_status, $make_offer ) : null;

    if ( null !== $variants ) {
        $schema['@type']          = 'ProductGroup';
        $schema['productGroupID'] = $sku;
        if ( [] !== $variants['varies_by'] ) {
            $schema['variesBy'] = $variants['varies_by'];
        }
        $schema['hasVariant'] = $variants['items'];
        unset( $schema['gtin'] ); // بارکد مال هر تنوع است، نه گروه
    } elseif ( ! $_product->is_type( 'variable' ) ) {
        $offer = $make_offer( $_product, $page_url, $stock_status );
        if ( null !== $offer ) {
            $schema['offers'] = $offer;
        }
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
        // باگ رفع‌شده: جستجوی «چین»/«ایران» روی متنی بود که «ی»اش حذف شده؛
        // «ساخت چین» هرگز پیدا نمی‌شد. حالا روی متن اصلی (ی عربی → فارسی).
        in_array( $origin_norm, [ 'cn', 'چن', 'china' ], true ), str_contains( str_replace( 'ي', 'ی', $origin_text ), 'چین' ) => 'CN',
        in_array( $origin_norm, [ 'ir', 'اران', 'iran' ], true ), str_contains( str_replace( 'ي', 'ی', $origin_text ), 'ایران' ) => 'IR',
        default => '',
    };
    if ( '' !== $origin ) {
        $schema['countryOfOrigin'] = [ '@type' => 'Country', 'name' => $origin ];
    }

    /*
     * «سئو مدرن» برای محصولات خاموش است (hook_modern_seo_enabled در
     * media-system/media-helpers.php):
     *   - عنوان Discover به عنوان alternateName (پایین) و موجودیت‌ها به عنوان
     *     مشخصه «مرتبط با» — هر دو کاربرد نادرست آن ویژگی‌ها.
     *   - خلاصه هوش مصنوعی کلا از سایت حذف شد (دیگر به description اضافه نمی‌شود).
     */
    $modern_seo = function_exists( 'hodima_media_discover_enabled' )
        ? hodima_media_discover_enabled( 'post', (int) $product_id )
        : ( function_exists( 'hook_modern_seo_enabled' ) && hook_modern_seo_enabled( 'post', (int) $product_id ) );

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
            /*
             * عدد (مثلا وزن ۲۰ با unitCode) عدد می‌ماند؛ فهرست چند گزینه‌ای
             * (["1.5", "2.5", "3"] یا ["صورتی", "آبی"]) فهرست می‌ماند؛ بقیه متن تمیز.
             */
            $clean_value = static fn( mixed $v ): int|float|string => ( is_int( $v ) || is_float( $v ) ) ? $v : trim( wp_strip_all_tags( is_scalar( $v ) ? (string) $v : '' ) );

            $prop_value = $property['value'] ?? '';
            if ( is_array( $prop_value ) ) {
                $prop_value = array_values( array_filter( array_map( $clean_value, $prop_value ), static fn( $v ): bool => '' !== $v ) );
                $prop_value = match ( count( $prop_value ) ) {
                    0       => '',
                    1       => $prop_value[0],
                    default => $prop_value,
                };
            } else {
                $prop_value = $clean_value( $prop_value );
            }

            if ( '' === $prop_name || '' === $prop_value ) {
                continue;
            }

            $key = mb_strtolower( preg_replace( '/\s+/u', ' ', $prop_name ) );

            if ( isset( $seen[ $key ] ) ) {
                continue;
            }

            $seen[ $key ] = true;

            $entry = [
                '@type' => 'PropertyValue',
                'name'  => $prop_name,
                'value' => $prop_value,
            ];

            // واحد اندازه‌گیری (UN/CEFACT) و شناسه ویژگی، اگر ماژول سازنده داده باشد
            foreach ( [ 'unitCode', 'unitText', 'propertyID' ] as $extra_key ) {
                if ( isset( $property[ $extra_key ] ) && is_string( $property[ $extra_key ] ) && '' !== trim( $property[ $extra_key ] ) ) {
                    $entry[ $extra_key ] = trim( wp_strip_all_tags( $property[ $extra_key ] ) );
                }
            }

            $clean[] = $entry;

            if ( count( $clean ) >= 25 ) {
                break;
            }
        }

        /*
         * گروه محصول: مشخصه‌ای که تنوع‌ها بر اساسش فرق دارند (مثلا «رنگ»)
         * روی گروه نمی‌آید؛ جدول مشخصات یک مقدار کلی («تک رنگ»/«متنوع»)
         * می‌دهد که با رنگ هر تنوع تناقض دارد.
         */
        if ( ! empty( $schema['variesBy'] ) ) {
            $clean = array_values( array_filter( $clean, static fn( array $p ): bool =>
                ! in_array( 'https://schema.org/' . hodima_product_schema_variant_property( '', $p['name'] ), (array) $schema['variesBy'], true )
            ) );
        }

        if ( ! empty( $clean ) ) {
            $schema['additionalProperty'] = $clean;
        }
    }

    /*
     * رنگ، جنس و سایز به‌عنوان ویژگی‌های اصلی Product (ویژگی‌های پیشنهادی
     * گوگل برای محصول و Merchant listings). قبلا فقط تنوع‌های محصول متغیر
     * آن‌ها را داشتند و در محصول ساده فقط در additionalProperty بودند که
     * گوگل برای نتایج غنی محصول نمی‌خواند. فقط مقدار واقعی (نه «متنوع» /
     * «تک رنگ» پیش‌فرض)، و در گروه محصول نه ویژگی‌ای که تنوع‌ها بر اساسش
     * فرق دارند (مقدار آن مال هر تنوع است). additionalProperty دست نمی‌خورد.
     */
    foreach ( [ 'color', 'material', 'size' ] as $fact ) {
        $fact_value = trim( (string) ( $schema_facts[ $fact ] ?? '' ) );
        if ( '' === $fact_value || isset( $schema[ $fact ] )
            || in_array( 'https://schema.org/' . $fact, (array) ( $schema['variesBy'] ?? [] ), true ) ) {
            continue;
        }
        $schema[ $fact ] = $fact_value;
    }

    // وزن: عدد + کد واحد (QuantitativeValue)، نه متن «۲۰ گرم»
    if ( ! empty( $schema_facts['weight']['value'] ) && ! empty( $schema_facts['weight']['unitCode'] ) ) {
        $schema['weight'] = [ '@type' => 'QuantitativeValue' ] + array_intersect_key( (array) $schema_facts['weight'], array_flip( [ 'value', 'unitCode', 'unitText' ] ) );
    }

    /*
     * هر داده یک جا: ردیفی از additionalProperty که شناسه‌اش (propertyID) همان
     * ویژگی اصلی است که بالا ساخته شد (color، material، size، weight،
     * countryOfOrigin) حذف می‌شود. قبلا رنگ/جنس/سایز/وزن/تولید هم ویژگی
     * اصلی بودند و هم در additionalProperty. ردیفی که ویژگی اصلی‌اش ساخته
     * نشده (مثلا رنگ چندگزینه‌ای) سر جایش می‌ماند.
     */
    if ( ! empty( $schema['additionalProperty'] ) ) {
        $schema['additionalProperty'] = array_values( array_filter(
            $schema['additionalProperty'],
            static function ( array $p ) use ( $schema ): bool {
                $id = (string) ( $p['propertyID'] ?? '' );
                return ! ( str_starts_with( $id, 'https://schema.org/' ) && isset( $schema[ substr( $id, 19 ) ] ) );
            }
        ) );
        if ( [] === $schema['additionalProperty'] ) {
            unset( $schema['additionalProperty'] );
        }
    }

    /*
     * ویدیو: سازنده واحد سیستم رسانه (media-system/media-video.php) — همان
     * کاور، تاریخ، پخش‌کننده، فصل‌ها و متن کامل که روی صفحه نمایش داده
     * می‌شود. فقط وقتی سیستم رسانه برای محصول روشن است؛ قبلا ویدیوی محصولی
     * که «فعال‌سازی» آن خاموش بود (و روی صفحه دیده نمی‌شد) هم اعلام می‌شد.
     * کد پایین فقط برای Hodima Media قدیمی (بدون این تابع) می‌ماند.
     */
    if ( function_exists( 'hodima_media_video_node' ) ) {
        $video_node = hodima_media_video_node( (int) $product_id, 'post', [
            '_name'            => 'فیلم معرفی ' . $name,
            'mainEntityOfPage' => [ '@id' => $page_url . '#webpage' ],
            'about'            => [ '@id' => $page_url . '#product' ],
        ] );
        if ( null === $video_node ) {
            unset( $video_node );
        } else {
            $schema['subjectOf'] = [ '@id' => $video_node['@id'] ];
        }
    } elseif ( ! empty( $media_data['video_url'] ) ) {
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

        // صفحه آپارات/یوتیوب → آدرس پخش‌کننده (embed)؛ گوگل صفحه تماشا را embedUrl نمی‌پذیرد
        $video_node[ $is_file ? 'contentUrl' : 'embedUrl' ] = ( ! $is_file && function_exists( 'hodima_video_player_url' ) ) ? hodima_video_player_url( $video_raw ) : $video_raw;

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
 * تنوع‌های یک محصول متغیر برای ProductGroup.hasVariant، یا null اگر هیچ
 * تنوع قیمت‌داری نیست.
 *
 * هر تنوع: نام (نام محصول + مقدار ویژگی‌ها)، SKU خودش (اگر خالی یا همان SKU
 * والد باشد، «SKU والد-شناسه» تا یکتا بماند)، تصویر، GTIN، آدرس با انتخاب
 * همان تنوع (?attribute_pa_color=…) و پیشنهاد جداگانه. ویژگی‌هایی که گوگل
 * برای variesBy می‌شناسد (رنگ، سایز، جنس، طرح) به ویژگی schema.org خودشان
 * می‌روند؛ بقیه additionalProperty.
 *
 * @return array{items: list<array>, varies_by: list<string>}|null
 */
function hodima_product_schema_variants( WC_Product $product, string $page_url, string $group_sku, string $group_name, string $fallback_image, string $group_availability, callable $make_offer ): ?array {

    $max      = max( 1, (int) apply_filters( 'hodima_product_schema_max_variants', 50 ) );
    $children = array_slice( array_map( 'intval', (array) $product->get_children() ), 0, $max );
    $items    = [];
    $varies   = [];

    foreach ( $children as $variation_id ) {

        $variation = wc_get_product( $variation_id );

        if ( ! $variation || 'publish' !== $variation->get_status() ) {
            continue;
        }

        $props = [];
        $extra = [];
        $parts = [];

        foreach ( (array) $variation->get_attributes() as $key => $value ) {

            $value = (string) $value;
            if ( '' === $value ) {
                continue; // «هر مقدار» — این تنوع روی این ویژگی فرقی ندارد
            }

            $term  = taxonomy_exists( (string) $key ) ? get_term_by( 'slug', $value, (string) $key ) : false;
            $shown = $term ? (string) $term->name : rawurldecode( $value );
            $label = function_exists( 'wc_attribute_label' ) ? (string) wc_attribute_label( (string) $key, $product ) : (string) $key;
            $prop  = hodima_product_schema_variant_property( (string) $key, $label );

            if ( '' !== $prop ) {
                $props[ $prop ]  = $shown;
                $varies[ $prop ] = 'https://schema.org/' . $prop;
            } else {
                $extra[] = [ '@type' => 'PropertyValue', 'name' => wp_strip_all_tags( $label ), 'value' => wp_strip_all_tags( $shown ) ];
            }

            $parts[] = $shown;
        }

        // آدرس تنوع = آدرس canonical + همان رشته انتخاب ویژگی که ووکامرس می‌سازد
        // نویسه‌های غیر ASCII (نام ویژگی/مقدار فارسی) درصدی می‌شوند تا آدرس معتبر باشد
        $query     = (string) preg_replace_callback( '/[^\x21-\x7e]+/', static fn( array $m ): string => rawurlencode( $m[0] ), (string) wp_parse_url( (string) $variation->get_permalink(), PHP_URL_QUERY ) );
        $offer_url = '' !== $query ? $page_url . ( str_contains( $page_url, '?' ) ? '&' : '?' ) . $query : $page_url;

        $availability = $variation->is_in_stock() ? $group_availability : 'https://schema.org/OutOfStock';
        $offer        = $make_offer( $variation, $offer_url, $availability );

        if ( null === $offer ) {
            continue; // تنوع بدون قیمت پیشنهادی ندارد و گوگل آن را رد می‌کند
        }

        $variant_sku = (string) $variation->get_sku();
        if ( '' === $variant_sku || $variant_sku === $group_sku ) {
            $variant_sku = $group_sku . '-' . $variation_id;
        }

        $image_id = (int) $variation->get_image_id();
        $image    = $image_id ? (string) wp_get_attachment_url( $image_id ) : '';

        $item = [
            '@type'                => 'Product',
            '@id'                  => $page_url . '#variant-' . $variation_id,
            'name'                 => [] !== $parts ? $group_name . ' - ' . implode( '، ', $parts ) : $group_name,
            'sku'                  => $variant_sku,
            'inProductGroupWithID' => $group_sku,
            'image'                => '' !== $image ? $image : $fallback_image,
        ] + $props;

        $gtin = function_exists( 'hodima_seo_schema_gtin' ) ? hodima_seo_schema_gtin( $variation ) : '';
        if ( '' !== $gtin ) {
            $item['gtin'] = $gtin;
        }

        if ( [] !== $extra ) {
            $item['additionalProperty'] = $extra;
        }

        $item['offers'] = $offer;
        $items[]        = $item;
    }

    return [] === $items ? null : [ 'items' => $items, 'varies_by' => array_values( $varies ) ];
}

/**
 * ویژگی schema.org یک ویژگی ووکامرس (برای variesBy)، یا رشته خالی.
 * گوگل برای تنوع فقط color، size، material، pattern، suggestedAge و
 * suggestedGender را می‌شناسد.
 */
function hodima_product_schema_variant_property( string $key, string $label ): string {

    $slug  = mb_strtolower( rawurldecode( (string) preg_replace( '/^(attribute_)?(pa_)?/', '', $key ) ) );
    $label = mb_strtolower( trim( $label ) );

    $map = [
        'color'    => [ 'color', 'colour', 'rang', 'رنگ', 'رنگ‌بندی', 'رنگبندی' ],
        'size'     => [ 'size', 'saiz', 'سایز', 'اندازه' ],
        'material' => [ 'material', 'jens', 'جنس', 'متریال' ],
        'pattern'  => [ 'pattern', 'tarh', 'طرح', 'الگو' ],
    ];

    foreach ( $map as $prop => $names ) {
        if ( in_array( $slug, $names, true ) || in_array( $label, $names, true ) ) {
            return $prop;
        }
    }

    return '';
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
