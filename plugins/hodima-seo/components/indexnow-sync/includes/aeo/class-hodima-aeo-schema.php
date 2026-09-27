<?php
declare(strict_types=1);
if ( ! defined( 'ABSPATH' ) ) exit;

final class Hodima_AEO_Schema {
    private static array $graph = [];

    public static function init(): void {
        add_action( 'wp_head', [ __CLASS__, 'add_markdown_alternate_link' ], 5 );
        add_action( 'wp_head', [ __CLASS__, 'render' ], 999 );
        add_action( 'wp_head', [ __CLASS__, 'register_schemas' ], 99 );
    }

    public static function add_markdown_alternate_link(): void {
        echo "<!-- Hodima AI Semantic Endpoints -->\n";
        echo '<link rel="help" type="text/plain" href="' . esc_url( home_url( '/fa/llms.txt' ) ) . '" title="AI Semantic Map (FA)" />' . "\n";
        echo '<link rel="help" type="text/plain" href="' . esc_url( home_url( '/en/llms.txt' ) ) . '" title="AI Semantic Map (EN)" />' . "\n";
        echo '<link rel="alternate" type="application/json" href="' . esc_url( home_url( '/fa/ai-feed.json' ) ) . '" title="Hodima AI Live Feed" />' . "\n";

        $id = get_queried_object_id();
        if ( ! $id ) return;

        $type = ( is_category() || is_tax( 'product_cat' ) ) ? 'term' : 'post';
        
        if ( class_exists('Hodima_Core_Helpers') && Hodima_Core_Helpers::is_noindex( $id, $type ) ) return;

        if ( is_singular( [ 'post', 'page', 'product' ] ) || is_category() || is_tax( 'product_cat' ) ) {
            /*
             * آدرس Markdown از آدرس *واقعی* صفحه، نه شناسه.
             * نسخه قبلی /fa/{شناسه}.md می‌ساخت (مثلا /fa/690.md برای محصولی
             * با canonical /103/). این سایت نامک عددی دارد و مسیریاب AEO اول
             * نامک را امتحان می‌کند؛ اگر محصول دیگری نامک «690» داشت، محتوای
             * *آن* محصول به موتورهای هوش مصنوعی داده می‌شد.
             */
            $page_link = is_singular() ? get_permalink( $id ) : get_term_link( (int) $id );
            $page_link = is_wp_error( $page_link ) ? '' : (string) $page_link;

            $md_url_fa = '' !== $page_link ? Hodima_AEO_Generator::format_md_url( $page_link, 'fa' ) : '';
            $md_url_en = '' !== $page_link ? Hodima_AEO_Generator::format_md_url( $page_link, 'en' ) : '';

            if ( '' === $md_url_fa ) {
                return;
            }

            echo '<link rel="alternate" type="text/markdown" href="' . esc_url( $md_url_fa ) . '" hreflang="fa" title="Markdown (AI-Readable) Version - FA" />' . "\n";
            echo '<link rel="alternate" type="text/markdown" href="' . esc_url( $md_url_en ) . '" hreflang="en" title="Markdown (AI-Readable) Version - EN" />' . "\n";
        }
    }

    public static function add_node( array $node ): void {
        self::$graph[] = $node;
    }

    private static function current_url(): string {

        if ( is_category() || is_tax( 'product_cat' ) ) {
            $link = get_term_link( get_queried_object() );
            if ( $link && ! is_wp_error( $link ) ) {
                return (string) $link;
            }
        }

        $id = get_queried_object_id();
        if ( $id ) {
            $link = get_permalink( $id );
            if ( $link && ! is_wp_error( $link ) ) {
                return (string) $link;
            }
        }

        return home_url( '/' );
    }

    /**
     * نودها به گراف واحد صفحه (hodima-core) می‌روند.
     *
     * نسخه قبلی یک تگ جداگانه چاپ می‌کرد. FAQPage این کلاس و FAQPage سیستم
     * رسانه هر دو شناسه «#faq» دارند؛ گارد حذف تکراری schema-cleaner.php
     * دومی را *کامل* دور می‌انداخت و سوال‌های AEO هرگز به گوگل نمی‌رسید.
     * گراف واحد این دو را ادغام می‌کند (سوال تکراری یک بار).
     */
    public static function render(): void {
        if ( empty( self::$graph ) ) return;
        hodima_schema_add( [ '@graph' => self::$graph ], 'hodima-seo: aeo-schema' );
        self::$graph = [];
    }

