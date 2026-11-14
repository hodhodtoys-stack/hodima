<?php
/**
 * ورود بسته — بررسی بسته (فقط خواندن)
 * Path: core/package-import/package-import-check.php
 *
 * بسته JSON را می‌خواند، قفل آدرس را می‌سنجد، آدرس‌ها را به شناسه وردپرس برمی‌گرداند و برای
 * هر کادر مقدار تازه را به شکلی که جاوااسکریپت مستقیم در فرم می‌گذارد آماده می‌کند. هیچ
 * چیزی در پایگاه داده نوشته نمی‌شود: ذخیره فقط با دکمه «به‌روزرسانی» و کد ذخیره خود هر
 * کادر است (قانون ماژول). آدرس پیدانشده فقط همان مورد را کنار می‌گذارد، نه کل بسته.
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/**
 * بررسی کامل یک بسته برای یک شیء (نوشته یا ترم).
 *
 * @param array{title?: string, description?: string} $current مقدار فعلی فرم سئوباکس (برای سنجش پیکسلی «فعلی»)
 * @return array{ok: bool, errors: list<string>, meta: array<string, string>, lock: array<string, mixed>, boxes: array<string, array{value: mixed, warnings: list<string>}>, ignored: list<string>}
 */
function hodima_seo_pkg_check( string $json, WP_Post|WP_Term $subject, array $current = [] ): array {

	$out = [ 'ok' => false, 'errors' => [], 'meta' => [], 'lock' => [], 'boxes' => [], 'ignored' => [] ];

	if ( strlen( $json ) > HODIMA_SEO_PKG_MAX_BYTES ) {
		$out['errors'][] = 'بسته بزرگ‌تر از یک مگابایت است.';
		return $out;
	}

	try {
		$data = json_decode( $json, true, 64, JSON_THROW_ON_ERROR );
	} catch ( JsonException $e ) {
		$out['errors'][] = 'متن بسته JSON معتبر نیست: ' . $e->getMessage();
		return $out;
	}

	if ( ! is_array( $data ) || array_is_list( $data ) ) {
		$out['errors'][] = 'بسته باید یک شیء JSON باشد ({ … }).';
		return $out;
	}

	$format = is_string( $data['format'] ?? null ) ? $data['format'] : '';
	$type   = is_string( $data['type'] ?? null ) ? $data['type'] : '';
	$url    = is_string( $data['url'] ?? null ) ? trim( $data['url'] ) : '';

	$out['meta'] = [
		'format' => $format,
		'type'   => $type,
		'url'    => $url,
		'date'   => is_string( $data['date'] ?? null ) ? sanitize_text_field( $data['date'] ) : '',
		'note'   => is_string( $data['note'] ?? null ) ? sanitize_textarea_field( $data['note'] ) : '',
	];

	$types     = hodima_seo_pkg_types();
	$page_type = hodima_seo_pkg_type_for_object( $subject );
	$spec      = null !== $page_type ? $types[ $page_type ] : null;

	if ( ! in_array( $format, [ HODIMA_SEO_PKG_FORMAT, HODIMA_SEO_PKG_FORMAT_V2 ], true ) ) {
		$out['errors'][] = sprintf( 'قالب بسته «%s» یا «%s» نیست (در بسته: «%s»).', HODIMA_SEO_PKG_FORMAT_V2, HODIMA_SEO_PKG_FORMAT, $format );
	} elseif ( null !== $spec && $type === $page_type && ! in_array( $format, $spec['formats'], true ) ) {
		$out['errors'][] = sprintf( 'نوع «%s» فقط با قالب «%s» می‌آید (در بسته: «%s»).', $type, HODIMA_SEO_PKG_FORMAT_V2, $format );
	}

	if ( null === $spec || $type !== $page_type ) {
		$out['errors'][] = sprintf( 'نوع بسته «%s» است ولی این صفحه «%s» (%s) است.', $type, null !== $spec ? $spec['label'] : '—', (string) $page_type );
	}

	// قفل آدرس: بسته یک صفحه دیگر هرگز این صفحه را پر نکند
	$page_url    = hodima_seo_pkg_page_url( $subject );
	$out['lock'] = [
		'package' => $url,
		'page'    => $page_url,
		'id'      => $subject instanceof WP_Term ? $subject->term_id : $subject->ID,
		'match'   => '' !== $url && '' !== $page_url && hodima_seo_pkg_url_key( $url ) === hodima_seo_pkg_url_key( $page_url ),
	];
	if ( '' === $url ) {
		$out['errors'][] = 'بسته کلید "url" ندارد.';
	} elseif ( ! $out['lock']['match'] ) {
		$out['errors'][] = 'آدرس بسته با آدرس این صفحه یکی نیست؛ هیچ خانه‌ای پر نمی‌شود.';
	}

	$boxes = $data['boxes'] ?? null;
	if ( ! is_array( $boxes ) || ( [] !== $boxes && array_is_list( $boxes ) ) ) {
		$out['errors'][] = 'بسته کلید "boxes" (شیء کادرها) ندارد.';
	}

	if ( $out['errors'] || null === $spec ) {
		return $out;
	}

	$known  = $spec['boxes'];
	$labels = hodima_seo_pkg_box_labels( (string) $page_type );
	$skip   = hodima_seo_pkg_out_of_scope( (string) $page_type );

	foreach ( (array) $boxes as $key => $value ) {
		$key = (string) $key;
		if ( ! isset( $known[ $key ] ) ) {
			$out['ignored'][] = isset( $skip[ $key ] )
				? sprintf( '«%s» (%s) برای %s در ورود بسته نیست؛ دست نخورد.', $skip[ $key ], $key, $spec['label'] )
				: sprintf( 'کادر «%s» برای %s شناخته نشد؛ دست نخورد.', $key, $spec['label'] );
			continue;
		}

		$warnings = [];
		$normal   = hodima_seo_pkg_box( $known[ $key ], $value, $subject, $warnings, $current );

		if ( null === $normal ) {
			$out['ignored'][] = sprintf( 'مقدار «%s» شکل درستی ندارد؛ این کادر دست نخورد.', $labels[ $key ] ?? $key );
			continue;
		}

		$out['boxes'][ $key ] = [ 'value' => $normal, 'warnings' => $warnings ];
	}

	$out['ok'] = true;
	return $out;
}

