<?php
/**
 * کادر «تنظیمات پیش‌فاکتور و پیش‌خرید» در ویرایش محصول (منتقل‌شده از پنل قدیمی؛
 * بازطراحی با کیت پیشخوان: مرحله ۶).
 *
 * @package Hodima\Panel
 */

declare(strict_types=1);

namespace Hodima\Panel\Legacy;

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'woocommerce_wp_checkbox' ) && ! class_exists( 'WooCommerce' ) ) {
    return;
}

// ۱. تعریف و ثبت Meta Box جداگانه
add_action( 'add_meta_boxes', __NAMESPACE__ . '\\up_register_custom_product_meta_box' );
function up_register_custom_product_meta_box(): void {
    add_meta_box(
        'up_product_settings_box',
        'تنظیمات پیش‌فاکتور و پیش‌خرید',
        __NAMESPACE__ . '\\up_render_custom_product_fields',
        'product',
        'normal',
        'high'
    );
}

// ۲. رندر کردن (نمایش) فیلدها درون باکس جدید
function up_render_custom_product_fields(): void {
    echo '<div class="woocommerce_options_panel">';

    // چک‌باکس فعال‌سازی پیش‌خرید
    woocommerce_wp_checkbox([
        'id'            => '_prebuy_enabled',
        'label'         => 'فعال‌سازی پیش‌خرید',
        'description'   => 'این محصول در لیست پیش‌خرید پنل کاربری نمایش داده شود.',
    ]);

    // چک‌باکس فعال‌سازی پیش‌فاکتور
    woocommerce_wp_checkbox([
        'id'            => '_preinvoice_enabled',
        'label'         => 'فعال‌سازی پیش‌فاکتور',
        'description'   => 'این محصول در لیست پیش‌فاکتور پنل کاربری نمایش داده شود.',
    ]);

    // لیست انتخابی واحد فروش
    woocommerce_wp_select([
        'id'      => '_up_sale_unit',
        'label'   => 'واحد فروش',
        'options' => [
            ''      => 'انتخاب کنید...', 
            'کیلو'  => 'کیلو',
            'بسته'  => 'بسته',
            'کارتن' => 'کارتن',
            'جین'   => 'جین',
        ],
        'desc_tip'    => true,
        'description' => 'واحدی که در لیست محصولات نمایش داده می‌شود را انتخاب کنید.',
    ]);

    // فیلد جدید: ظرفیت هر واحد (تعداد در هر کارتن/بسته)
    woocommerce_wp_text_input([
        'id'                => '_up_unit_capacity',
        'label'             => 'تعداد در هر واحد (ظرفیت)',
        'placeholder'       => 'مثلاً: 200',
        'type'              => 'number',
        'custom_attributes' => ['step' => '1', 'min' => '1'],
        'desc_tip'    => true,
        'description' => 'اگر واحد فروش "کارتن" یا "بسته" است، داخل آن چند عدد محصول وجود دارد؟ (برای محاسبه دقیق قیمت کل).',
    ]);

    // فیلد حداقل مقدار خرید (بر اساس واحد انتخاب شده)
    woocommerce_wp_text_input([
        'id'                => '_up_min_qty',
        'label'             => 'حداقل سفارش (تعداد واحد)',
        'placeholder'       => 'مثلاً: 2',
        'type'              => 'number',
        'custom_attributes' => ['step' => '1', 'min' => '1'],
        'desc_tip'    => true,
        'description' => 'کمترین تعدادی از "واحد فروش" که کاربر مجاز به ثبت است (مثلاً حداقل 2 کارتن).',
    ]);

    echo '</div>';
}

// ۳. ذخیره سازی فیلدها در دیتابیس
add_action( 'woocommerce_process_product_meta', __NAMESPACE__ . '\\up_save_custom_fields_of_product' );
function up_save_custom_fields_of_product( int $post_id ): void {
    $prebuy = isset( $_POST['_prebuy_enabled'] ) ? 'yes' : 'no';
    update_post_meta( $post_id, '_prebuy_enabled', $prebuy );

    $preinvoice = isset( $_POST['_preinvoice_enabled'] ) ? 'yes' : 'no';
    update_post_meta( $post_id, '_preinvoice_enabled', $preinvoice );

    if ( isset( $_POST['_up_min_qty'] ) ) {
        update_post_meta( $post_id, '_up_min_qty', (string) max( 0, (float) wp_unslash( $_POST['_up_min_qty'] ) ) );
    }
    
    if ( isset( $_POST['_up_sale_unit'] ) ) {
        update_post_meta( $post_id, '_up_sale_unit', sanitize_text_field( wp_unslash( $_POST['_up_sale_unit'] ) ) );
    }

    // ذخیره فیلد جدید (ظرفیت واحد)
    if ( isset( $_POST['_up_unit_capacity'] ) ) {
        // اگر خالی بود به صورت پیش‌فرض 1 در نظر می‌گیریم
        $capacity = !empty($_POST['_up_unit_capacity']) ? (string) max( 1, absint( $_POST['_up_unit_capacity'] ) ) : '1';
        update_post_meta( $post_id, '_up_unit_capacity', $capacity );
    }
}