    public static function register_schemas(): void {
        $id = get_queried_object_id();
        if ( ! $id ) return;

        $type = ( is_category() || is_tax( 'product_cat' ) ) ? 'term' : 'post';
        
        if ( class_exists('Hodima_Core_Helpers') && Hodima_Core_Helpers::is_noindex( $id, $type ) ) return;

        $get_meta = $type === 'post' ? fn($k) => get_post_meta($id, $k, true) : fn($k) => get_term_meta($id, $k, true);

        $faqs_fa = json_decode( (string) $get_meta('_h_ai_faqs') ?: '[]', true );
        $faqs_en = json_decode( (string) $get_meta('_h_ai_en_faqs') ?: '[]', true );
        if ( ! is_array( $faqs_fa ) ) $faqs_fa = [];
        if ( ! is_array( $faqs_en ) ) $faqs_en = [];
        $all_faqs = array_merge($faqs_en, $faqs_fa);

        $context_en = (string) $get_meta('_h_ai_en_context');
        $context_fa = (string) $get_meta('_h_ai_text');
        $context = !empty($context_en) ? $context_en : $context_fa;

        if ( empty( $all_faqs ) && empty( $context ) ) return;

        // نودهای بدون @id به عنوان موجودیت مستقل دیده می‌شوند. چون
        // media-system/media-schema.php هم روی همین URL یک FAQPage با
        // شناسه #faq و homepage-schema.php یک WebPage با شناسه #webpage
        // می‌سازد، خروجی این کلاس بدون @id باعث ایجاد موجودیت تکراری
        // می‌شد. با دادن همان شناسه‌ها، گراف واحد نودها را ادغام می‌کند.
        //
        // پایه شناسه از موتور canonical مشترک — همان آدرسی که
        // homepage-schema.php برای «#webpage» به کار می‌برد. نسخه قبلی
        // trailingslashit(permalink) بود: در صفحه دوم آرشیو، یا با canonical
        // دستی، یا با ساختار پیوند بدون اسلش پایانی، شناسه‌ها به نودی
        // می‌رسیدند که وجود نداشت (نود جزئی بی‌نوع و آویزان).
        $canonical = function_exists( 'hodima_get_canonical_url' ) ? hodima_get_canonical_url() : '';
        $page_url  = '' !== $canonical ? $canonical : trailingslashit( self::current_url() );

        if ( ! empty( $all_faqs ) ) {
            $schema = [
                '@type'      => 'FAQPage',
                '@id'        => $page_url . '#faq',
                'isPartOf'   => [ '@id' => $page_url . '#webpage' ],
                'mainEntity' => [],
            ];
            foreach ( $all_faqs as $f ) {
                $schema['mainEntity'][] = [
                    '@type'          => 'Question',
                    'name'           => $f['q'],
                    'acceptedAnswer' => [ '@type' => 'Answer', 'text' => $f['a'] ],
                ];
            }
            if ( ! empty( $context ) ) $schema['description'] = $context;
            self::add_node( $schema );
        } elseif ( ! empty( $context ) ) {
            /*
             * گراف اصلی (schema/homepage-schema.php) نود «#webpage» را با نوع
             * درست (CollectionPage / ItemPage / WebPage) و توضیح صفحه ساخته
             * است. نسخه قبلی اینجا یک WebPage دوم با همان شناسه ولی نوع و
             * description متفاوت چاپ می‌کرد. حالا فقط متن هوش مصنوعی به همان
             * نود ضمیمه می‌شود.
             */
            $master_ran = function_exists( 'hodima_schema_webpage_emitted' ) && hodima_schema_webpage_emitted();

            self::add_node( $master_ran
                ? [ '@id' => $page_url . '#webpage', 'text' => $context ]
                : [
                    '@type'       => 'WebPage',
                    '@id'         => $page_url . '#webpage',
                    'url'         => $page_url,
                    'description' => $context,
                    'text'        => $context,
                ]
            );
        }
    }
}