/**
 * نرمال‌ساز یک کادر (با نام آداپتور). null یعنی شکل مقدار نامعتبر است (کادر دست نمی‌خورد).
 * آرایه خالی [] برای هر کادر یعنی «خالی کن».
 *
 * @param list<string>                                 $warnings
 * @param array{title?: string, description?: string} $current
 */
function hodima_seo_pkg_box( string $adapter, mixed $value, WP_Post|WP_Term $subject, array &$warnings, array $current ): mixed {

	// متن‌های HTML (بدنه هم) و دکمه محصول می‌توانند رشته ساده یا null هم باشند
	if ( in_array( $adapter, [ 'body', 'intro_html', 'description_html' ], true ) && ( is_string( $value ) || null === $value ) ) {
		$value = [ 'html' => (string) $value ];
	}
	if ( 'product_button' === $adapter && ( is_string( $value ) || null === $value ) ) {
		$value = '' === trim( (string) $value ) ? [] : [ 'url' => (string) $value ];
	}

	if ( ! is_array( $value ) ) {
		return null;
	}

	return match ( $adapter ) {
		'body', 'intro_html', 'description_html' => hodima_seo_pkg_body( $value, $warnings ),
		'upsells'        => $subject instanceof WP_Post ? hodima_seo_pkg_products( $value, $subject, $warnings, 6 ) : null,
		'cross_sells'    => $subject instanceof WP_Post ? hodima_seo_pkg_products( $value, $subject, $warnings, 0 ) : null,
		'table'          => hodima_seo_pkg_table( $value, $warnings ),
		'faq'            => hodima_seo_pkg_qa_list( $value, $warnings, 'پرسش' ),
		'related_links'  => hodima_seo_pkg_related( $value, $subject, $warnings ),
		'topic_cluster'  => hodima_seo_pkg_cluster( $value, $subject, $warnings ),
		'aeo'            => hodima_seo_pkg_aeo( $value, $warnings, $subject instanceof WP_Term ),
		'seobox'         => hodima_seo_pkg_seobox( $value, $warnings, $current ),
		'video'          => hodima_seo_pkg_fields( $value, $warnings, HODIMA_SEO_PKG_MEDIA_VIDEO ),
		'podcast'        => hodima_seo_pkg_fields( $value, $warnings, HODIMA_SEO_PKG_MEDIA_PODCAST ),
		'video_watch'    => hodima_seo_pkg_fields( $value, $warnings, HODIMA_SEO_PKG_VIDEO_WATCH ),
		'discover'       => hodima_seo_pkg_discover( $value, $warnings ),
		'suggestions'    => hodima_seo_pkg_suggestions( $value, $warnings ),
		'product_button' => hodima_seo_pkg_product_button( $value, $warnings ),
		default          => null,
	};
}

/* =====================================================================
 * آدرس‌ها
 * ===================================================================== */

/** آدرس فعلی صفحه؛ پیش‌نویس هنوز آدرس نهایی ندارد، پس آدرس نمونه همان نامک. ترم: آدرس دسته. */
function hodima_seo_pkg_page_url( WP_Post|WP_Term $subject ): string {

	if ( $subject instanceof WP_Term ) {
		$link = get_term_link( $subject );
		return is_string( $link ) ? $link : '';
	}

	if ( in_array( $subject->post_status, [ 'draft', 'pending', 'auto-draft', 'future' ], true ) && function_exists( 'get_sample_permalink' ) ) {
		[ $template, $name ] = get_sample_permalink( $subject->ID );
		return str_replace( [ '%pagename%', '%postname%' ], (string) $name, (string) $template );
	}

	return (string) get_permalink( $subject );
}

/**
 * کلید مقایسه آدرس: میزبان بدون www + مسیر رمزگشایی‌شده با یک اسلش انتهایی؛ بدون http/https،
 * پرسش و #. (https://hodhodli.com/103 و http://www.hodhodli.com/103/ یکی‌اند.)
 */
function hodima_seo_pkg_url_key( string $url ): string {

	$parts = wp_parse_url( trim( $url ) );
	if ( ! is_array( $parts ) ) {
		return '';
	}

	$host = (string) preg_replace( '/^www\./', '', strtolower( (string) ( $parts['host'] ?? '' ) ) );
	$path = '/' . trim( rawurldecode( (string) ( $parts['path'] ?? '' ) ), '/' ) . '/';
	$path = '//' === $path ? '/' : $path;

	return $host . $path;
}

/** آیا آدرس مال همین سایت است؟ */
function hodima_seo_pkg_is_internal( string $url ): bool {
	$key  = hodima_seo_pkg_url_key( $url );
	$home = hodima_seo_pkg_url_key( home_url( '/' ) );
	$host = strstr( $home, '/', true );
	return '' !== $key && false !== $host && str_starts_with( $key, $host . '/' );
}

/**
 * آدرس کامل ← نوشته یا ترم. آدرس محصول‌ها عددی است و با شناسه فرق دارد (/103/ = #690)،
 * پس هرگز عدد آدرس شناسه حساب نمی‌شود: اول روتر آدرس تمیز، بعد حل‌کننده لینک‌های مرتبط
 * (نامک + تطبیق آدرس واقعی)، بعد url_to_postid خود وردپرس.
 *
 * @return array{kind: string, id: int}|null
 */
