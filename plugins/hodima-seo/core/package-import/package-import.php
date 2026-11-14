<?php
/**
 * ماژول «ورود بسته» — کادر بالای ویرایش محصول، دسته محصول، مقاله و ویدیو
 * Path: core/package-import/package-import.php
 *
 * جای کادر: ویرایشگر کلاسیک ← edit_form_top (بالای عنوان)؛ ویرایشگر بلوکی (مقاله و ویدیو) ← کادر
 * اول ناحیه کادرها (edit_form_top آنجا اجرا نمی‌شود)؛ دسته ← {taxonomy}_term_edit_form_top (داخل
 * فرم ترم، بالای جدول). رابط و قانون‌ها در هر سه یکی است.
 *
 * مدیر یک بسته JSON (hodima-package/1) می‌چسباند؛ «بررسی» (admin-ajax، فقط خواندن) قفل آدرس و
 * آدرس‌ها را می‌سنجد و برای هر کادر «فعلی ← جدید» نشان داده می‌شود؛ «پر کردن فرم» فقط خانه‌های
 * همین صفحه را در مرورگر پر می‌کند. ذخیره فقط با «به‌روزرسانی» خود مدیر و کد ذخیره خود هر
 * کادر است: این ماژول هیچ‌جا در پایگاه داده نمی‌نویسد و ذخیره خودکار ندارد.
 * جدا از دستار سئو (core/advisor) است، چون کار دستار فقط پیدا کردن ایراد است.
 * طرح تأییدشده: Artifact «طرح کادر ورود بسته» (HODIMA-AUDIT.md بخش ۴۳۵).
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/package-import-types.php';
require_once __DIR__ . '/package-import-check.php';
require_once dirname( __DIR__ ) . '/seobox/seobox-metrics.php'; // خط‌کش پیکسلی مشترک عنوان/متا

add_action( 'edit_form_top', 'hodima_seo_pkg_render_classic' );
add_action( 'add_meta_boxes', 'hodima_seo_pkg_register_block_box', 1, 2 );
add_action( 'admin_enqueue_scripts', 'hodima_seo_pkg_assets' );
add_action( 'wp_ajax_hodima_seo_pkg_check', 'hodima_seo_pkg_ajax_check' );
add_action( 'init', static function (): void {
	foreach ( hodima_seo_pkg_types() as $spec ) {
		if ( 'term' === ( $spec['object'] ?? '' ) && isset( $spec['taxonomy'] ) ) {
			add_action( "{$spec['taxonomy']}_term_edit_form_top", 'hodima_seo_pkg_render_term', 1, 1 );
		}
	}
}, 30 );

/** آیا کادر روی ویرایش این شیء هست؟ (نوع شناخته‌شده + مجوز ویرایش همان شیء) */
function hodima_seo_pkg_enabled_for( WP_Post|WP_Term $subject ): bool {
	if ( null === hodima_seo_pkg_type_for_object( $subject ) ) {
		return false;
	}
	return $subject instanceof WP_Term
		? current_user_can( 'edit_term', $subject->term_id )
		: current_user_can( 'edit_post', $subject->ID );
}

/** شیء صفحه ویرایش فعلی پیشخوان (نوشته یا ترم)، یا null. */
function hodima_seo_pkg_screen_object( string $hook ): WP_Post|WP_Term|null {

	if ( in_array( $hook, [ 'post.php', 'post-new.php' ], true ) ) {
		global $post;
		return $post instanceof WP_Post ? $post : null;
	}
	if ( 'term.php' === $hook ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- فقط خواندن شناسه صفحه جاری
		$term = get_term( absint( $_GET['tag_ID'] ?? 0 ) );
		return $term instanceof WP_Term ? $term : null;
	}
	return null;
}

/** ویرایشگر بلوکی: کادر اول ناحیه کادرها (edit_form_top در این ویرایشگر اجرا نمی‌شود). */
function hodima_seo_pkg_register_block_box( string $post_type, mixed $post = null ): void {
	if ( ! $post instanceof WP_Post || ! function_exists( 'use_block_editor_for_post' ) || ! use_block_editor_for_post( $post ) || ! hodima_seo_pkg_enabled_for( $post ) ) {
		return;
	}
	add_meta_box( 'hodima_pkg_box', 'ورود بسته', static fn( WP_Post $p ) => hodima_seo_pkg_render_box( $p, 'block' ), $post_type, 'normal', 'high' );
}

/** ویرایشگر کلاسیک: بالای عنوان. */
function hodima_seo_pkg_render_classic( WP_Post $post ): void {
	if ( function_exists( 'use_block_editor_for_post' ) && use_block_editor_for_post( $post ) ) {
		return;
	}
	hodima_seo_pkg_render_box( $post, 'classic' );
}

/** صفحه ویرایش دسته: داخل فرم ترم، بالای جدول. */
function hodima_seo_pkg_render_term( mixed $term ): void {
	if ( $term instanceof WP_Term ) {
		hodima_seo_pkg_render_box( $term, 'term' );
	}
}

