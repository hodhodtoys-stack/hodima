<?php
/**
 * ماژول «دستار سئو» — گام‌های دقیق هر پیشنهاد (چه کنم، کجا، با چه کلمه‌ای)
 * Path: core/advisor/advisor-steps.php
 *
 * پیشنهاد کلی («لینک بدهید») کافی نیست؛ مدیر باید بداند کدام صفحه و کدام عبارت. این فایل به
 * هر پیشنهاد فهرست «گام» اضافه می‌کند (کلید `steps`). فقط کادر ویرایش یک صفحه آن را می‌سازد و نشان می‌دهد
 * (hodima_seo_advisor_analyze( $path, true ))؛ از SEO 2.9.9 در جدول ذخیره نمی‌شود، چون ساختنش برای همه آدرس‌ها در
 * تحلیل روزانه حدود ۹۸٪ زمان آن بود و فهرست پیشخوان هم آن را نشان نمی‌داد:
 *
 *   title      (نرخ کلیک کم)      عنوان و توضیح سئوی فعلی با تعداد نویسه، و عنوان پیشنهادی (پیش‌نویس:
 *                                  کلمه اصلی + نام صفحه؛ توضیح متا را خودکار نمی‌نویسیم)
 *   headings   (کلمه در تیتر/متن نیست) تیترهای فعلی صفحه و ایده تیتر از کلمه‌های واقعی گوگل همین صفحه
 *   links      (کلمه نزدیک صفحه اول) «از این صفحه‌ها لینک بدهید»: صفحه‌هایی که متنشان آن عبارت را دارد
 *                                  و هنوز به این صفحه لینک ندارند، قوی‌ترین (بیشترین نمایش گوگل) اول؛
 *                                  حداکثر ۵ مورد و «+N صفحه دیگر»
 *   canonical  (رقابت داخلی)       صفحه‌های رقیب برای آن کلمه با رتبه و نمایش و صفحه مرجع پیشنهادی
 *
 * فقط خواندن؛ چیزی را عوض نمی‌کند. جستجوی لینک از متن خام صفحه‌ها (محتوای نوشته/برگه/محصول و
 * توضیح دسته‌ها) است، پس فقط عبارتی را پیدا می‌کند که واقعا نوشته شده.
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/** حداکثر صفحه پیشنهادی برای لینک دادن. */
const HODIMA_SEO_ADVISOR_LINK_SOURCES = 5;

/** حداکثر ایده تیتر. */
const HODIMA_SEO_ADVISOR_HEADING_IDEAS = 3;

/** طول عنوان سئوی پیشنهادی (نویسه). */
const HODIMA_SEO_ADVISOR_TITLE_MAX = 60;

/**
 * نام، عنوان سئو و توضیح متای یک آدرس.
 *
 * @param array<string, mixed> $row
 * @return array{name: string, title: string, description: string, custom_description: bool, headings: list<string>}|null
 */