function hodima_seo_pkg_resolve( string $url ): ?array {

	if ( ! hodima_seo_pkg_is_internal( $url ) ) {
		return null;
	}

	if ( function_exists( 'hodima_router_resolve_url' ) ) {
		$match = hodima_router_resolve_url( $url );
		if ( is_array( $match ) && in_array( $match['kind'] ?? '', [ 'post', 'term' ], true ) && (int) ( $match['id'] ?? 0 ) > 0 ) {
			return [ 'kind' => (string) $match['kind'], 'id' => (int) $match['id'] ];
		}
	}

	if ( class_exists( \Hodima\RelatedLinks\Store::class ) ) {
		$found = \Hodima\RelatedLinks\Store::resolve_url( $url );
		if ( is_array( $found ) ) {
			return [ 'kind' => (string) $found['kind'], 'id' => (int) $found['id'] ];
		}
	}

	$post_id = url_to_postid( $url );
	return $post_id > 0 ? [ 'kind' => 'post', 'id' => $post_id ] : null;
}

/**
 * فهرست آدرس‌ها (رشته) با هشدار برای مورد نامعتبر.
 *
 * @param list<string> $warnings
 * @return list<string>
 */
function hodima_seo_pkg_url_list( mixed $urls, array &$warnings ): array {

	if ( ! is_array( $urls ) ) {
		return [];
	}

	$list = [];
	foreach ( $urls as $url ) {
		if ( ! is_string( $url ) || '' === trim( $url ) ) {
			$warnings[] = 'یک مورد فهرست آدرس نبود و کنار گذاشته شد.';
			continue;
		}
		$list[] = trim( $url );
	}
	return $list;
}

/* =====================================================================
 * کادرها
 * ===================================================================== */

/**
 * متن HTML (بدنه، متن معرفی، توضیحات دسته) با شورت‌کدها. هشدار (نه توقف): لینک tel: غیر از
 * شماره‌های سایت، سرتیترهای H1، H4، H5، H6 و لینک به سایت دیگر. HTML (مثل <details>) دست نمی‌خورد.
 *
 * @param array<mixed> $value
 * @param list<string> $warnings
 * @return array{html: string}|null
 */
function hodima_seo_pkg_body( array $value, array &$warnings ): ?array {

	if ( [] === $value ) {
		return [ 'html' => '' ];
	}
	if ( ! is_string( $value['html'] ?? null ) ) {
		return null;
	}

	$html = $value['html'];

	foreach ( hodima_seo_pkg_bad_phones( $html ) as $phone ) {
		$warnings[] = sprintf( 'شماره tel:%s جزو شماره‌های سایت نیست.', $phone );
	}

	if ( preg_match_all( '/<h([1456])\b/i', $html, $m ) ) {
		$counts = array_count_values( $m[1] );
		ksort( $counts );
		foreach ( $counts as $level => $count ) {
			$warnings[] = sprintf( 'سرتیتر H%d در متن (%s مورد)؛ برای متن صفحه فقط H2 و H3 پیشنهاد می‌شود.', $level, number_format_i18n( $count ) );
		}
	}

	$external = hodima_seo_pkg_external_hosts( $html );
	if ( $external ) {
		$warnings[] = sprintf( 'لینک به سایت دیگر: %s.', implode( '، ', $external ) );
	}

	return [ 'html' => $html ];
}

/**
 * میزبان‌های لینک‌های بیرونی متن (href با http/https که مال همین سایت نیست؛ هر میزبان یک بار).
 *
 * @return list<string>
 */
function hodima_seo_pkg_external_hosts( string $html ): array {

	if ( ! preg_match_all( '/<a\b[^>]*\bhref\s*=\s*["\']\s*(https?:\/\/[^"\'\s>]+)/iu', $html, $m ) ) {
		return [];
	}

	$hosts = [];
	foreach ( $m[1] as $href ) {
		if ( ! hodima_seo_pkg_is_internal( $href ) ) {
			$host = strtolower( (string) wp_parse_url( $href, PHP_URL_HOST ) );
			if ( '' !== $host ) {
				$hosts[ $host ] = true;
			}
		}
	}
	return array_keys( $hosts );
}

/**
 * شماره‌های لینک tel: که جزو شماره‌های سایت نیستند (ارقام فارسی/عربی و +98 یکدست می‌شوند).
 *
 * @return list<string>
 */
function hodima_seo_pkg_bad_phones( string $html ): array {

	if ( ! preg_match_all( '/tel:([^"\'\s<>]+)/iu', $html, $m ) ) {
		return [];
	}

	$allowed = hodima_seo_pkg_phones();
	$bad     = [];
	foreach ( $m[1] as $raw ) {
		$digits = (string) preg_replace( '/\D+/', '', strtr( rawurldecode( $raw ), [
			'۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
			'٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
		] ) );
		$digits = str_starts_with( $digits, '0098' ) ? '0' . substr( $digits, 4 ) : ( str_starts_with( $digits, '98' ) ? '0' . substr( $digits, 2 ) : $digits );
		if ( ! in_array( $digits, $allowed, true ) ) {
			$bad[] = $raw;
		}
	}
	return array_values( array_unique( $bad ) );
}

/**
 * محصولات پیوند شده (upsell / cross-sell): آدرس ← شناسه + نام همان شکلی که جستجوی ووکامرس
 * در فهرست نشان می‌دهد.
 *
 * @param array<mixed> $value
 * @param list<string> $warnings
 * @return list<array{id: int, title: string, url: string}>|null
 */