function hodima_seo_pkg_assets( string $hook ): void {

	$subject = hodima_seo_pkg_screen_object( $hook );
	if ( null === $subject || ! hodima_seo_pkg_enabled_for( $subject ) ) {
		return;
	}
	$type = (string) hodima_seo_pkg_type_for_object( $subject );

	if ( function_exists( 'hodima_seo_ui_enqueue' ) ) {
		hodima_seo_ui_enqueue(); // نوار وضعیت، نشان، دکمه و فیلد از کیت UI همین افزونه
	}

	$dir = HODIMA_SEO_DIR . '/core/package-import/';
	$url = HODIMA_SEO_URL . '/core/package-import/';
	$ver = static fn( string $file ): string => is_file( $dir . $file ) ? (string) filemtime( $dir . $file ) : HODIMA_SEO_VERSION;

	wp_enqueue_style( 'hodima-seo-pkg', $url . 'package-import.css', [ 'dashicons' ], $ver( 'package-import.css' ) );
	wp_enqueue_script( 'hodima-seo-pkg', $url . 'package-import.js', [], $ver( 'package-import.js' ), [ 'in_footer' => true ] );
	$is_term = $subject instanceof WP_Term;
	wp_localize_script( 'hodima-seo-pkg', 'hodimaPkg', [
		'ajax'       => admin_url( 'admin-ajax.php' ),
		'nonce'      => wp_create_nonce( 'hodima_seo_pkg_check' ),
		'objectType' => $is_term ? 'term' : 'post',
		'objectId'   => $is_term ? $subject->term_id : $subject->ID,
		'postId'     => $is_term ? 0 : $subject->ID, // سازگاری نسخه ۱
		'type'       => $type,
		'editor'     => $is_term ? 'term' : ( function_exists( 'use_block_editor_for_post' ) && use_block_editor_for_post( $subject ) ? 'block' : 'classic' ),
		'labels'     => hodima_seo_pkg_box_labels( $type ),
		'aeoKeys'    => hodima_seo_pkg_aeo_keys(),
		'fieldMaps'  => [ 'video' => HODIMA_SEO_PKG_MEDIA_VIDEO, 'podcast' => HODIMA_SEO_PKG_MEDIA_PODCAST, 'video_watch' => HODIMA_SEO_PKG_VIDEO_WATCH ],
		'boxes'      => hodima_seo_pkg_types()[ $type ]['boxes'],
	] );
}

/**
 * خود کادر (هر سه جا). بدون جاوااسکریپت کاری نمی‌کند، پس تا اسکریپت آماده نشده پنهان است.
 * هیچ خانه‌ای name ندارد تا متن بسته با فرم فرستاده نشود.
 *
 * @param string $place classic | block | term
 */
