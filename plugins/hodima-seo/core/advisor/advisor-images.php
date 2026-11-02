<?php
/**
 * ماژول «دستار سئو» — تصویرهای بدون alt: داده، گروه مشترک، علت رفع و پیشنهاد متن (SEO 3.4.9)
 * Path: core/advisor/advisor-images.php
 *
 * چرا: ایراد «تصویر بدون alt» alt خالی عمدی و نبود alt را یکی می‌گرفت، تصویر نامرئی (پاپ‌آپ نوتیفیکیشن) را هم می‌شمرد، یک تصویر
 * مشترک در ۲۲۰ صفحه ۲۲۰ کار می‌ساخت و نمی‌گفت alt را باید کجا نوشت. حالا خزنده هر تصویر دیده‌شونده را با آدرس، وضعیت alt و
 * جایگاهش در HTML ذخیره می‌کند (ستون `img_list` به شکل JSON) و این فایل از روی آن:
 *   • علامت‌های ادمین (`تزئینی است` / `alt می‌خواهد`) را نگه می‌دارد (گزینه hodima_advisor_img_marks؛ فقط ادمین دستی می‌زند)؛
 *   • تصویرهای مشترک بین چند صفحه را یک کار می‌کند؛
 *   • برای هر تصویر می‌گوید alt را کجا بنویسند (کتابخانه رسانه، متن همان صفحه، قالب/افزونه، بیرون از کتابخانه)؛
 *   • متن پیشنهادی alt می‌سازد (از عنوان محصول، عنوان پیوست یا نام فایل)؛ چیزی خودکار ذخیره نمی‌شود.
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/** علامت‌های ادمین روی تصویرها: کلید کوتاه آدرس ← `ok` (تزئینی) یا `need` (alt می‌خواهد)؛ autoload خاموش. */
const HODIMA_SEO_ADVISOR_IMG_MARKS_OPTION = 'hodima_advisor_img_marks';

/** سقف علامت‌ها (قدیمی‌ترین‌ها می‌افتند؛ هر کدام ≈۴۰ بایت). */
const HODIMA_SEO_ADVISOR_IMG_MARKS_MAX = 2000;

add_action( 'admin_post_hodima_advisor_img_mark', 'hodima_seo_advisor_handle_img_mark' );

/**
 * فهرست تصویرهای یک صفحه ← JSON ستون img_list (یک آدرس بلند یا ناجور فهرست را بزرگ نکند: سقف ۲۰٬۰۰۰ نویسه).
 *
 * @param list<array{u: string, s: string, c: string}> $images
 */
function hodima_seo_advisor_img_list_encode( array $images ): string {

	while ( $images ) {
		$json = (string) wp_json_encode( $images, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );

		if ( strlen( $json ) <= 20000 ) {
			return $json;
		}

		array_pop( $images );
	}

	return '';
}

/**
 * ستون img_list ← فهرست تصویرها. ردیف‌های پیش از ۳.۴.۹ یک آدرس در هر خط بودند (وضعیت ناشناخته ← `missing`).
 *
 * @return list<array{u: string, s: string, c: string}>
 */
function hodima_seo_advisor_img_list_decode( string $raw ): array {

	$raw = trim( $raw );

	if ( '' === $raw ) {
		return [];
	}

	if ( str_starts_with( $raw, '[' ) ) {
		$data = json_decode( $raw, true );
		$out  = [];

		foreach ( is_array( $data ) ? $data : [] as $item ) {
			if ( is_array( $item ) && isset( $item['s'] ) && in_array( $item['s'], [ 'missing', 'linked', 'empty' ], true ) ) {
				$out[] = [ 'u' => (string) ( $item['u'] ?? '' ), 's' => (string) $item['s'], 'c' => (string) ( $item['c'] ?? '' ) ];
			}
		}

		return $out;
	}

	return array_map( static fn( string $url ): array => [ 'u' => $url, 's' => 'missing', 'c' => '' ], array_values( array_filter( explode( "\n", $raw ) ) ) );
}

/** کلید کوتاه یک آدرس تصویر (برای علامت‌ها و فیلتر). */
function hodima_seo_advisor_img_key( string $url ): string {
	return substr( md5( $url ), 0, 12 );
}

/**
 * علامت‌های ادمین.
 *
 * @return array<string, string> کلید آدرس ← ok|need
 */
function hodima_seo_advisor_img_marks(): array {

	$saved = get_option( HODIMA_SEO_ADVISOR_IMG_MARKS_OPTION, [] );
	$out   = [];

	foreach ( is_array( $saved ) ? $saved : [] as $key => $mark ) {
		if ( is_string( $key ) && in_array( $mark, [ 'ok', 'need' ], true ) ) {
			$out[ $key ] = (string) $mark;
		}
	}

	return $out;
}