function hodima_seo_pkg_products( array $value, WP_Post $post, array &$warnings, int $max ): ?array {

	if ( [] === $value ) {
		return [];
	}
	if ( ! array_key_exists( 'urls', $value ) || ! is_array( $value['urls'] ) ) {
		return null;
	}

	$items = [];
	foreach ( hodima_seo_pkg_url_list( $value['urls'], $warnings ) as $url ) {

		$target = hodima_seo_pkg_resolve( $url );
		$id     = $target && 'post' === $target['kind'] ? $target['id'] : 0;

		if ( ! $id || 'product' !== get_post_type( $id ) ) {
			$warnings[] = sprintf( '%s محصولی پیدا نشد؛ فقط همین مورد کنار گذاشته شد.', $url );
			continue;
		}
		if ( $id === $post->ID ) {
			$warnings[] = sprintf( '%s خود همین محصول است؛ کنار گذاشته شد.', $url );
			continue;
		}
		if ( isset( $items[ $id ] ) ) {
			$warnings[] = sprintf( '%s تکراری بود؛ یک بار آمد.', $url );
			continue;
		}
		if ( 'publish' !== get_post_status( $id ) ) {
			$warnings[] = sprintf( '%s منتشر نشده است (در سایت دیده نمی‌شود).', $url );
		}

		$items[ $id ] = [ 'id' => $id, 'title' => hodima_seo_pkg_product_label( $id ), 'url' => $url ];
	}

	if ( $max > 0 && count( $items ) > $max ) {
		$warnings[] = sprintf( 'بیشتر از %s محصول آمده بود؛ فقط %s مورد اول ماند.', number_format_i18n( $max ), number_format_i18n( $max ) );
		$items      = array_slice( $items, 0, $max, true );
	}

	return array_values( $items );
}

/** نام محصول مثل فهرست جستجوی ووکامرس («نام (کد)»). */
function hodima_seo_pkg_product_label( int $id ): string {
	$product = function_exists( 'wc_get_product' ) ? wc_get_product( $id ) : null;
	if ( $product instanceof WC_Product ) {
		return rawurldecode( wp_strip_all_tags( $product->get_formatted_name() ) );
	}
	return get_the_title( $id ) . ' (#' . $id . ')';
}

/**
 * جدول تکمیلی: سرستون‌ها و ردیف‌ها؛ ردیف کوتاه با خانه خالی پر می‌شود. سقف همان سقف جدول
 * (۲۰ ستون، ۱۰۰ ردیف).
 *
 * @param array<mixed> $value
 * @param list<string> $warnings
 * @return array{headers: list<string>, rows: list<list<string>>}|null
 */
function hodima_seo_pkg_table( array $value, array &$warnings ): ?array {

	if ( [] === $value ) {
		return [ 'headers' => [], 'rows' => [] ];
	}

	$headers = $value['headers'] ?? [];
	$rows    = $value['rows'] ?? [];
	if ( ! is_array( $headers ) || ! is_array( $rows ) ) {
		return null;
	}

	$cell    = static fn( mixed $v ): string => is_scalar( $v ) ? (string) $v : '';
	$headers = array_map( $cell, array_values( $headers ) );
	$clean   = [];
	foreach ( $rows as $row ) {
		if ( ! is_array( $row ) ) {
			$warnings[] = 'یک ردیف جدول آرایه نبود و کنار گذاشته شد.';
			continue;
		}
		$clean[] = array_map( $cell, array_values( $row ) );
	}

	$width = max( 1, count( $headers ), ...array_map( 'count', $clean ?: [ [] ] ) );
	if ( $width > 20 || count( $clean ) > 100 ) {
		$warnings[] = 'جدول از سقف ۲۰ ستون و ۱۰۰ ردیف بزرگ‌تر بود و بریده شد.';
		$width      = min( $width, 20 );
		$clean      = array_slice( $clean, 0, 100 );
	}

	$fit  = static fn( array $r ): array => array_map( static fn( int $c ): string => $r[ $c ] ?? '', range( 0, $width - 1 ) );
	$rows = array_map( $fit, $clean );

	$seen = [];
	foreach ( $rows as $i => $row ) {
		$key = mb_strtolower( trim( implode( '|', $row ) ) );
		if ( '|' !== $key && '' !== trim( $key, '|' ) && isset( $seen[ $key ] ) ) {
			$warnings[] = sprintf( 'ردیف %s جدول تکرار ردیف %s است.', number_format_i18n( $i + 1 ), number_format_i18n( $seen[ $key ] + 1 ) );
		}
		$seen[ $key ] ??= $i;
	}

	return [ 'headers' => $fit( $headers ), 'rows' => $rows ];
}

/**
 * فهرست پرسش و پاسخ ({q, a}). پرسش بی‌پاسخ (یا برعکس) پر می‌شود ولی هشدار می‌گیرد، چون کد
 * ذخیره همان کادر آن را نگه نمی‌دارد.
 *
 * @param array<mixed> $value
 * @param list<string> $warnings
 * @return list<array{q: string, a: string}>|null
 */
function hodima_seo_pkg_qa_list( mixed $value, array &$warnings, string $label ): ?array {

	if ( ! is_array( $value ) || ( [] !== $value && ! array_is_list( $value ) ) ) {
		return null;
	}

	$list = [];
	$seen = [];
	foreach ( $value as $i => $item ) {
		if ( ! is_array( $item ) ) {
			$warnings[] = sprintf( '%s %s شکل {q, a} ندارد و کنار گذاشته شد.', $label, number_format_i18n( $i + 1 ) );
			continue;
		}
		$q = is_scalar( $item['q'] ?? null ) ? trim( (string) $item['q'] ) : '';
		$a = is_scalar( $item['a'] ?? null ) ? trim( (string) $item['a'] ) : '';
		if ( '' === $q || '' === $a ) {
			$warnings[] = sprintf( '%s %s پرسش یا پاسخ خالی دارد؛ هنگام ذخیره نگه داشته نمی‌شود.', $label, number_format_i18n( $i + 1 ) );
		}
		$key = mb_strtolower( (string) preg_replace( '/[\s؟?!.،,]+/u', '', $q ) );
		if ( '' !== $key && isset( $seen[ $key ] ) ) {
			$warnings[] = sprintf( '%s %s تکرار %s %s است.', $label, number_format_i18n( $i + 1 ), $label, number_format_i18n( $seen[ $key ] + 1 ) );
		}
		$seen[ $key ] ??= $i;
		foreach ( hodima_seo_pkg_bad_phones( $a ) as $phone ) {
			$warnings[] = sprintf( 'پاسخ %s %s: شماره tel:%s جزو شماره‌های سایت نیست.', $label, number_format_i18n( $i + 1 ), $phone );
		}
		$list[] = [ 'q' => $q, 'a' => $a ];
	}
	return $list;
}