function hodima_seo_advisor_seo_fields( array $row ): ?array {

	$id   = (int) $row['object_id'];
	$kind = (string) $row['kind'];

	if ( $id <= 0 || '' === $kind ) {
		return null;
	}

	if ( taxonomy_exists( $kind ) ) {
		$term = get_term( $id, $kind );
		if ( ! $term instanceof WP_Term ) {
			return null;
		}
		$name     = $term->name;
		$html     = $term->description;
		$title    = (string) get_term_meta( $id, '_seobox_title', true );
		$custom   = (string) get_term_meta( $id, '_seobox_description', true );
		$fallback = function_exists( 'seobox_term_fallback_description' ) ? seobox_term_fallback_description( $term ) : '';
	} elseif ( post_type_exists( $kind ) ) {
		$post = get_post( $id );
		if ( ! $post instanceof WP_Post ) {
			return null;
		}
		$name     = $post->post_title;
		$html     = $post->post_content;
		$title    = (string) get_post_meta( $id, '_seobox_title', true );
		$custom   = (string) get_post_meta( $id, '_seobox_description', true );
		$fallback = function_exists( 'seobox_post_fallback_description' ) ? seobox_post_fallback_description( $post ) : '';
	} else {
		return null;
	}

	preg_match_all( '#<h[2-4][^>]*>(.*?)</h[2-4]>#isu', strip_shortcodes( $html ), $found );

	$text = static fn( string $value ): string => trim( html_entity_decode( wp_strip_all_tags( $value ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );

	return [
		'name'               => $text( $name ),
		'title'              => $text( $title ),
		'description'        => '' !== trim( $custom ) ? $text( $custom ) : $text( $fallback ),
		'custom_description' => '' !== trim( $custom ),
		'headings'           => array_values( array_filter( array_map( $text, $found[1] ), static fn( string $h ): bool => '' !== $h ) ),
	];
}

/**
 * گام «عنوان و توضیح» برای نرخ کلیک کم.
 *
 * @param array<string, mixed> $fields خروجی hodima_seo_advisor_seo_fields()
 * @return array<string, mixed>
 */
function hodima_seo_advisor_step_title( array $fields, string $term ): array {

	$current = '' !== $fields['title'] ? $fields['title'] : $fields['name'];
	$has     = '' !== $term && hodima_seo_advisor_text_has( hodima_seo_advisor_normalize( $current ), $term );
	$starts  = $has && str_starts_with( hodima_seo_advisor_normalize( $current ), hodima_seo_advisor_normalize( $term ) );
	$tip     = '';
	$suggest = '';

	if ( '' === $term ) {
		$tip = 'کلمه اصلی این صفحه هنوز از گوگل معلوم نیست.';
	} elseif ( $starts ) {
		$tip = 'عنوان فعلی با کلمه اصلی شروع می‌شود؛ بیشتر روی جذاب بودن آن (تعداد، ویژگی، «عمده») فکر کنید.';
	} elseif ( $has ) {
		$tip = 'کلمه اصلی در عنوان هست ولی ابتدایش نیست؛ آن را به ابتدای عنوان ببرید.';
	} else {
		$suggest = mb_substr( $term . ' | ' . $fields['name'], 0, HODIMA_SEO_ADVISOR_TITLE_MAX );
		$tip     = 'کلمه اصلی در عنوان نیست؛ عنوان پیشنهادی پیش‌نویس است و خودتان آن را کامل کنید.';
	}

	$description = (string) $fields['description'];
	$desc_len    = mb_strlen( $description );
	$desc_tip    = match ( true ) {
		! $fields['custom_description'] => 'توضیح متا ندارید و گوگل از ابتدای متن صفحه می‌سازد؛ یک توضیح ۱۲۰ تا ۱۶۰ نویسه‌ای بنویسید که کلمه اصلی را داشته باشد.',
		$desc_len < 70                  => 'توضیح متا کوتاه است؛ تا حدود ۱۲۰ تا ۱۶۰ نویسه کامل کنید.',
		$desc_len > 170                 => 'توضیح متا بلند است و بریده می‌شود؛ به حدود ۱۶۰ نویسه برسانید.',
		'' !== $term && ! hodima_seo_advisor_text_has( hodima_seo_advisor_normalize( $description ), $term ) => 'کلمه اصلی در توضیح متا نیست؛ آن را در جمله اول بیاورید.',
		default                         => '',
	};

	return [
		'type'        => 'title',
		'term'        => $term,
		'current'     => $current,
		'current_len' => mb_strlen( $current ),
		'custom'      => '' !== $fields['title'],
		'suggested'   => $suggest,
		'tip'         => $tip,
		'meta'        => [ 'text' => $description, 'len' => $desc_len, 'custom' => $fields['custom_description'], 'tip' => $desc_tip ],
	];
}

/**
 * گام «تیترها»: تیترهای فعلی و ایده تیتر از کلمه‌های واقعی گوگل (اولین ایده خود کلمه است).
 *
 * @param list<string> $headings
 * @param list<array{term: string, impressions: int}> $queries
 * @return array<string, mixed>
 */
function hodima_seo_advisor_step_headings( array $headings, string $term, array $queries ): array {

	$haystack = hodima_seo_advisor_normalize( implode( ' ', $headings ) );
	$ideas    = [];

	foreach ( [ $term, ...array_column( $queries, 'term' ) ] as $candidate ) {
		if ( '' !== $candidate && ! in_array( $candidate, $ideas, true ) && ! hodima_seo_advisor_text_has( $haystack, $candidate ) ) {
			$ideas[] = $candidate;
		}
		if ( count( $ideas ) >= HODIMA_SEO_ADVISOR_HEADING_IDEAS ) {
			break;
		}
	}

	return [ 'type' => 'headings', 'term' => $term, 'current' => array_slice( $headings, 0, 8 ), 'ideas' => $ideas ];
}

/**
 * صفحه‌هایی که متنشان عبارت را دارد و هنوز به صفحه هدف لینک ندارند، قوی‌ترین اول.
 *
 * پیش‌فیلتر SQL با بلندترین واژه عبارت، سپس تایید با متن یکسان‌شده (همه واژه‌ها). «قوی» = بیشترین
 * نمایش گوگل (جدول آدرس‌ها). نتیجه: حداکثر `$limit` مورد و تعداد بقیه.
 *
 * @param array<string, mixed> $row صفحه هدف
 * @return array{items: list<array{id: int, kind: string, title: string, impressions: int}>, more: int}
 */
function hodima_seo_advisor_link_sources( string $term, array $row, int $limit = HODIMA_SEO_ADVISOR_LINK_SOURCES ): array {

	global $wpdb;

	$tokens = array_filter( explode( ' ', hodima_seo_advisor_normalize( $term ) ), static fn( string $t ): bool => mb_strlen( $t ) >= 2 );

	if ( ! $tokens ) {
		return [ 'items' => [], 'more' => 0 ];
	}

	usort( $tokens, static fn( string $a, string $b ): int => mb_strlen( $b ) <=> mb_strlen( $a ) );

	$like   = '%' . $wpdb->esc_like( $tokens[0] ) . '%';
	$arabic = '%' . $wpdb->esc_like( strtr( $tokens[0], [ 'ی' => 'ي', 'ک' => 'ك' ] ) ) . '%'; // متن‌هایی که با ی/ک عربی نوشته شده‌اند (پیش‌فیلتر قبل از یکسان‌سازی)
	$target = rtrim( (string) $row['path'], '/' );
	$links  = '#' . preg_quote( $target, '#' ) . '/?(?:["\'\s?\#]|$)#ui'; // نشانی هدف در href (حروف بزرگ و کوچک یکی)
	$found  = [];

	$types = array_values( array_intersect( [ 'post', 'page', 'product' ], hodima_seo_advisor_box_post_types_all() ) );

	if ( $types ) {
		$posts = (array) $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare( // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- ۴ placeholder ثابت + array_fill هم‌اندازه نوع‌ها
				'SELECT ID, post_type, post_title, post_content, post_excerpt FROM %i WHERE post_status = %s AND post_type IN (' . implode( ',', array_fill( 0, count( $types ), '%s' ) ) . ') AND ID <> %d AND (post_content LIKE %s OR post_content LIKE %s OR post_excerpt LIKE %s OR post_excerpt LIKE %s) ORDER BY post_modified DESC LIMIT 200', // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- تعداد placeholderها: ۴ ثابت + array_fill هم‌اندازه نوع‌ها
				$wpdb->posts,
				'publish',
				...$types,
				...[ 'post' === (string) $row['kind'] || post_type_exists( (string) $row['kind'] ) ? (int) $row['object_id'] : 0, $like, $arabic, $like, $arabic ]
			),
			ARRAY_A
		);
		foreach ( $posts as $post ) {
			$html = strip_shortcodes( (string) $post['post_content'] . ' ' . (string) $post['post_excerpt'] );
			if ( hodima_seo_advisor_text_has( hodima_seo_advisor_normalize( $html ), $term ) && 1 !== preg_match( $links, $html ) && 1 !== preg_match( $links, rawurldecode( $html ) ) ) {
				$found[] = [ 'id' => (int) $post['ID'], 'kind' => (string) $post['post_type'], 'title' => trim( wp_strip_all_tags( (string) $post['post_title'] ) ), 'url' => (string) get_permalink( (int) $post['ID'] ) ];
			}
		}
	}

	$terms = (array) $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->prepare(
			'SELECT t.term_id, tt.taxonomy, t.name, tt.description FROM %i tt INNER JOIN %i t ON t.term_id = tt.term_id WHERE tt.taxonomy IN (%s, %s) AND tt.term_id <> %d AND (tt.description LIKE %s OR tt.description LIKE %s) LIMIT 100',
			$wpdb->term_taxonomy,
			$wpdb->terms,
			'product_cat',
			'category',
			taxonomy_exists( (string) $row['kind'] ) ? (int) $row['object_id'] : 0,
			$like,
			$arabic
		),
		ARRAY_A
	);
	foreach ( $terms as $entry ) {
		$html = (string) $entry['description'];
		if ( taxonomy_exists( (string) $entry['taxonomy'] ) && hodima_seo_advisor_text_has( hodima_seo_advisor_normalize( $html ), $term ) && 1 !== preg_match( $links, $html ) && 1 !== preg_match( $links, rawurldecode( $html ) ) ) {
			$link = get_term_link( (int) $entry['term_id'], (string) $entry['taxonomy'] );
			if ( is_string( $link ) ) {
				$found[] = [ 'id' => (int) $entry['term_id'], 'kind' => (string) $entry['taxonomy'], 'title' => trim( wp_strip_all_tags( (string) $entry['name'] ) ), 'url' => $link ];
			}
		}
	}

	if ( ! $found ) {
		return [ 'items' => [], 'more' => 0 ];
	}

	// قدرت هر صفحه: نمایش گوگل
	$paths  = array_map( static fn( array $f ): string => (string) hodima_seo_advisor_path_key( $f['url'] ), $found );
	$powers = [];
	foreach ( array_chunk( array_values( array_unique( array_filter( $paths ) ) ), 100 ) as $chunk ) {
		foreach ( (array) $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare( 'SELECT path, impressions FROM %i WHERE path IN (' . implode( ',', array_fill( 0, count( $chunk ), '%s' ) ) . ')', hodima_seo_advisor_table( 'pages' ), ...$chunk ), // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- تعداد placeholderها با array_fill هم‌اندازه آدرس‌هاست
			ARRAY_A
		) as $power ) {
			$powers[ (string) $power['path'] ] = (int) $power['impressions'];
		}
	}

	$items = [];
	foreach ( $found as $i => $f ) {
		$items[] = [ 'id' => $f['id'], 'kind' => $f['kind'], 'title' => $f['title'], 'impressions' => $powers[ $paths[ $i ] ] ?? 0 ];
	}

	usort( $items, static fn( array $a, array $b ): int => [ $b['impressions'], $a['title'] ] <=> [ $a['impressions'], $b['title'] ] );

	return [ 'items' => array_slice( $items, 0, $limit ), 'more' => max( 0, count( $items ) - $limit ) ];
}