/**
 * علامت یک تصویر را می‌گذارد؛ `reset` برش می‌دارد.
 *
 * @param string $mark ok | need | reset
 */
function hodima_seo_advisor_img_mark_set( string $url, string $mark ): bool {

	if ( '' === $url || mb_strlen( $url ) > 300 || ! in_array( $mark, [ 'ok', 'need', 'reset' ], true ) ) {
		return false;
	}

	$marks = hodima_seo_advisor_img_marks();
	$key   = hodima_seo_advisor_img_key( $url );

	if ( 'reset' === $mark ) {
		unset( $marks[ $key ] );
	} else {
		unset( $marks[ $key ] );
		$marks[ $key ] = $mark; // تازه‌ترین آخر؛ سقف قدیمی‌ترین را می‌اندازد
	}

	update_option( HODIMA_SEO_ADVISOR_IMG_MARKS_OPTION, array_slice( $marks, -HODIMA_SEO_ADVISOR_IMG_MARKS_MAX, null, true ), false );
	hodima_seo_advisor_crawl_map( true ); // ایرادها از همین داده ساخته می‌شوند

	return true;
}

/**
 * فهرست تصویرهای یک صفحه را با علامت‌های ادمین سه‌تکه می‌کند:
 *   issues = ایراد (نبود alt، لینک بی‌نام، یا «alt می‌خواهد»)؛ review = alt خالی که هنوز تصمیمی برایش نیست؛ ok = «تزئینی است».
 *
 * @param list<array{u: string, s: string, c: string}> $entries
 * @return array{issues: list<array<string, mixed>>, review: list<array<string, mixed>>, ok: list<array<string, mixed>>}
 */
function hodima_seo_advisor_img_split( array $entries ): array {

	$marks  = hodima_seo_advisor_img_marks();
	$result = [ 'issues' => [], 'review' => [], 'ok' => [] ];

	foreach ( $entries as $entry ) {
		$mark = '' === $entry['u'] ? '' : ( $marks[ hodima_seo_advisor_img_key( $entry['u'] ) ] ?? '' );

		if ( 'empty' !== $entry['s'] ) {
			$result['issues'][] = $entry;
		} elseif ( 'ok' === $mark ) {
			$result['ok'][] = $entry;
		} elseif ( 'need' === $mark ) {
			$result['issues'][] = [ 'need' => true ] + $entry;
		} else {
			$result['review'][] = $entry;
		}
	}

	return $result;
}

/**
 * کش درخواست جاری گروه‌های تصویر (مرجع تا crawl_map(true) بتواند دورش بریزد).
 *
 * @return array<string, array{url: string, pages: list<string>, impressions: int, state: string, context: string}>|null
 */
function &hodima_seo_advisor_image_groups_cache(): ?array {
	static $cache = null;
	return $cache;
}

/**
 * تصویرهای مشترک بین صفحه‌ها (آدرس ← صفحه‌ها): فقط تصویر دارای آدرس و ایراد باز؛ صفحه‌ای که کار «تصویر بدون alt»‌اش
 * علامت‌خورده است نمی‌آید. بیشترین صفحه اول. «مشترک» = دست‌کم HODIMA_SEO_ADVISOR_BULK_MIN صفحه (همان مرز «کار کلی»).
 *
 * @return array<string, array{url: string, pages: list<string>, impressions: int, state: string, context: string}>
 */
function hodima_seo_advisor_image_groups(): array {

	global $wpdb;

	$cache = &hodima_seo_advisor_image_groups_cache();

	if ( null !== $cache ) {
		return $cache;
	}

	$rows = (array) $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->prepare(
			'SELECT c.path, c.img_list, COALESCE(p.impressions, 0) AS impressions FROM %i c LEFT JOIN %i p ON p.path = c.path WHERE c.status = 200 AND c.indexable = 1 AND c.img_noalt > 0',
			hodima_seo_advisor_table( 'crawl_pages' ),
			hodima_seo_advisor_table( 'pages' )
		),
		ARRAY_A
	);

	$groups = [];

	foreach ( $rows as $row ) {
		$path = (string) $row['path'];

		if ( null !== hodima_seo_advisor_task_closed( $path, 'crawl_img_alt' ) ) {
			continue;
		}

		foreach ( hodima_seo_advisor_img_split( hodima_seo_advisor_img_list_decode( (string) $row['img_list'] ) )['issues'] as $entry ) {
			if ( '' === $entry['u'] ) {
				continue;
			}

			$groups[ $entry['u'] ] ??= [ 'url' => $entry['u'], 'pages' => [], 'impressions' => 0, 'state' => $entry['s'], 'context' => (string) $entry['c'] ];

			if ( ! in_array( $path, $groups[ $entry['u'] ]['pages'], true ) ) {
				$groups[ $entry['u'] ]['pages'][]       = $path;
				$groups[ $entry['u'] ]['impressions']  += (int) $row['impressions'];
			}
		}
	}

	uasort( $groups, static fn( array $a, array $b ): int => [ count( $b['pages'] ), $b['impressions'] ] <=> [ count( $a['pages'] ), $a['impressions'] ] );

	$cache = $groups;

	return $groups;
}