/**
 * لینک‌های مرتبط: گروه «پیشنهاد خرید» (products) و «مقاله پیشنهادی» (articles در بسته،
 * article در کادر). هر آدرس ← همان مورد جستجوی کادر (عنوان، تصویر، هشدار).
 *
 * @param array<mixed> $value
 * @param list<string> $warnings
 * @return array{products: list<array<string, mixed>>, article: list<array<string, mixed>>}
 */
function hodima_seo_pkg_related( array $value, WP_Post|WP_Term $subject, array &$warnings ): array {

	if ( ! class_exists( \Hodima\RelatedLinks\Admin::class ) ) {
		$warnings[] = 'ماژول «لینک‌های مرتبط دستی» روشن نیست؛ این کادر پر نمی‌شود.';
		return [ 'products' => [], 'article' => [] ];
	}

	$groups = [ 'products' => 'products', 'articles' => 'article' ];
	$out    = [ 'products' => [], 'article' => [] ];

	foreach ( $groups as $in => $group ) {
		$enum  = \Hodima\RelatedLinks\Group::from( $group );
		$scope = \Hodima\RelatedLinks\Store::search_scope( $enum );

		foreach ( hodima_seo_pkg_url_list( $value[ $in ] ?? [], $warnings ) as $url ) {
			$target = hodima_seo_pkg_resolve( $url );
			if ( ! $target || 'post' !== $target['kind'] || ! in_array( get_post_type( $target['id'] ), $scope, true ) ) {
				$warnings[] = sprintf( '%s برای «%s» پیدا نشد؛ فقط همین مورد کنار گذاشته شد.', $url, $enum->label() );
				continue;
			}
			if ( in_array( $target['id'], array_column( $out[ $group ], 'id' ), true ) ) {
				$warnings[] = sprintf( '%s در «%s» تکراری بود؛ یک بار آمد.', $url, $enum->label() );
				continue;
			}
			$item = $subject instanceof WP_Term
				? \Hodima\RelatedLinks\Admin::package_item( $target['id'], $subject->term_id, 'term' )
				: \Hodima\RelatedLinks\Admin::package_item( $target['id'], $subject->ID );
			if ( null === $item ) {
				$warnings[] = sprintf( '%s را نمی‌شود لینک داد؛ کنار گذاشته شد.', $url );
				continue;
			}
			$out[ $group ][] = $item;
		}
	}

	return $out;
}

/**
 * خوشه‌بندی محتوا: پیلار، خارج از خوشه، دسته پیلار والد (parent_term) و برای مقاله «محتوای
 * ستون» (parent_post_url: مقاله یا برگه پیلار). null یعنی خالی (والد خودکار). محصول و دسته
 * والد نوشته‌ای ندارند، پس آنجا parent_post_url فقط هشدار.
 *
 * @param array<mixed> $value
 * @param list<string> $warnings
 * @return array{pillar: bool, exclude: bool, parents: list<array{id: int, title: string}>, post_parents: list<array{id: int, title: string}>}
 */