/**
 * hodima_seo_advisor_link_sources با کش کوتاه: باز کردن کادر ویرایش هر بار چند
 * جستجوی LIKE روی متن همه نوشته‌ها می‌زد. کلید شامل «آخرین تغییر نوشته‌ها و دسته‌ها» و زمان آخرین دریافت داده
 * است؛ با هر ویرایش یا دریافت تازه خودکار نو می‌شود.
 *
 * @param array<string, mixed> $row
 * @return array{items: list<array{id: int, kind: string, title: string, impressions: int}>, more: int}
 */
function hodima_seo_advisor_link_sources_cached( string $term, array $row ): array {

	$key    = 'hodima_adv_ls_' . md5( implode( '|', [ $term, (string) $row['path'], wp_cache_get_last_changed( 'posts' ), wp_cache_get_last_changed( 'terms' ), (string) hodima_seo_advisor_status()['fetched'] ] ) );
	$cached = get_transient( $key );

	if ( is_array( $cached ) && isset( $cached['items'], $cached['more'] ) ) {
		return $cached;
	}

	$fresh = hodima_seo_advisor_link_sources( $term, $row );
	set_transient( $key, $fresh, 10 * MINUTE_IN_SECONDS );

	return $fresh;
}

/**
 * نوع‌های نوشته‌ای که متن‌شان برای لینک‌دهی جستجو می‌شود (همان نوع‌های سئوباکس).
 *
 * @return list<string>
 */
