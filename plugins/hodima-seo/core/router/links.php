<?php
/**
 * Hodima Router — جستجوی لینک‌های داخلی قدیمی و خراب (فقط گزارش)
 * Path: core/router/links.php
 *
 * چرا: آدرس‌های قدیمی با ۳۰۱ به جای درست می‌روند، ولی لینک‌هایی که داخل خود سایت
 * هنوز به آدرس قدیمی اشاره می‌کنند هر بار یک ریدایرکت اضافه دارند، و لینک
 * خراب یا بدشکل (مثل /charms/)(https://…/charms/ که سرچ کنسول پیدا کرد) گوگل را
 * به آدرس‌های بی‌معنی می‌برد. این فایل آن لینک‌ها را با جای دقیقشان پیدا می‌کند.
 *
 * منابع: متن و توضیح کوتاه نوشته/برگه/محصول منتشرشده، توضیح دسته محصول و دسته
 * نوشته، «پیوند دلخواه» منوها، و مقدارهای تنظیمات قالب. کار دسته‌دسته با
 * admin-ajax (router-links.js) تا صفحه کند نشود؛ هیچ چیزی ذخیره یا عوض نمی‌شود
 * — اصلاح با مدیر سایت است (لینک «ویرایش» هر ردیف).
 * وضعیت هر لینک فقط با hodima_router_inspect_url() (report.php).
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

const HODIMA_ROUTER_LINKS_BATCH = 40;

/** ترتیب منابع جستجو. */
const HODIMA_ROUTER_LINK_SOURCES = [ 'posts', 'terms', 'menus', 'settings' ];

/**
 * لینک‌های یک متن HTML: مقدار href ها و آدرس‌های خام همین سایت در متن.
 * آدرس خام لازم است: لینک بدشکل معمولا متنی است که اشتباه چسبانده شده.
 *
 * @return list<string>
 */