function hodima_seo_pkg_cluster( array $value, WP_Post|WP_Term $subject, array &$warnings ): array {

	$out = [ 'pillar' => ! empty( $value['pillar'] ), 'exclude' => ! empty( $value['exclude'] ), 'parents' => [], 'post_parents' => [] ];

	if ( ! class_exists( \Hodima\TopicCluster\Graph::class ) ) {
		$warnings[] = 'ماژول «خوشه‌های موضوعی» روشن نیست؛ این کادر پر نمی‌شود.';
		return $out;
	}

	$is_term  = $subject instanceof WP_Term;
	$ref      = $is_term ? \Hodima\TopicCluster\Ref::term( $subject->term_id ) : \Hodima\TopicCluster\Ref::post( $subject->ID );
	$taxonomy = $is_term ? $subject->taxonomy : \Hodima\TopicCluster\Graph::parent_taxonomy_for( $subject->post_type );
	$label    = static fn( \Hodima\TopicCluster\Ref $r, string $title ): string => class_exists( \Hodima\TopicCluster\Admin\Editor::class )
		? \Hodima\TopicCluster\Admin\Editor::label_for( $r, $title )
		: $title;
	$term_url = $value['parent_term'] ?? null;

	if ( is_string( $term_url ) && '' !== trim( $term_url ) ) {
		$target = hodima_seo_pkg_resolve( $term_url );
		$term   = $target && 'term' === $target['kind'] ? get_term( $target['id'] ) : null;

		if ( ! $term instanceof WP_Term || $term->taxonomy !== $taxonomy ) {
			$warnings[] = sprintf( 'دسته والد %s پیدا نشد؛ والد خالی ماند (خودکار).', $term_url );
		} elseif ( $is_term && $term->term_id === $subject->term_id ) {
			$warnings[] = 'دسته نمی‌تواند والد خودش باشد؛ والد خالی ماند.';
		} elseif ( ! \Hodima\TopicCluster\Graph::is_pillar( \Hodima\TopicCluster\Ref::term( $term->term_id ) ) ) {
			$warnings[] = sprintf( 'دسته «%s» پیلار نیست و والد نمی‌شود؛ والد خالی ماند.', $term->name );
		} else {
			$out['parents'][] = [ 'id' => (int) $term->term_id, 'title' => $label( \Hodima\TopicCluster\Ref::term( $term->term_id ), $term->name ) ];
		}
	} elseif ( null !== $term_url && ! is_string( $term_url ) ) {
		$warnings[] = 'parent_term باید آدرس دسته یا null باشد؛ والد خالی ماند.';
	}

	$post_url = $value['parent_post_url'] ?? null;
	if ( is_string( $post_url ) && '' !== trim( $post_url ) ) {
		if ( $is_term || ! \Hodima\TopicCluster\Graph::accepts_post_parents( $ref ) ) {
			$warnings[] = sprintf( '%s والد مقاله‌ای (parent_post_url) نمی‌گیرد؛ این مورد نادیده گرفته شد.', $is_term ? 'دسته' : 'محصول' );
		} else {
			$target = hodima_seo_pkg_resolve( $post_url );
			$id     = $target && 'post' === $target['kind'] ? $target['id'] : 0;
			if ( ! $id || ! in_array( get_post_type( $id ), \Hodima\TopicCluster\Graph::post_pillar_types(), true ) ) {
				$warnings[] = sprintf( 'مقاله پیلار والد %s پیدا نشد؛ «محتوای ستون» خالی ماند.', $post_url );
			} elseif ( $id === $subject->ID ) {
				$warnings[] = 'مقاله نمی‌تواند والد خودش باشد؛ «محتوای ستون» خالی ماند.';
			} elseif ( ! \Hodima\TopicCluster\Graph::is_pillar( \Hodima\TopicCluster\Ref::post( $id ) ) ) {
				$warnings[] = sprintf( '«%s» پیلار نیست و والد نمی‌شود؛ «محتوای ستون» خالی ماند.', get_the_title( $id ) );
			} else {
				$out['post_parents'][] = [ 'id' => $id, 'title' => $label( \Hodima\TopicCluster\Ref::post( $id ), get_the_title( $id ) ) ];
			}
		}
	}

	if ( $out['exclude'] && ( $out['parents'] || $out['post_parents'] ) ) {
		$warnings[] = '«خارج از خوشه» روشن است؛ والد ذخیره می‌شود ولی اثری ندارد.';
	}

	return $out;
}

/**
 * پنل AEO: کل کادر (جز رسانه‌ها) جایگزین می‌شود؛ کلید نیامده = خانه خالی (یا پیش‌فرض همان
 * خانه، مثل مبنای قیمت). خروجی: نام خانه فرم ← مقدار.
 *
 * @param array<mixed> $value
 * @param list<string> $warnings
 * @return array<string, mixed>
 */
function hodima_seo_pkg_aeo( array $value, array &$warnings, bool $is_term = false ): array {

	$text   = static fn( mixed $v ): string => is_scalar( $v ) ? trim( (string) $v ) : '';
	$fields = [];

	foreach ( hodima_seo_pkg_aeo_text_fields() as $key => $name ) {
		$fields[ $name ] = $text( $value[ $key ] ?? '' );
	}

	$before = count( $warnings ); // هشدارهای بخش فروش؛ برای دسته که این بخش را ندارد برداشته می‌شوند
	$model  = $text( $value['biz_model'] ?? 'both' );
	if ( ! in_array( $model, [ 'both', 'tier_a', 'tier_b' ], true ) ) {
		$warnings[] = sprintf( 'biz_model «%s» معتبر نیست (both، tier_a یا tier_b)؛ «هر دو پلن» گذاشته شد.', $model );
		$model      = 'both';
	}
	$fields['h_ai_biz_model'] = $model;

	$units = hodima_seo_pkg_aeo_units();
	foreach ( [ 'a' => 'tier_a', 'b' => 'tier_b' ] as $tier => $key ) {
		$data = is_array( $value[ $key ] ?? null ) ? $value[ $key ] : [];
		foreach ( hodima_seo_pkg_aeo_tier_fields() as $sub => $spec ) {
			$name = hodima_seo_pkg_aeo_tier_name( $spec['field'], $tier );
			$raw  = $data[ $sub ] ?? null;
			$val  = null === $raw ? $spec[ $tier ] : $text( $raw );

			if ( 'price' === $sub && '' !== $val ) {
				$val = strtr( $val, [ '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9', '٬' => '', ',' => '' ] );
				if ( ! is_numeric( $val ) ) {
					$warnings[] = sprintf( 'قیمت پلن %s عدد نیست؛ خالی ماند (قیمت ووکامرس می‌رود).', strtoupper( $tier ) );
					$val        = '';
				}
			}
			if ( in_array( $sub, [ 'fa_basis', 'en_basis' ], true ) ) {
				$allowed = $units[ 'fa_basis' === $sub ? 'fa' : 'en' ];
				if ( ! in_array( $val, $allowed, true ) ) {
					$warnings[] = sprintf( 'مبنای قیمت پلن %s «%s» در فهرست نیست (%s)؛ پیش‌فرض «%s» گذاشته شد.', strtoupper( $tier ), $val, implode( '، ', $allowed ), $spec[ $tier ] );
					$val        = $spec[ $tier ];
				}
			}
			$fields[ $name ] = $val;
		}
	}

	if ( $is_term ) {
		// پنل AEO دسته بخش «اطلاعات فروش» (پلن A/B و قیمت) ندارد؛ آن خانه‌ها در فرم ترم نیستند
		array_splice( $warnings, $before );
		$sales = [ 'h_ai_biz_model' ];
		foreach ( hodima_seo_pkg_aeo_tier_fields() as $spec ) {
			$sales[] = hodima_seo_pkg_aeo_tier_name( $spec['field'], 'a' );
			$sales[] = hodima_seo_pkg_aeo_tier_name( $spec['field'], 'b' );
		}
		$fields = array_diff_key( $fields, array_flip( $sales ) );
		if ( array_intersect_key( $value, array_flip( [ 'biz_model', 'tier_a', 'tier_b' ] ) ) ) {
			$warnings[] = 'اطلاعات فروش (biz_model، tier_a، tier_b) برای دسته در پنل AEO نیست؛ نادیده گرفته شد.';
		}
	}

	$specs = [];
	foreach ( is_array( $value['en_specs'] ?? null ) ? $value['en_specs'] : [] as $spec ) {
		if ( is_array( $spec ) ) {
			$specs[] = [ 'k' => $text( $spec['k'] ?? '' ), 'v' => $text( $spec['v'] ?? '' ) ];
		}
	}
	$fields['h_ai_en_specs'] = $specs;

	foreach ( [ 'fa_faqs' => [ 'h_ai_faqs', 'پرسش فارسی AEO' ], 'en_faqs' => [ 'h_ai_en_faqs', 'پرسش انگلیسی AEO' ] ] as $key => [ $name, $label ] ) {
		$fields[ $name ] = hodima_seo_pkg_qa_list( $value[ $key ] ?? [], $warnings, $label ) ?? [];
	}

	foreach ( [ 'fa_prompts' => 'h_ai_prompts', 'en_prompts' => 'h_ai_en_prompts' ] as $key => $name ) {
		$lines           = is_array( $value[ $key ] ?? null ) ? array_map( $text, $value[ $key ] ) : [ $text( $value[ $key ] ?? '' ) ];
		$fields[ $name ] = implode( "\n", array_filter( $lines, static fn( string $l ): bool => '' !== $l ) );
	}

	return $fields;
}

