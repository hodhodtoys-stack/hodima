<?php
// HARNESS_HOME=1: صفحه اصلی از چیدمان بخش‌ها ساخته شود (دسته‌ها، جدیدترین محصولات، یک دسته، مقالات، مزایا، دعوت به اقدام)
// تا CSS/HTML بخش‌های صفحه اصلی (.hodima-home-card، .hodima-scroller…) در مقایسه ظاهر دیده شوند. پیش‌فرض خاموش.
$hs = (array) get_option( 'hodima_theme_settings', [] );
$hs['home_builder'] = 1;
update_option( 'hodima_theme_settings', $hs );
$hcat = get_terms( [ 'taxonomy' => 'product_cat', 'hide_empty' => false, 'number' => 1 ] );
$hslug = ( is_array( $hcat ) && isset( $hcat[0] ) ) ? $hcat[0]->slug : '';
update_option( 'hodima_home_layout', [
	[ 'id' => 'a1', 'type' => 'categories', 'enabled' => true ],
	[ 'id' => 'a2', 'type' => 'latest', 'enabled' => true, 'link' => '/shop/' ],
	[ 'id' => 'a3', 'type' => 'products', 'enabled' => true, 'category' => $hslug ],
	[ 'id' => 'a4', 'type' => 'blog', 'enabled' => true ],
	[ 'id' => 'a5', 'type' => 'features', 'enabled' => true, 'title' => 'مزایا', 'subtitle' => 'چرا ما', 'items' => [ 'ارسال سریع | تا ۴۸ ساعت', 'ضمانت بازگشت | هفت روز' ] ],
	[ 'id' => 'a6', 'type' => 'cta', 'enabled' => true, 'title' => 'دعوت', 'text' => 'متن کوتاه', 'button_text' => 'شروع', 'link' => '/shop/' ],
] );
