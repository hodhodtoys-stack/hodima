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
 * بررسی کامل یک بسته برای یک نوشته.
 *
 * @param array{title?: string, description?: string} $current مقدار فعلی فرم سئوباکس (برای سنجش پیکسلی «فعلی»)
 * @return array{ok: bool, errors: list<string>, meta: array<string, string>, lock: array<string, mixed>, boxes: array<string, array{value: mixed, warnings: list<string>}>, ignored: list<string>}
 */
function hodima_seo_pkg_check( string $json, WP_Post $post, array $current = [] ): array {

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

	if ( HODIMA_SEO_PKG_FORMAT !== $format ) {
		$out['errors'][] = sprintf( 'قالب بسته «%s» نیست (در بسته: «%s»).', HODIMA_SEO_PKG_FORMAT, $format );
	}

	$page_type = hodima_seo_pkg_type_for( $post->post_type );
	if ( null === $page_type || $type !== $page_type ) {
		$out['errors'][] = sprintf( 'نوع بسته «%s» است ولی این صفحه «%s» است.', $type, null !== $page_type ? hodima_seo_pkg_types()[ $page_type ]['label'] : $post->post_type );
	}

	// قفل آدرس: بسته یک صفحه دیگر هرگز این صفحه را پر نکند
	$page_url     = hodima_seo_pkg_page_url( $post );
	$out['lock']  = [
		'package' => $url,
		'page'    => $page_url,
		'id'      => $post->ID,
		'match'   => '' !== $url && hodima_seo_pkg_url_key( $url ) === hodima_seo_pkg_url_key( $page_url ),
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

	if ( $out['errors'] ) {
		return $out;
	}

	$known  = null !== $page_type ? hodima_seo_pkg_types()[ $page_type ]['boxes'] : [];
	$labels = hodima_seo_pkg_box_labels();
	$skip   = hodima_seo_pkg_out_of_scope();

	foreach ( (array) $boxes as $key => $value ) {
		$key = (string) $key;
		if ( ! in_array( $key, $known, true ) ) {
			$out['ignored'][] = isset( $skip[ $key ] )
				? sprintf( '«%s» (%s) در نسخه ۱ ورود بسته نیست؛ دست نخورد.', $skip[ $key ], $key )
				: sprintf( 'کادر «%s» شناخته نشد؛ دست نخورد.', $key );
			continue;
		}

		$warnings = [];
		$normal   = hodima_seo_pkg_box( $key, $value, $post, $warnings, $current );

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
 * نرمال‌ساز یک کادر. null یعنی شکل مقدار نامعتبر است (کادر دست نمی‌خورد).
 * آرایه خالی [] برای هر کادر یعنی «خالی کن».
 *
 * @param list<string>                                 $warnings
 * @param array{title?: string, description?: string} $current
 */
function hodima_seo_pkg_box( string $key, mixed $value, WP_Post $post, array &$warnings, array $current ): mixed {

	if ( ! is_array( $value ) ) {
		return null;
	}

	return match ( $key ) {
		'body'          => hodima_seo_pkg_body( $value, $warnings ),
		'upsells'       => hodima_seo_pkg_products( $value, $post, $warnings, 6 ),
		'cross_sells'   => hodima_seo_pkg_products( $value, $post, $warnings, 0 ),
		'table'         => hodima_seo_pkg_table( $value, $warnings ),
		'faq'           => hodima_seo_pkg_qa_list( $value, $warnings, 'پرسش' ),
		'related_links' => hodima_seo_pkg_related( $value, $post, $warnings ),
		'topic_cluster' => hodima_seo_pkg_cluster( $value, $post, $warnings ),
		'aeo'           => hodima_seo_pkg_aeo( $value, $warnings ),
		'seobox'        => hodima_seo_pkg_seobox( $value, $warnings, $current ),
		default         => null,
	};
}

/* =====================================================================
 * آدرس‌ها
 * ===================================================================== */

/** آدرس فعلی صفحه؛ پیش‌نویس هنوز آدرس نهایی ندارد، پس آدرس نمونه همان نامک. */
function hodima_seo_pkg_page_url( WP_Post $post ): string {

	if ( in_array( $post->post_status, [ 'draft', 'pending', 'auto-draft', 'future' ], true ) && function_exists( 'get_sample_permalink' ) ) {
		[ $template, $name ] = get_sample_permalink( $post->ID );
		return str_replace( [ '%pagename%', '%postname%' ], (string) $name, (string) $template );
	}

	return (string) get_permalink( $post );
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
 * متن اصلی: HTML بدنه با شورت‌کدها. هشدار (نه توقف): لینک tel: غیر از شماره‌های سایت و
 * سرتیترهای H1، H4، H5، H6.
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
			$warnings[] = sprintf( 'سرتیتر H%d در متن (%s مورد)؛ برای متن محصول فقط H2 و H3 پیشنهاد می‌شود.', $level, number_format_i18n( $count ) );
		}
	}

	return [ 'html' => $html ];
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

	$fit = static fn( array $r ): array => array_map( static fn( int $c ): string => $r[ $c ] ?? '', range( 0, $width - 1 ) );

	return [ 'headers' => $fit( $headers ), 'rows' => array_map( $fit, $clean ) ];
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
function hodima_seo_pkg_related( array $value, WP_Post $post, array &$warnings ): array {

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
			$item = \Hodima\RelatedLinks\Admin::package_item( $target['id'], $post->ID );
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
 * خوشه‌بندی محتوا: پیلار، خارج از خوشه و دسته پیلار والد. parent_term null یعنی خالی (والد
 * خودکار از دسته اصلی). محصول والد نوشته‌ای (مقاله) نمی‌گیرد، پس parent_post_url فقط هشدار.
 *
 * @param array<mixed> $value
 * @param list<string> $warnings
 * @return array{pillar: bool, exclude: bool, parents: list<array{id: int, title: string}>}
 */
function hodima_seo_pkg_cluster( array $value, WP_Post $post, array &$warnings ): array {

	$out = [ 'pillar' => ! empty( $value['pillar'] ), 'exclude' => ! empty( $value['exclude'] ), 'parents' => [] ];

	if ( ! class_exists( \Hodima\TopicCluster\Graph::class ) ) {
		$warnings[] = 'ماژول «خوشه‌های موضوعی» روشن نیست؛ این کادر پر نمی‌شود.';
		return $out;
	}

	$taxonomy = \Hodima\TopicCluster\Graph::parent_taxonomy_for( $post->post_type );
	$term_url = $value['parent_term'] ?? null;

	if ( is_string( $term_url ) && '' !== trim( $term_url ) ) {
		$target = hodima_seo_pkg_resolve( $term_url );
		$term   = $target && 'term' === $target['kind'] ? get_term( $target['id'] ) : null;

		if ( ! $term instanceof WP_Term || $term->taxonomy !== $taxonomy ) {
			$warnings[] = sprintf( 'دسته والد %s پیدا نشد؛ والد خالی ماند (خودکار از دسته اصلی).', $term_url );
		} elseif ( ! \Hodima\TopicCluster\Graph::is_pillar( \Hodima\TopicCluster\Ref::term( $term->term_id ) ) ) {
			$warnings[] = sprintf( 'دسته «%s» پیلار نیست و والد نمی‌شود؛ والد خالی ماند.', $term->name );
		} else {
			$title = class_exists( \Hodima\TopicCluster\Admin\Editor::class )
				? \Hodima\TopicCluster\Admin\Editor::label_for( \Hodima\TopicCluster\Ref::term( $term->term_id ), $term->name )
				: $term->name;
			$out['parents'][] = [ 'id' => (int) $term->term_id, 'title' => $title ];
		}
	} elseif ( null !== $term_url && ! is_string( $term_url ) ) {
		$warnings[] = 'parent_term باید آدرس دسته یا null باشد؛ والد خالی ماند.';
	}

	$post_url = $value['parent_post_url'] ?? null;
	if ( is_string( $post_url ) && '' !== trim( $post_url ) && ! \Hodima\TopicCluster\Graph::accepts_post_parents( \Hodima\TopicCluster\Ref::post( $post->ID ) ) ) {
		$warnings[] = 'محصول والد مقاله‌ای (parent_post_url) نمی‌گیرد؛ این مورد نادیده گرفته شد.';
	}

	if ( $out['exclude'] && $out['parents'] ) {
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
function hodima_seo_pkg_aeo( array $value, array &$warnings ): array {

	$text   = static fn( mixed $v ): string => is_scalar( $v ) ? trim( (string) $v ) : '';
	$fields = [];

	foreach ( hodima_seo_pkg_aeo_text_fields() as $key => $name ) {
		$fields[ $name ] = $text( $value[ $key ] ?? '' );
	}

	$model = $text( $value['biz_model'] ?? 'both' );
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