/**
 * سئوباکس: عنوان و توضیح، با خط‌کش پیکسلی مشترک سئوباکس/دستار (hodima_seo_text_px).
 *
 * @param array<mixed>                                 $value
 * @param list<string>                                 $warnings
 * @param array{title?: string, description?: string} $current
 * @return array{title: string, description: string, px: array<string, int>}
 */
function hodima_seo_pkg_seobox( array $value, array &$warnings, array $current ): array {

	$title = is_scalar( $value['title'] ?? null ) ? trim( (string) $value['title'] ) : '';
	$desc  = is_scalar( $value['description'] ?? null ) ? trim( (string) $value['description'] ) : '';
	$px    = [];

	if ( function_exists( 'hodima_seo_text_px' ) ) {
		foreach ( [ 'title' => [ $title, 'عنوان' ], 'description' => [ $desc, 'توضیح' ] ] as $field => [ $text, $label ] ) {
			$limit                     = hodima_seo_px_limit( $field );
			$px[ $field ]              = hodima_seo_text_px( $text, $field );
			$px[ $field . '_max' ]     = $limit['max'];
			$px[ $field . '_current' ] = hodima_seo_text_px( (string) ( $current[ $field ] ?? '' ), $field );

			if ( '' === $text ) {
				continue;
			}
			if ( $px[ $field ] > $limit['max'] ) {
				$warnings[] = sprintf( '%s %s از %s پیکسل است؛ در گوگل بریده می‌شود.', $label, number_format_i18n( $px[ $field ] ), number_format_i18n( $limit['max'] ) );
			} elseif ( $px[ $field ] < $limit['min'] ) {
				$warnings[] = sprintf( '%s %s از %s پیکسل است؛ کوتاه است.', $label, number_format_i18n( $px[ $field ] ), number_format_i18n( $limit['max'] ) );
			}
		}
	}

	return [ 'title' => $title, 'description' => $desc, 'px' => $px ];
}

/* =====================================================================
 * کادرهای چندخانه‌ای ساده (ویدیو و پادکست «تنظیمات رسانه»، «تنظیمات ویدیو»)
 * ===================================================================== */

/** ویدیوی «تنظیمات رسانه» (مقاله و دسته): کلید بسته ← نام خانه فرم. کاور (عکس) بیرون از ابزار است. */
const HODIMA_SEO_PKG_MEDIA_VIDEO = [
	'url'         => 'hodima_media[video_url]',
	'title'       => 'hodima_media[video_title]',
	'description' => 'hodima_media[video_description]',
	'duration'    => 'hodima_media[video_duration]',
	'chapters'    => 'hodima_media[video_chapters]',
	'transcript'  => 'hodima_media[video_transcript]',
];

/** پادکست «تنظیمات رسانه». */
const HODIMA_SEO_PKG_MEDIA_PODCAST = [
	'url'        => 'hodima_media[voice_url]',
	'title'      => 'hodima_media[voice_title]',
	'duration'   => 'hodima_media[voice_duration]',
	'transcript' => 'hodima_media[voice_transcript]',
];

/** کادر «تنظیمات ویدیو» صفحه ویدیو (Media، components/video-watch). */
const HODIMA_SEO_PKG_VIDEO_WATCH = [
	'url'        => 'hod_video_url',
	'cover'      => 'hod_video_thumbnail',
	'duration'   => 'hod_video_duration',
	'chapters'   => 'hod_video_chapters',
	'transcript' => 'hod_video_transcript',
];

/**
 * کادر چندخانه‌ای: کل کادر جایگزین می‌شود (کلید نیامده = خانه خالی). فصل‌ها می‌توانند آرایه
 * خط‌ها باشند. خروجی: نام خانه فرم ← مقدار.
 *
 * @param array<mixed>          $value
 * @param list<string>          $warnings
 * @param array<string, string> $map کلید بسته ← نام خانه
 * @return array<string, string>
 */