function hodima_seo_advisor_box_post_types_all(): array {
	$types = function_exists( 'seobox_post_types' ) ? seobox_post_types() : [ 'post', 'page', 'product' ];
	return array_values( array_filter( (array) $types, static fn( mixed $t ): bool => is_string( $t ) && post_type_exists( $t ) ) );
}

/**
 * آدرس‌هایی که برای یک کلمه نمایش می‌گیرند (برای انتخاب صفحه مرجع).
 *
 * @return list<array{path: string, title: string, kind: string, object_id: int, impressions: int, clicks: int, position: float}>
 */
function hodima_seo_advisor_term_pages( string $term, int $limit = 5 ): array {

	global $wpdb;

	$rows = (array) $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->prepare(
			'SELECT q.path, q.impressions, q.clicks, q.avg_position, p.title, p.kind, p.object_id FROM %i q LEFT JOIN %i p ON p.path = q.path WHERE q.term_key = %s AND q.impressions >= %d ORDER BY q.impressions DESC LIMIT %d',
			hodima_seo_advisor_table( 'queries' ),
			hodima_seo_advisor_table( 'pages' ),
			hodima_seo_advisor_term_key( $term ),
			hodima_seo_advisor_config()['cannibal_min_impressions'],
			$limit
		),
		ARRAY_A
	);

	return array_map(
		static fn( array $r ): array => [
			'path'        => (string) $r['path'],
			'title'       => (string) $r['title'],
			'kind'        => (string) $r['kind'],
			'object_id'   => (int) $r['object_id'],
			'impressions' => (int) $r['impressions'],
			'clicks'      => (int) $r['clicks'],
			'position'    => (float) $r['avg_position'],
		],
		$rows
	);
}