function hodima_router_links_in_html( string $html ): array {

	if ( '' === trim( $html ) ) {
		return [];
	}

	$found = [];

	if ( preg_match_all( '/\bhref\s*=\s*(["\'])(.*?)\1/isu', $html, $matches ) ) {
		$found = $matches[2];
	}

	$host = (string) preg_replace( '/^www\./i', '', (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
	if ( '' !== $host && preg_match_all( '#https?://(?:www\.)?' . preg_quote( $host, '#' ) . '(?:[/?\#][^\s"\'<>\[\]]*)?#iu', $html, $matches ) ) {
		$found = array_merge( $found, $matches[0] );
	}

	$links = [];
	foreach ( $found as $link ) {

		$link = trim( html_entity_decode( (string) $link, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
		$link = rtrim( $link, '.,;:!?،؛' );

		// پرانتز بسته آخر جمله، نه بخشی از آدرس
		while ( str_ends_with( $link, ')' ) && substr_count( $link, ')' ) > substr_count( $link, '(' ) ) {
			$link = substr( $link, 0, -1 );
		}

		if ( '' !== $link ) {
			$links[ $link ] = true;
		}
	}

	return array_map( 'strval', array_keys( $links ) );
}

/**
 * آدرس کامل یک لینک اگر به صفحه‌ای از همین سایت اشاره می‌کند، وگرنه null
 * (لینک بیرونی، #، mailto/tel، فایل‌ها و مسیرهای سیستمی وردپرس).
 */
function hodima_router_link_absolute( string $link ): ?string {

	if ( '' === $link || str_starts_with( $link, '#' ) || preg_match( '/^(mailto|tel|javascript|data|sms|whatsapp):/i', $link ) ) {
		return null;
	}

	if ( str_starts_with( $link, '//' ) ) {
		$link = 'https:' . $link;
	} elseif ( str_starts_with( $link, '/' ) ) {
		$link = home_url( $link );
	} elseif ( ! preg_match( '#^https?://#i', $link ) ) {
		return null; // آدرس نسبی بدون / (به صفحه جاری بستگی دارد)
	}

	$path = hodima_router_url_path( $link );
	if ( null === $path ) {
		return null; // دامنه دیگر
	}

	if ( preg_match( '#^(wp-content|wp-includes|wp-admin|wp-json)(/|$)#i', $path ) || str_ends_with( $path, '.php' ) ) {
		return null;
	}

	// فایل (تصویر، pdf…): بخش آخر پسوند دارد؛ آدرس بدشکل نه (پسوندش داخل مسیر است)
	$last = (string) substr( (string) strrchr( '/' . $path, '/' ), 1 );
	if ( preg_match( '/\.[a-z0-9]{2,5}$/i', $last ) ) {
		return null;
	}

	return $link;
}

/** آیا آدرس بدشکل است (آدرس دوم یا پرانتز چسبیده داخل مسیر، فاصله)؟ */
function hodima_router_link_malformed( string $url ): bool {
	$path = rawurldecode( (string) wp_parse_url( $url, PHP_URL_PATH ) );
	return (bool) preg_match( '#https?:/|www\.|\)\(|[()\s<>"]#iu', $path );
}

/**
 * مشکل یک لینک، یا null اگر سالم است (آدرس اصلی، صفحه اصلی، برچسب/فید/جستجو…).
 *
 * issue: malformed | broken | old
 *
 * @return array{issue:string, check:array<string, mixed>, final:string}|null
 */
function hodima_router_link_issue( string $url ): ?array {

	static $cache = [];

	if ( array_key_exists( $url, $cache ) ) {
		return $cache[ $url ];
	}

	$check = hodima_router_inspect_url( $url );
	$key   = hodima_router_check_key( $check );
	$final = hodima_router_final_url( $check );

	$issue = match ( true ) {
		hodima_router_link_malformed( $url )                                => 'malformed',
		in_array( $key, [ 'missing', 'rule_broken', 'rule_gone' ], true )  => 'broken',
		in_array( $key, [ 'redirect', 'rule_ok', 'rule_chain', 'rule_external' ], true ) => 'old',
		default                                                            => null,
	};

	$cache[ $url ] = null === $issue ? null : [ 'issue' => $issue, 'check' => $check, 'final' => $final ];

	return $cache[ $url ];
}

/**
 * ردیف‌های گزارش برای لینک‌های یک متن.
 *
 * @param array{where:string, edit:string, field:string} $place
 * @return list<array<string, string>>
 */
function hodima_router_link_rows( string $html, array $place ): array {

	$rows = [];

	foreach ( hodima_router_links_in_html( $html ) as $link ) {

		$url = hodima_router_link_absolute( $link );
		if ( null === $url ) {
			continue;
		}

		$problem = hodima_router_link_issue( $url );
		if ( null === $problem ) {
			continue;
		}

		$rows[] = $place + [
			'link'  => rawurldecode( $link ),
			'issue' => $problem['issue'],
			'pill'  => hodima_router_link_pill( $problem['issue'] ),
			'fix'   => hodima_router_link_fix_text( $problem ),
		];
	}

	return $rows;
}

/** نشان نوع مشکل لینک. */
function hodima_router_link_pill( string $issue ): string {

	[ $class, $text ] = match ( $issue ) {
		'malformed' => [ 'error', 'بدشکل' ],
		'broken'    => [ 'error', 'خراب (۴۰۴)' ],
		default     => [ 'warn', 'قدیمی (ریدایرکت می‌شود)' ],
	};

	return sprintf( '<span class="hds-pill hds-pill--%s">%s</span>', esc_attr( $class ), esc_html( $text ) );
}

/**
 * راهنمای اصلاح یک لینک (HTML امن).
 *
 * @param array{issue:string, check:array<string, mixed>, final:string} $problem
 */
function hodima_router_link_fix_text( array $problem ): string {

	$final = $problem['final'];
	$code  = static fn( string $url ): string => '<code dir="ltr">' . esc_html( rawurldecode( $url ) ) . '</code>';

	if ( 'broken' === $problem['issue'] ) {
		return 'این لینک به صفحه‌ای می‌رسد که وجود ندارد. لینک را حذف کنید یا به صفحه مرتبط دیگری بدهید.';
	}

	if ( 'malformed' === $problem['issue'] ) {
		return 'آدرس اشتباه نوشته شده (مثلا دو آدرس به هم چسبیده‌اند).'
			. ( '' !== $final ? ' احتمالا منظور این بوده: ' . $code( $final ) : ' لینک را درست بنویسید یا حذف کنید.' );
	}

	return '' !== $final
		? 'لینک را به آدرس اصلی عوض کنید: ' . $code( $final )
		: 'این لینک ریدایرکت می‌شود؛ آدرس فعلی صفحه را جایش بگذارید.';
}

/**
 * نام و لینک ویرایش یک نوشته/برگه/محصول.
 *
 * @return array{where:string, edit:string}
 */
function hodima_router_link_place_post( WP_Post $post ): array {

	$title = '' !== $post->post_title ? $post->post_title : '#' . $post->ID;

	return [
		'where' => sprintf( '<span class="hds-pill hds-pill--info">%s</span> %s', esc_html( hodima_router_type_label( $post->post_type ) ), esc_html( $title ) ),
		'edit'  => (string) get_edit_post_link( $post->ID, 'raw' ),
	];
}

/**
 * یک دسته از یک منبع.
 *
 * @return array{rows:list<array<string, string>>, cursor:int, end:bool, done:int, total:int, label:string}
 */
function hodima_router_links_batch( string $source, int $cursor ): array {

	global $wpdb;

	$rows = [];

	switch ( $source ) {

		case 'posts':
			$types = hodima_router_post_types();
			if ( ! $types ) {
				return [ 'rows' => [], 'cursor' => 0, 'end' => true, 'done' => 0, 'total' => 0, 'label' => 'نوشته‌ها، برگه‌ها و محصولات' ];
			}
			$in    = implode( ',', array_fill( 0, count( $types ), '%s' ) );
			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.DirectDatabaseQuery -- placeholders پویای IN در $in؛ فقط خواندن در پیشخوان
			$total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_type IN ($in)", ...$types ) );
			$done  = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_type IN ($in) AND ID <= %d", ...array_merge( $types, [ $cursor ] ) ) );
			$ids   = array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_type IN ($in) AND ID > %d ORDER BY ID ASC LIMIT %d",
				...array_merge( $types, [ $cursor, HODIMA_ROUTER_LINKS_BATCH ] )
			) ) );
			// phpcs:enable

			foreach ( $ids as $id ) {
				$post = get_post( $id );
				if ( ! $post instanceof WP_Post ) {
					continue;
				}
				$place = hodima_router_link_place_post( $post );
				$rows  = array_merge(
					$rows,
					hodima_router_link_rows( (string) $post->post_content, $place + [ 'field' => 'متن' ] ),
					hodima_router_link_rows( (string) $post->post_excerpt, $place + [ 'field' => 'product' === $post->post_type ? 'توضیح کوتاه' : 'خلاصه' ] )
				);
				$cursor = $id;
			}

			return [
				'rows'   => $rows,
				'cursor' => $cursor,
				'end'    => count( $ids ) < HODIMA_ROUTER_LINKS_BATCH,
				'done'   => min( $total, $done + count( $ids ) ),
				'total'  => $total,
				'label'  => 'نوشته‌ها، برگه‌ها و محصولات',
			];

		case 'terms':
			$terms = get_terms( [ 'taxonomy' => hodima_router_taxonomies(), 'hide_empty' => false ] );
			$terms = is_array( $terms ) ? array_filter( $terms, static fn( $term ): bool => $term instanceof WP_Term && '' !== trim( $term->description ) ) : [];

			foreach ( $terms as $term ) {
				$rows = array_merge( $rows, hodima_router_link_rows( $term->description, [
					'where' => sprintf( '<span class="hds-pill hds-pill--info">%s</span> %s', esc_html( hodima_router_type_label( $term->taxonomy ) ), esc_html( $term->name ) ),
					'edit'  => (string) get_edit_term_link( $term->term_id, $term->taxonomy ),
					'field' => 'توضیح دسته',
				] ) );
			}

			return [ 'rows' => $rows, 'cursor' => 0, 'end' => true, 'done' => count( $terms ), 'total' => count( $terms ), 'label' => 'توضیح دسته‌ها' ];

		case 'menus':
			$items = get_posts( [
				'post_type'   => 'nav_menu_item',
				'post_status' => 'publish',
				'numberposts' => -1,
				'meta_key'    => '_menu_item_type', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_query_meta_key -- یک بار در پیشخوان
				'meta_value'  => 'custom', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_query_meta_value
			] );

			foreach ( $items as $item ) {
				$menus = wp_get_object_terms( $item->ID, 'nav_menu' );
				$menu  = is_array( $menus ) && isset( $menus[0] ) && $menus[0] instanceof WP_Term ? $menus[0] : null;
				$link  = (string) get_post_meta( $item->ID, '_menu_item_url', true );
				$rows  = array_merge( $rows, hodima_router_link_rows( '<a href="' . esc_attr( $link ) . '"></a>', [
					'where' => sprintf( '<span class="hds-pill hds-pill--info">منو</span> %s ← %s', esc_html( null !== $menu ? $menu->name : '—' ), esc_html( '' !== $item->post_title ? $item->post_title : $link ) ),
					'edit'  => null !== $menu ? admin_url( 'nav-menus.php?action=edit&menu=' . $menu->term_id ) : admin_url( 'nav-menus.php' ),
					'field' => 'پیوند دلخواه',
				] ) );
			}

			return [ 'rows' => $rows, 'cursor' => 0, 'end' => true, 'done' => count( $items ), 'total' => count( $items ), 'label' => 'منوها' ];

		case 'settings':
			/*
			 * تنظیمات قالب فقط از توابع خود قالب (افزونه گزینه را مستقیم نمی‌خواند)
			 * و فقط فیلدهای «لینک» و «متن»: «فهرست آدرس» (مثل صفحه‌هایی که نوار
			 * شبکه‌های اجتماعی پنهان است: /product/*) الگو است نه لینک، و «کد» اسکریپت.
			 */
			$settings = function_exists( 'hodima_settings' ) ? (array) hodima_settings() : [];
			$fields   = function_exists( 'hodima_settings_fields' ) ? (array) hodima_settings_fields() : [];
			$edit     = function_exists( 'hodima_settings_url' ) ? (string) hodima_settings_url() : '';
			$values   = 0;

			foreach ( $settings as $key => $value ) {
				$type = (string) ( $fields[ $key ]['type'] ?? '' );
				if ( ! is_string( $value ) || ! in_array( $type, [ 'url', 'text', 'textarea' ], true ) || ! preg_match( '#(https?://|href=|^/)#i', $value ) ) {
					continue;
				}
				++$values;
				$html  = str_contains( $value, '<' ) || 'url' !== $type ? $value : '<a href="' . esc_attr( $value ) . '"></a>';
				$label = (string) ( $fields[ $key ]['label'] ?? $key );
				$rows  = array_merge( $rows, hodima_router_link_rows( $html, [
					'where' => '<span class="hds-pill hds-pill--info">تنظیمات قالب</span> ' . esc_html( $label ),
					'edit'  => $edit,
					'field' => (string) $key,
				] ) );
			}

			return [ 'rows' => $rows, 'cursor' => 0, 'end' => true, 'done' => $values, 'total' => $values, 'label' => 'تنظیمات قالب' ];
	}

	return [ 'rows' => [], 'cursor' => 0, 'end' => true, 'done' => 0, 'total' => 0, 'label' => '' ];
}

add_action( 'wp_ajax_hodima_router_links', 'hodima_router_links_ajax' );

/** یک درخواست از جستجوی دسته‌ای (router-links.js). */
function hodima_router_links_ajax(): void {

	if ( ! current_user_can( 'manage_options' ) || ! check_ajax_referer( 'hodima_router_links', 'nonce', false ) ) {
		wp_send_json_error( [ 'message' => 'دسترسی ندارید یا صفحه منقضی شده است؛ صفحه را دوباره باز کنید.' ], 403 );
	}

	$source = sanitize_key( wp_unslash( (string) ( $_POST['source'] ?? '' ) ) );
	$cursor = absint( wp_unslash( (string) ( $_POST['cursor'] ?? '0' ) ) );

	if ( ! in_array( $source, HODIMA_ROUTER_LINK_SOURCES, true ) ) {
		$source = HODIMA_ROUTER_LINK_SOURCES[0];
		$cursor = 0;
	}

	$batch = hodima_router_links_batch( $source, $cursor );
	$index = (int) array_search( $source, HODIMA_ROUTER_LINK_SOURCES, true );
	$next  = $batch['end'] ? ( HODIMA_ROUTER_LINK_SOURCES[ $index + 1 ] ?? '' ) : $source;

	wp_send_json_success( [
		'rows'   => $batch['rows'],
		'label'  => $batch['label'],
		'done'   => $batch['done'],
		'total'  => $batch['total'],
		'source' => $next,
		'cursor' => $batch['end'] ? 0 : $batch['cursor'],
		'end'    => '' === $next,
	] );
}