function hodima_seo_pkg_fields( array $value, array &$warnings, array $map ): array {

	$out = [];
	foreach ( $map as $key => $name ) {
		$raw = $value[ $key ] ?? '';
		if ( is_array( $raw ) ) {
			$raw = implode( "\n", array_map( static fn( mixed $l ): string => is_scalar( $l ) ? trim( (string) $l ) : '', $raw ) );
		}
		$val = is_scalar( $raw ) ? trim( (string) $raw ) : '';

		if ( in_array( $key, [ 'url', 'cover' ], true ) && '' !== $val && ! preg_match( '#^https?://#i', $val ) ) {
			$warnings[] = sprintf( '«%s» آدرس کامل (http/https) نیست؛ خالی ماند.', $key );
			$val        = '';
		}
		$out[ $name ] = $val;
	}

	$extra = array_diff( array_keys( $value ), array_keys( $map ) );
	if ( $extra ) {
		$warnings[] = sprintf( 'کلیدهای %s شناخته نشدند (مجاز: %s).', implode( '، ', $extra ), implode( '، ', array_keys( $map ) ) );
	}

	return $out;
}

/**
 * گوگل دیسکاور (فقط مقاله): عنوان کارت، موضوعات (نام، یا «نام آدرس‌ویکی‌داده») و «برای دیسکاور
 * نیست». تصویر و نقطه تمرکز بیرون از ابزارند.
 *
 * @param array<mixed> $value
 * @param list<string> $warnings
 * @return array{title: string, topics: list<string>, skip: bool}
 */
function hodima_seo_pkg_discover( array $value, array &$warnings ): array {

	$topics = [];
	foreach ( is_array( $value['topics'] ?? null ) ? $value['topics'] : [] as $topic ) {
		$line = is_array( $topic )
			? trim( ( is_scalar( $topic['name'] ?? null ) ? (string) $topic['name'] : '' ) . ' ' . ( is_scalar( $topic['url'] ?? null ) ? (string) $topic['url'] : '' ) )
			: ( is_scalar( $topic ) ? trim( (string) $topic ) : '' );
		if ( '' !== $line && ! in_array( $line, $topics, true ) ) {
			$topics[] = $line;
		}
	}

	$title = is_scalar( $value['title'] ?? null ) ? trim( (string) $value['title'] ) : '';
	if ( function_exists( 'hodima_seo_discover_clickbait_match' ) && '' !== $title ) {
		$bait = hodima_seo_discover_clickbait_match( $title );
		if ( '' !== (string) $bait ) {
			$warnings[] = sprintf( 'عنوان دیسکاور واژه طعمه کلیک دارد: «%s».', (string) $bait );
		}
	}

	return [ 'title' => $title, 'topics' => $topics, 'skip' => ! empty( $value['skip'] ) ];
}

/**
 * کارت‌های «ویدیوهای مرتبط» زیر ویدیو: [{title, url, cover?}]. کارت بی‌عنوان یا بی‌آدرس را کد
 * ذخیره کادر نگه نمی‌دارد (هشدار)؛ آدرس داخلی پیدانشده هشدار می‌گیرد ولی پر می‌شود (کارت دستی است).
 *
 * @param array<mixed> $value
 * @param list<string> $warnings
 * @return list<array{title: string, url: string, cover: string}>|null
 */
function hodima_seo_pkg_suggestions( array $value, array &$warnings ): ?array {

	if ( [] !== $value && ! array_is_list( $value ) ) {
		return null;
	}

	$out  = [];
	$seen = [];
	foreach ( $value as $i => $card ) {
		$n = number_format_i18n( $i + 1 );
		if ( ! is_array( $card ) ) {
			$warnings[] = sprintf( 'کارت %s شکل {title, url} ندارد و کنار گذاشته شد.', $n );
			continue;
		}
		$title = is_scalar( $card['title'] ?? null ) ? trim( (string) $card['title'] ) : '';
		$url   = is_scalar( $card['url'] ?? null ) ? trim( (string) $card['url'] ) : '';
		$cover = is_scalar( $card['cover'] ?? null ) ? trim( (string) $card['cover'] ) : '';

		if ( '' === $title || '' === $url ) {
			$warnings[] = sprintf( 'کارت %s عنوان یا آدرس ندارد؛ هنگام ذخیره نگه داشته نمی‌شود.', $n );
		} elseif ( hodima_seo_pkg_is_internal( $url ) && null === hodima_seo_pkg_resolve( $url ) ) {
			$warnings[] = sprintf( 'کارت %s: %s در سایت پیدا نشد.', $n, $url );
		}
		$key = hodima_seo_pkg_url_key( $url );
		if ( '' !== $url && isset( $seen[ $key ] ) ) {
			$warnings[] = sprintf( 'کارت %s تکرار کارت %s است.', $n, number_format_i18n( $seen[ $key ] + 1 ) );
		}
		$seen[ $key ] ??= $i;
		$out[] = [ 'title' => $title, 'url' => $url, 'cover' => $cover ];
	}
	return $out;
}

/**
 * دکمه محصول زیر ویدیو: آدرس محصول یا دسته (یا null/"" = خاموش).
 *
 * @param array<mixed> $value
 * @param list<string> $warnings
 * @return array{url: string}
 */
function hodima_seo_pkg_product_button( array $value, array &$warnings ): array {

	$url = is_scalar( $value['url'] ?? null ) ? trim( (string) $value['url'] ) : '';
	if ( '' !== $url ) {
		$target = hodima_seo_pkg_is_internal( $url ) ? hodima_seo_pkg_resolve( $url ) : null;
		$ok     = $target && ( 'term' === $target['kind'] || 'product' === get_post_type( $target['id'] ) );
		if ( ! $ok ) {
			$warnings[] = sprintf( '%s محصول یا دسته‌ای از همین سایت نیست؛ دکمه همین آدرس را باز می‌کند.', $url );
		}
	}
	return [ 'url' => $url ];
}