/**
 * صفحه‌هایی که برای یک کلمه واقعا رقیب‌اند، برنده اول: بدون نادیده‌گرفته‌ها، صفحه‌بندی آرشیو و صفحه‌های حل‌شده
 * (ریدایرکت، ۴۰۴، noindex، صفحه اصلی؛ hodima_seo_advisor_resolved_paths). برنده همان قاعده تب «صفحه‌های هم‌کلمه»:
 * بیشترین کلیک ← رتبه بهتر ← نمایش بیشتر. کادر ویرایش، تب رقابت و «لینک‌های این کلمه» همه از همین می‌خوانند
 * (تا SEO 2.9.9 کادر ویرایش «صفحه مرجع» را با بهترین رتبه و بدون کنار گذاشتن صفحه‌های حل‌شده انتخاب می‌کرد و برای
 * یک کلمه صفحه اصلی دیگری از تب رقابت پیشنهاد می‌داد، گاهی حتی صفحه‌ای که ریدایرکت می‌شد).
 *
 * @return list<array{path: string, title: string, kind: string, object_id: int, impressions: int, clicks: int, position: float}>
 */
function hodima_seo_advisor_term_rivals( string $term, int $limit = 5 ): array {

	$ignored  = hodima_seo_advisor_ignored();
	$resolved = hodima_seo_advisor_resolved_paths();
	$pages    = array_values( array_filter(
		hodima_seo_advisor_term_pages( $term, $limit + 5 ),
		static fn( array $p ): bool => ! in_array( $p['path'], $ignored, true ) && 1 !== preg_match( '#/page/\d+/?$#', $p['path'] ) && ! isset( $resolved[ $p['path'] ] )
	) );

	usort( $pages, 'hodima_seo_advisor_winner_order' );

	return array_slice( $pages, 0, $limit );
}