function hodima_seo_pkg_render_box( WP_Post|WP_Term $subject, string $place = 'classic' ): void {

	if ( ! hodima_seo_pkg_enabled_for( $subject ) ) {
		return;
	}
	$type   = (string) hodima_seo_pkg_type_for_object( $subject );
	$format = 'product' === $type ? HODIMA_SEO_PKG_FORMAT : HODIMA_SEO_PKG_FORMAT_V2;
	?>
	<section class="hds-ui hodima-pkg hodima-pkg--<?php echo esc_attr( $place ); ?>" data-hodima-pkg aria-labelledby="hodima-pkg-title" hidden>
		<header class="hds-statusbar hodima-pkg__head">
			<span class="hds-statusbar__icon dashicons dashicons-download" data-pkg-icon aria-hidden="true"></span>
			<div class="hds-statusbar__body">
				<?php // در ویرایشگر بلوکی خود کادر وردپرس عنوان «ورود بسته» دارد؛ عنوان تکراری فقط برای صفحه‌خوان ?>
				<h2 class="hds-statusbar__title<?php echo 'block' === $place ? ' screen-reader-text' : ''; ?>" id="hodima-pkg-title">ورود بسته</h2>
				<p class="hds-statusbar__text" data-pkg-status aria-live="polite">بسته JSON سئو را بچسبانید تا خانه‌های همین صفحه پر شوند؛ ذخیره فقط با «به‌روزرسانی».</p>
			</div>
			<div class="hds-statusbar__aside">
				<span class="hds-pill" data-pkg-pill hidden></span>
				<button type="button" class="hds-btn hds-btn--outline hodima-pkg__toggle" data-pkg-toggle aria-expanded="false" aria-controls="hodima-pkg-body">باز کردن</button>
			</div>
		</header>

		<div class="hodima-pkg__body" id="hodima-pkg-body" hidden>
			<ol class="hodima-pkg__steps" aria-label="گام‌ها" data-pkg-steps>
				<li data-step="1"><span class="hodima-pkg__dot" aria-hidden="true">۱</span>چسباندن<span class="screen-reader-text" data-step-sr></span></li>
				<li data-step="2"><span class="hodima-pkg__dot" aria-hidden="true">۲</span>بررسی<span class="screen-reader-text" data-step-sr></span></li>
				<li data-step="3"><span class="hodima-pkg__dot" aria-hidden="true">۳</span>پر کردن فرم<span class="screen-reader-text" data-step-sr></span></li>
				<li data-step="4"><span class="hodima-pkg__dot" aria-hidden="true">۴</span>«به‌روزرسانی» (شما)<span class="screen-reader-text" data-step-sr></span></li>
			</ol>

			<div class="hodima-pkg__paste" data-pkg-paste>
				<label class="hodima-pkg__label" for="hodima-pkg-json">متن بسته (JSON)</label>
				<textarea class="hds-input hodima-pkg__json" id="hodima-pkg-json" dir="ltr" rows="7" spellcheck="false" autocomplete="off" placeholder='{"format": "<?php echo esc_attr( $format ); ?>", "url": "<?php echo esc_attr( hodima_seo_pkg_page_url( $subject ) ); ?>", "type": "<?php echo esc_attr( $type ); ?>", "boxes": { … }}' data-pkg-json></textarea>
				<div class="hodima-pkg__actions">
					<button type="button" class="hds-btn" data-pkg-check>بررسی بسته</button>
					<button type="button" class="hds-btn hds-btn--outline" data-pkg-file-btn>انتخاب فایل .json</button>
					<input type="file" accept=".json,application/json" data-pkg-file hidden>
					<span class="hodima-pkg__hint">بررسی فقط می‌خواند؛ هیچ‌چیز ذخیره نمی‌شود.</span>
				</div>
				<p class="hodima-pkg__error" data-pkg-paste-error role="alert" hidden></p>
			</div>

			<div class="hodima-pkg__loaded" data-pkg-loaded hidden>
				<div class="hodima-pkg__info">
					<span data-pkg-info></span>
					<button type="button" class="button-link hodima-pkg__edit" data-pkg-edit>ویرایش متن بسته</button>
				</div>
				<div class="hodima-pkg__lock" data-pkg-lock></div>
				<ul class="hodima-pkg__errors" data-pkg-errors hidden></ul>
				<div data-pkg-review hidden>
					<blockquote class="hodima-pkg__note" data-pkg-note hidden></blockquote>
					<div class="hodima-pkg__chips" data-pkg-chips></div>
					<ul class="hodima-pkg__rows" aria-label="کادرهای این صفحه" data-pkg-rows></ul>
					<ul class="hodima-pkg__ignored" data-pkg-ignored hidden></ul>
					<p class="hodima-pkg__hint">کادری که در بسته نیست دست نمی‌خورد. کلیدی که هست کل همان کادر را عوض می‌کند و [] یعنی خالی کردن. بیرون از ابزار: عکس‌ها و alt، تصویر دیسکاور و کاورها، ویژگی‌ها و دسته‌ها و برچسب‌ها، رسانه‌های پنل AEO.</p>
				</div>
				<div class="hodima-pkg__fillbar" data-pkg-fillbar hidden>
					<button type="button" class="hds-btn" data-pkg-fill>پر کردن فرم</button>
					<span class="hodima-pkg__hint" data-pkg-fill-hint></span>
				</div>
				<div class="hodima-pkg__done" data-pkg-done role="status" tabindex="-1" hidden>
					<p data-pkg-done-text></p>
					<button type="button" class="hds-btn hds-btn--outline hodima-pkg__undo" data-pkg-undo>بازگرداندن مقدارهای قبلی</button>
				</div>
			</div>
		</div>
	</section>
	<?php
}

/** «بررسی بسته»: فقط خواندن؛ همان مجوز ویرایش همین نوشته یا ترم. */
function hodima_seo_pkg_ajax_check(): void {

	check_ajax_referer( 'hodima_seo_pkg_check', 'nonce' );

	$id     = absint( $_POST['object_id'] ?? $_POST['post_id'] ?? 0 );
	$subject = 'term' === ( $_POST['object_type'] ?? '' ) ? get_term( $id ) : get_post( $id );
	if ( ! ( $subject instanceof WP_Post || $subject instanceof WP_Term ) || ! hodima_seo_pkg_enabled_for( $subject ) ) {
		wp_send_json_error( [ 'message' => 'دسترسی ویرایش این صفحه را ندارید.' ], 403 );
	}

	// متن بسته JSON خام است و فقط خوانده می‌شود؛ هر مقدار را نرمال‌ساز همان کادر تمیز می‌کند
	// و کد ذخیره خود هر کادر هنگام «به‌روزرسانی» دوباره پاک‌سازی می‌کند.
	$json    = isset( $_POST['package'] ) ? (string) wp_unslash( $_POST['package'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	$current = [
		'title'       => sanitize_text_field( (string) wp_unslash( $_POST['current_title'] ?? '' ) ),
		'description' => sanitize_textarea_field( (string) wp_unslash( $_POST['current_description'] ?? '' ) ),
	];

	wp_send_json_success( hodima_seo_pkg_check( $json, $subject, $current ) );
}
