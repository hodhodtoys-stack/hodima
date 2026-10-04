<?php
/**
 * کادر دور فرم افزودن/ویرایش دسته (دسته نوشته و دسته محصول)
 * Path: hodima-core/includes/admin-term-box.php
 *
 * از قالب (inc/category-box.php) منتقل شد — بازسازی قالب، مرحله ۲.
 * ظاهر پیشخوان به قالب سایت ربطی ندارد. نسخه قبلی با jQuery نوشته شده بود؛
 * حالا JavaScript خالص. CSS فقط زیر .hodima-postbox (قبلا قانون
 * .term-thumbnail-wrap روی کل صفحه هم اعمال می‌شد).
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

add_action( 'admin_footer', 'hodima_core_term_form_box' );

function hodima_core_term_form_box(): void {

	// قالب قبل از 2.3.0 همین کادر را خودش (با jQuery) می‌سازد
	if ( hodima_theme_has_legacy_logic() ) {
		return;
	}

	$screen = get_current_screen();

	if ( ! $screen || ! in_array( $screen->taxonomy ?? '', (array) apply_filters( 'hodima_term_box_taxonomies', [ 'category', 'product_cat' ] ), true ) ) {
		return;
	}
	?>
	<style>
		.hodima-postbox { background: #fff; border: 1px solid #c3c4c7; box-shadow: 0 1px 1px rgb(0 0 0 / .04); margin-block-start: 20px;
			& .hodima-postbox-header { border-block-end: 1px solid #c3c4c7; padding: 15px; margin: 0; font-size: 14px; font-weight: 600; }
			& .hodima-postbox-inside { padding: 20px; }
			& #addtag .submit { padding: 0; margin-block-end: 0; }
			& table.form-table { margin-block-start: 0; }
			& div.term-thumbnail-wrap { border-block-end: 1px solid #c3c4c7; padding-block-end: 20px; margin-block-end: 20px !important; }
			& tr.term-thumbnail-wrap :is(th, td) { border-block-end: 1px solid #c3c4c7; padding-block-end: 20px; }
		}
	</style>
	<script>
		( () => {
			/** عنصر را داخل کادر عنوان‌دار می‌گذارد (فقط یک بار). */
			const wrapInBox = ( el, title ) => {
				if ( ! el || el.parentElement?.classList.contains( 'hodima-postbox-inside' ) ) {
					return;
				}
				const box    = document.createElement( 'div' );
				const header = document.createElement( 'h2' );
				const inside = document.createElement( 'div' );
				box.className    = 'hodima-postbox';
				header.className = 'hodima-postbox-header';
				inside.className = 'hodima-postbox-inside';
				header.textContent = title;
				el.before( box );
				box.append( header, inside );
				inside.append( el );
			};

			// صفحه فهرست دسته‌ها: فرم «افزودن»
			const addWrap = document.querySelector( '.form-wrap' );
			if ( addWrap && document.getElementById( 'addtag' ) ) {
				wrapInBox( addWrap, <?php echo wp_json_encode( 'افزودن دسته تازه' ); ?> );
				const firstHeading = addWrap.querySelector( 'h2' );
				if ( firstHeading ) {
					firstHeading.hidden = true;
				}
			}

			// صفحه ویرایش دسته
			wrapInBox( document.getElementById( 'edittag' ), <?php echo wp_json_encode( 'ویرایش دسته' ); ?> );
		} )();
	</script>
	<?php
}