/**
 * ترتیب برنده یک گروه رقابت (برای usort): بیشترین کلیک ← رتبه بهتر ← نمایش بیشتر.
 *
 * @param array{clicks: int, position: float, impressions: int} $a
 * @param array{clicks: int, position: float, impressions: int} $b
 */
function hodima_seo_advisor_winner_order( array $a, array $b ): int {
	return [ $b['clicks'], $a['position'], $b['impressions'] ] <=> [ $a['clicks'], $b['position'], $a['impressions'] ];
}

/**
 * گام «رقابت داخلی»: صفحه‌های رقیب برای کلمه و صفحه مرجع پیشنهادی (همان برنده تب رقابت).
 *
 * @param list<array{path: string, title: string, kind: string, object_id: int, impressions: int, clicks: int, position: float}> $pages
 * @return array<string, mixed>
 */
function hodima_seo_advisor_step_canonical( string $term, array $pages, string $current_path ): array {

	usort( $pages, 'hodima_seo_advisor_winner_order' );

	return [
		'type'    => 'canonical',
		'term'    => $term,
		'pages'   => $pages,
		'current' => $current_path,
		'main'    => (string) ( $pages[0]['path'] ?? '' ),
		'is_main' => ( $pages[0]['path'] ?? '' ) === $current_path,
	];
}

/**
 * گام‌ها را به پیشنهادها اضافه می‌کند (کلید steps).
 *
 * @param list<array<string, mixed>> $suggestions
 * @param array<string, mixed> $row
 * @param list<array{term: string, clicks: int, impressions: int, position: float}> $queries
 * @return list<array<string, mixed>>
 */
function hodima_seo_advisor_enrich( array $suggestions, array $row, array $queries ): array {

	if ( ! $suggestions ) {
		return [];
	}

	$fields = hodima_seo_advisor_seo_fields( $row );
	$top    = $queries[0]['term'] ?? '';

	foreach ( $suggestions as $i => $item ) {
		$term  = (string) $item['term'];
		$steps = [];

		switch ( $item['rule'] ) {
			case 'low_ctr':
				if ( null !== $fields ) {
					$steps[] = hodima_seo_advisor_step_title( $fields, $top );
				}
				break;
			case 'missing_headings':
			case 'missing_content':
				if ( null !== $fields ) {
					$steps[] = hodima_seo_advisor_step_headings( $fields['headings'], $term, $queries );
				}
				$steps[] = [ 'type' => 'links' ] + hodima_seo_advisor_link_sources_cached( $term, $row ) + [ 'term' => $term ];
				break;
			case 'near_first':
				$steps[] = [ 'type' => 'links' ] + hodima_seo_advisor_link_sources_cached( $term, $row ) + [ 'term' => $term ];
				break;
			case 'cannibal':
				$steps[] = hodima_seo_advisor_step_canonical( $term, hodima_seo_advisor_term_rivals( $term ), (string) $row['path'] );
				break;
		}

		$suggestions[ $i ]['steps'] = $steps;
	}

	return $suggestions;
}
