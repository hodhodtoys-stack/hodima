<?php
declare(strict_types=1);
if ( ! defined( 'ABSPATH' ) ) exit;

final class Hodima_AEO_Init {

    public static function run(): void {
        require_once __DIR__ . '/class-hodima-aeo-data.php';
        require_once __DIR__ . '/class-hodima-aeo-generator.php';
        require_once __DIR__ . '/class-hodima-aeo-schema.php';
        require_once __DIR__ . '/class-hodima-aeo-router.php';

        Hodima_AEO_Schema::init();
        Hodima_AEO_Router::init();

        add_action( 'save_post', [ __CLASS__, 'clear_llms_cache' ] );

        // نسخه قبلی فقط save_post را می‌شنید. سایت‌مپ شامل دسته‌بندی‌ها
        // هم هست، پس تغییر ترم و حذف نوشته هم باید کش را باطل کند.
        add_action( 'edited_term',        [ __CLASS__, 'clear_sitemap_cache' ] );
        add_action( 'edited_term',        [ __CLASS__, 'clear_term_cache' ] );
        add_action( 'created_term',       [ __CLASS__, 'clear_sitemap_cache' ] );
        add_action( 'delete_term',        [ __CLASS__, 'clear_sitemap_cache' ] );
        add_action( 'before_delete_post', [ __CLASS__, 'clear_llms_cache' ] );
        add_action( 'trashed_post',       [ __CLASS__, 'clear_llms_cache' ] );

        if ( is_admin() ) {
            require_once __DIR__ . '/class-hodima-aeo-admin.php';
            Hodima_AEO_Admin::init();
        }
    }

    public static function clear_llms_cache( $post_id ): void {
        if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) return;
        
        delete_transient( 'hodima_llms_txt_cache_fa_20_siloed' );
        delete_transient( 'hodima_llms_txt_cache_en_20_siloed' );
        delete_transient( 'hodima_llms_txt_cache_fa_500_siloed' );
        delete_transient( 'hodima_llms_txt_cache_en_500_siloed' );

        /*
         * کش .md خود نوشته هم (۲۴ ساعته). قبلا فقط با ذخیره متاباکس AEO پاک
         * می‌شد؛ تغییر قیمت و موجودی از ویرایش سریع، سفارش یا REST تا یک روز
         * در نسخه ماشین‌خوان قدیمی می‌ماند.
         */
        if ( class_exists( 'Hodima_AEO_Generator' ) && (int) $post_id > 0 ) {
            Hodima_AEO_Generator::clear_entity_cache( (int) $post_id, 'post' );
        }

        self::clear_sitemap_cache();
    }

    public static function clear_term_cache( $term_id ): void {
        if ( class_exists( 'Hodima_AEO_Generator' ) && (int) $term_id > 0 ) {
            Hodima_AEO_Generator::clear_entity_cache( (int) $term_id, 'term' );
        }
    }

    /** پاک کردن کش سایت‌مپ XML نسخه‌های مارک‌داون */
    public static function clear_sitemap_cache(): void {
        delete_transient( 'hodima_md_sitemap_fa' );
        delete_transient( 'hodima_md_sitemap_en' );
    }
}

Hodima_AEO_Init::run();