/**
 * نام فایل تصویر ← متن خوانا برای alt (بدون پوشه، پسوند و اندازه `-300x300`؛ خط تیره ← فاصله)؛ خالی = نام بی‌معنا (IMG_1234).
 */
function hodima_seo_advisor_alt_from_name( string $name ): string {

	$name = rawurldecode( $name );
	$name = (string) preg_replace( [ '/\.[a-z0-9]{2,5}$/i', '/-(?:scaled|\d+x\d+)$/i', '/[-_+]+/' ], [ '', '', ' ' ], $name );
	$name = trim( (string) preg_replace( '/\s+/u', ' ', $name ) );

	if ( mb_strlen( $name ) < 3 || 1 === preg_match( '/^[\d\s]+$/', $name ) || 1 === preg_match( '/^(?:img|dsc|image|photo|pic|screenshot|whatsapp)\b[\s\d]*$/i', $name ) || ( 1 === preg_match( '/^[a-z0-9 ]+$/i', $name ) && 1 === preg_match( '/\d{3,}/', $name ) ) ) {
		return '';
	}

	return mb_substr( $name, 0, 125 );
}

/**
 * متن پیشنهادی alt (فقط پیشنهاد؛ ادمین می‌خواند، اصلاح و خودش ذخیره می‌کند): اگر تصویر شاخص یا گالری همین نوشته/محصول است
 * عنوان همان؛ وگرنه عنوان پیوست، وگرنه نام فایل. خالی = پیشنهادی نیست.
 */
function hodima_seo_advisor_alt_suggestion( string $url, int $attachment_id, string $page_path ): string {

	$class = '' === $page_path ? [ 'kind' => '', 'id' => 0 ] : hodima_seo_advisor_classify( $page_path );

	if ( $attachment_id > 0 && $class['id'] > 0 && post_type_exists( (string) $class['kind'] ) ) {
		$gallery = array_map( 'intval', array_filter( explode( ',', (string) get_post_meta( $class['id'], '_product_image_gallery', true ) ) ) );

		if ( (int) get_post_thumbnail_id( $class['id'] ) === $attachment_id || in_array( $attachment_id, $gallery, true ) ) {
			$title = trim( wp_strip_all_tags( get_the_title( $class['id'] ) ) );

			if ( '' !== $title ) {
				return mb_substr( $title, 0, 125 );
			}
		}
	}

	if ( $attachment_id > 0 ) {
		$title = hodima_seo_advisor_alt_from_name( trim( wp_strip_all_tags( get_the_title( $attachment_id ) ) ) );

		if ( '' !== $title ) {
			return $title;
		}
	}

	return hodima_seo_advisor_alt_from_name( basename( (string) wp_parse_url( $url, PHP_URL_PATH ) ) );
}

/** برچسب خوانای جایگاه در HTML برای بخش‌هایی که خود هدیما می‌سازد؛ خالی = ناشناخته (آنگاه همان شناسه/کلاس نشان داده می‌شود). */
function hodima_seo_advisor_img_context_label( string $context ): string {

	if ( str_contains( $context, 'hd-notif' ) ) {
		return 'پنجره نوتیفیکیشن (Hodima Media)';
	}

	if ( str_contains( $context, 'sp-gallery' ) ) {
		return 'گالری محصول قالب';
	}

	return '';
}

/**
 * علت و جای رفع یک تصویر بدون alt:
 *   none      آدرسی در HTML نیست (با جاوااسکریپت ساخته می‌شود) ← کاری از اینجا نمی‌شود؛
 *   library   پیوست کتابخانه است و alt کتابخانه خالی ← همان‌جا بنویسید (در همه صفحه‌ها اثر می‌کند)؛
 *   page      alt کتابخانه پر است ولی در متن همین صفحه کپی‌شده خالی ← ویرایش همین صفحه؛
 *   component alt کتابخانه پر است ولی صفحه خالی چاپ می‌کند و در متن صفحه نیست ← قالب یا افزونه alt را نمی‌خواند؛
 *   external  بیرون از کتابخانه (اسلایدر، صفحه‌ساز، CDN) ← آدرس را کپی کنید.
 *
 * @param array{u: string, s: string, c: string} $entry
 * @return array{case: string, url: string, name: string, id: int, lib_alt: string, edit: string, page_edit: string, thumb: string, suggest: string, where: string}
 */
function hodima_seo_advisor_img_fix_info( array $entry, string $path ): array {

	$url     = $entry['u'];
	$id      = '' === $url ? 0 : attachment_url_to_postid( $url );
	$lib_alt = $id > 0 ? trim( (string) get_post_meta( $id, '_wp_attachment_image_alt', true ) ) : '';
	$edit    = $id > 0 && current_user_can( 'edit_post', $id ) ? (string) get_edit_post_link( $id, 'raw' ) : '';
	$page_id = '' !== $path ? url_to_postid( home_url( $path ) ) : 0;
	$content = $page_id > 0 ? (string) get_post_field( 'post_content', $page_id ) : '';
	$stem    = (string) preg_replace( [ '/\.[a-z0-9]{2,5}$/i', '/-(?:scaled|\d+x\d+)$/i' ], '', rawurldecode( basename( (string) wp_parse_url( $url, PHP_URL_PATH ) ) ) );
	$baked   = '' !== $stem && '' !== $content && str_contains( $content, $stem );

	$case = match ( true ) {
		'' === $url                          => 'none',
		0 === $id                            => 'external',
		'' === $lib_alt                      => 'library',
		$baked                               => 'page',
		default                              => 'component',
	};

	$thumb = $id > 0 ? (string) wp_get_attachment_image_url( $id, 'thumbnail' ) : '';
	$label = hodima_seo_advisor_img_context_label( $entry['c'] );

	return [
		'case'      => $case,
		'url'       => $url,
		'name'      => '' === $url ? '' : ( $id > 0 ? rawurldecode( basename( (string) wp_parse_url( $url, PHP_URL_PATH ) ) ) : $url ),
		'id'        => $id,
		'lib_alt'   => $lib_alt,
		'edit'      => $edit,
		'page_edit' => $page_id > 0 && current_user_can( 'edit_post', $page_id ) ? (string) get_edit_post_link( $page_id, 'raw' ) : '',
		'thumb'     => '' !== $thumb ? $thumb : $url,
		'suggest'   => hodima_seo_advisor_alt_suggestion( $url, $id, $path ),
		'where'     => '' !== $label ? ( '' !== $entry['c'] ? $label . ' (' . $entry['c'] . ')' : $label ) : $entry['c'],
	];
}

/**
 * کنش «تزئینی است» / «alt می‌خواهد» / «برگرداندن» (admin-post؛ فقط ادمین، با nonce). علامت برای همه صفحه‌هایی است که همان
 * تصویر را دارند. برمی‌گردد به همان صفحه‌ای که ادمین در آن بود.
 */
function hodima_seo_advisor_handle_img_mark(): void {

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'اجازه این کار را ندارید.', 'دسترسی ندارید', [ 'response' => 403 ] );
	}

	check_admin_referer( 'hodima_advisor_img_mark' );

	$url  = isset( $_POST['url'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['url'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce بالا بررسی شد
	$mark = isset( $_POST['mark'] ) ? sanitize_key( wp_unslash( (string) $_POST['mark'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce بالا بررسی شد
	$back = wp_get_referer();
	$back = is_string( $back ) && '' !== $back ? $back : hodima_seo_advisor_page_url( [ 'tab' => 'overview' ] );

	if ( hodima_seo_advisor_img_mark_set( $url, $mark ) ) {
		hodima_admin_flash(
			match ( $mark ) {
				'ok'    => '<strong>ثبت شد:</strong> این تصویر «تزئینی» است و در هیچ صفحه‌ای ایراد حساب نمی‌شود.',
				'need'  => '<strong>ثبت شد:</strong> این تصویر به فهرست ایرادها رفت؛ برایش alt بنویسید.',
				default => '<strong>علامت برداشته شد:</strong> این تصویر دوباره در «بررسی کنید» می‌آید.',
			},
			'success'
		);
	} else {
		hodima_admin_flash( '<strong>ثبت نشد:</strong> درخواست نامعتبر بود.', 'error' );
	}

	wp_safe_redirect( $back );
	exit;
}
