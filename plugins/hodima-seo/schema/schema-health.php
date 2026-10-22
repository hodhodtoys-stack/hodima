<?php
/**
 * «سلامت اسکیما»: بررسی خودکار اسکیمای چند صفحه نمونه سایت در پیشخوان
 * Path: plugins/hodima-seo/schema/schema-health.php
 *
 * صفحه‌ها همان‌طور که بازدیدکننده ناشناس (و گوگل) می‌بیند دریافت می‌شوند و
 * همان قواعد tools/wp-harness/schema-rules.py (آزمون خودکار توسعه) روی
 * JSON-LD آن‌ها اجرا می‌شود: گراف واحد، هویت، canonical، ارجاع‌ها، تاریخ ISO،
 * آدرس‌ها، محصول و پیشنهاد، مقاله، ویدیو، بردکرامب، FAQ.
 *
 * فقط از صفحه «اسکیما ← سلامت» لود می‌شود (نه در بازدید سایت). فقط آدرس‌های
 * همین سایت دریافت می‌شوند (آدرس دلخواه با دامنه دیگر رد می‌شود).
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/**
 * صفحه‌های نمونه: هر نوع صفحه‌ای که سازنده اسکیمای خودش را دارد.
 *
 * @return list<array{label: string, url: string}>
 */
function hodima_seo_schema_health_sample_urls(): array {

	$urls = [ [ 'label' => 'صفحه اصلی', 'url' => home_url( '/' ) ] ];

	$add_post = static function ( string $label, string $post_type, array $extra = [] ) use ( &$urls ): void {
		if ( ! post_type_exists( $post_type ) ) {
			return;
		}
		$found = get_posts( $extra + [ 'post_type' => $post_type, 'post_status' => 'publish', 'numberposts' => 1, 'has_password' => false ] );
		if ( isset( $found[0] ) && $found[0] instanceof WP_Post ) {
			$urls[] = [ 'label' => $label . ': ' . get_the_title( $found[0] ), 'url' => (string) get_permalink( $found[0] ) ];
		}
	};

	$add_post( 'آخرین مقاله', 'post' );
	$add_post( 'آخرین محصول', 'product' );

	if ( taxonomy_exists( 'product_type' ) ) {
		// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- یک شناسه، فقط در پیشخوان
		$add_post( 'محصول متغیر', 'product', [ 'tax_query' => [ [ 'taxonomy' => 'product_type', 'field' => 'slug', 'terms' => 'variable' ] ] ] );
	}

	foreach ( [ 'product_cat' => 'دسته محصول', 'category' => 'دسته مقاله' ] as $taxonomy => $label ) {
		if ( ! taxonomy_exists( $taxonomy ) ) {
			continue;
		}
		$terms = get_terms( [ 'taxonomy' => $taxonomy, 'hide_empty' => true, 'number' => 1, 'orderby' => 'count', 'order' => 'DESC' ] );
		if ( is_array( $terms ) && isset( $terms[0] ) && $terms[0] instanceof WP_Term ) {
			$link = get_term_link( $terms[0] );
			if ( is_string( $link ) ) {
				$urls[] = [ 'label' => $label . ': ' . $terms[0]->name, 'url' => $link ];
			}
		}
	}

	$blog_id = (int) get_option( 'page_for_posts' );
	if ( $blog_id > 0 ) {
		$urls[] = [ 'label' => 'وبلاگ', 'url' => (string) get_permalink( $blog_id ) ];
	}

	if ( function_exists( 'wc_get_page_id' ) && wc_get_page_id( 'shop' ) > 0 ) {
		$urls[] = [ 'label' => 'فروشگاه', 'url' => (string) get_permalink( wc_get_page_id( 'shop' ) ) ];
	}

	$about = get_page_by_path( (string) get_option( 'hodima_corp_about_slug', 'about-us' ) );
	if ( $about instanceof WP_Post && 'publish' === $about->post_status ) {
		$urls[] = [ 'label' => 'درباره ما', 'url' => (string) get_permalink( $about ) ];
	}

	// تکراری‌ها (مثلا صفحه اصلی = برگه فروشگاه) یک بار
	$seen = [];
	return array_values( array_filter( $urls, static function ( array $row ) use ( &$seen ): bool {
		if ( '' === $row['url'] || isset( $seen[ $row['url'] ] ) ) {
			return false;
		}
		$seen[ $row['url'] ] = true;
		return true;
	} ) );
}

/** آیا آدرس از همین سایت است؟ (فقط همین‌ها دریافت می‌شوند) */
function hodima_seo_schema_health_is_local( string $url ): bool {
	$host = wp_parse_url( $url, PHP_URL_HOST );
	return in_array( wp_parse_url( $url, PHP_URL_SCHEME ), [ 'http', 'https' ], true )
		&& is_string( $host ) && strtolower( $host ) === strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
}

/**
 * دریافت یک صفحه و بررسی اسکیمای آن.
 *
 * @return array{url: string, ok: bool, errors: list<string>, warnings: list<string>, types: list<string>}
 */
function hodima_seo_schema_health_check_url( string $url ): array {

	$result = [ 'url' => $url, 'ok' => false, 'errors' => [], 'warnings' => [], 'types' => [] ];

	if ( ! hodima_seo_schema_health_is_local( $url ) ) {
		$result['errors'][] = 'فقط آدرس‌های همین سایت بررسی می‌شوند.';
		return $result;
	}

	$response = wp_remote_get( $url, [
		'timeout'     => 12,
		'redirection' => 3,
		'sslverify'   => (bool) apply_filters( 'https_local_ssl_verify', false ), // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- فیلتر خود وردپرس برای درخواست به همین سایت
		'user-agent'  => 'Hodima-Schema-Health/' . ( defined( 'HODIMA_SEO_VERSION' ) ? HODIMA_SEO_VERSION : '1' ),
	] );

	if ( is_wp_error( $response ) ) {
		$result['errors'][] = 'صفحه دریافت نشد: ' . $response->get_error_message();
		return $result;
	}

	$code = (int) wp_remote_retrieve_response_code( $response );
	if ( 200 !== $code ) {
		$result['errors'][] = sprintf( 'پاسخ سرور %d بود (انتظار ۲۰۰).', $code );
		return $result;
	}

	$checked = hodima_seo_schema_health_check_html( (string) wp_remote_retrieve_body( $response ) );

	return array_merge( $result, $checked, [ 'url' => $url, 'ok' => [] === $checked['errors'] ] );
}

/**
 * قواعد اسکیما روی HTML یک صفحه (همان tools/wp-harness/schema-rules.py).
 *
 * @return array{errors: list<string>, warnings: list<string>, types: list<string>}
 */
function hodima_seo_schema_health_check_html( string $html ): array {

	$errors   = [];
	$warnings = [];
	$types    = [];

	preg_match_all( '#<script\b[^>]*type=["\']application/ld\+json["\'][^>]*>(.*?)</script>#is', $html, $m );
	$scripts = $m[1];

	if ( [] === $scripts ) {
		return [ 'errors' => [ 'هیچ اسکیمای JSON-LD در صفحه نیست.' ], 'warnings' => [], 'types' => [] ];
	}
	if ( count( $scripts ) > 1 ) {
		$warnings[] = sprintf( '%d تگ JSON-LD در صفحه است (گراف واحد هدیما یک تگ است؛ بقیه از قالب یا افزونه دیگری‌اند؟).', count( $scripts ) );
	}

	$nodes = [];
	foreach ( $scripts as $script ) {
		$data = json_decode( trim( $script ), true );
		if ( ! is_array( $data ) ) {
			$errors[] = 'یک تگ JSON-LD قابل خواندن نیست (JSON نامعتبر).';
			continue;
		}
		$list = isset( $data['@graph'] ) && is_array( $data['@graph'] ) ? $data['@graph'] : ( array_is_list( $data ) ? $data : [ $data ] );
		foreach ( $list as $node ) {
			if ( is_array( $node ) ) {
				$nodes[] = $node;
			}
		}
	}

	$canonical = preg_match( '#<link\s+rel=["\']canonical["\']\s+href=["\']([^"\']+)["\']#i', $html, $c ) ? html_entity_decode( $c[1], ENT_QUOTES, 'UTF-8' ) : '';
	$site      = trailingslashit( home_url() );

	// شناسه‌ها و ارجاع‌ها در هر عمق
	$defined = [];
	$refs    = [];
	$top_ids = [];
	foreach ( $nodes as $node ) {
		if ( isset( $node['@id'] ) && is_string( $node['@id'] ) ) {
			$top_ids[] = $node['@id'];
		}
		hodima_seo_schema_health_walk( $node, static function ( array $obj ) use ( &$defined, &$refs ): void {
			if ( isset( $obj['@id'] ) && is_string( $obj['@id'] ) ) {
				if ( 1 === count( $obj ) ) {
					$refs[ $obj['@id'] ] = true;
				} else {
					$defined[ $obj['@id'] ] = true;
				}
			}
		} );
	}

	foreach ( array_unique( array_diff_assoc( $top_ids, array_unique( $top_ids ) ) ) as $dup ) {
		$errors[] = 'شناسه تکراری در گراف: ' . $dup;
	}

	foreach ( [ '#organization' => 'سازمان', '#website' => 'وب‌سایت' ] as $suffix => $label ) {
		if ( ! isset( $defined[ $site . $suffix ] ) ) {
			$errors[] = "نود {$label} ({$suffix}) نیست.";
		}
	}

	foreach ( array_keys( $refs ) as $ref ) {
		$ref = (string) $ref;
		if ( ! isset( $defined[ $ref ] ) && ( str_starts_with( $ref, $site . '#' ) || ( '' !== $canonical && str_starts_with( $ref, $canonical . '#' ) ) ) ) {
			$errors[] = 'ارجاع به نودی که در صفحه نیست: ' . $ref;
		}
	}

	$pages = array_values( array_filter( $nodes, static fn( array $n ): bool => is_string( $n['@id'] ?? null ) && str_ends_with( $n['@id'], '#webpage' ) ) );

	// جستجو و ۴۰۴ عمدا نود صفحه ندارند (هویت پایدار ندارند)
	$no_identity = 1 === preg_match( '#<body\b[^>]*class=["\'][^"\']*\b(search|error404)\b#i', $html );

	if ( $no_identity ) {
		if ( [] !== $pages ) {
			$errors[] = 'صفحه جستجو/۴۰۴ نباید نود صفحه (#webpage) داشته باشد.';
		}
	} elseif ( '' === $canonical ) {
		$warnings[] = 'تگ canonical در صفحه پیدا نشد.';
	} elseif ( 1 !== count( $pages ) ) {
		$errors[] = sprintf( '%d نود صفحه (#webpage) هست؛ باید یکی باشد.', count( $pages ) );
	} elseif ( $pages[0]['@id'] !== $canonical . '#webpage' ) {
		$errors[] = 'شناسه نود صفحه با canonical یکی نیست: ' . $pages[0]['@id'];
	}

	foreach ( $nodes as $node ) {
		foreach ( (array) ( $node['@type'] ?? [] ) as $type ) {
			if ( is_string( $type ) ) {
				$types[ $type ] = true;
			}
		}
		hodima_seo_schema_health_walk( $node, static function ( array $obj ) use ( &$errors, &$warnings ): void {
			hodima_seo_schema_health_rules( $obj, $errors, $warnings );
		} );
	}

	// اسکیما = محتوای دیده‌شده (همان قاعده schema-rules.py): متن کامل ویدیو در صفحه هم باشد؛ آخرین Clip با مدت، endOffset
	$visible = null;
	foreach ( $nodes as $node ) {
		hodima_seo_schema_health_walk( $node, static function ( array $obj ) use ( $html, &$visible, &$warnings ): void {
			if ( ! in_array( 'VideoObject', (array) ( $obj['@type'] ?? [] ), true ) || count( $obj ) < 2 ) {
				return;
			}
			$transcript = trim( (string) preg_replace( '/\s+/u', ' ', is_string( $obj['transcript'] ?? null ) ? $obj['transcript'] : '' ) );
			if ( '' !== $transcript ) {
				if ( null === $visible ) {
					$body    = (string) preg_replace( '#<(script|style|template)\b.*?</\1>#is', ' ', $html );
					$visible = trim( (string) preg_replace( '/\s+/u', ' ', html_entity_decode( wp_strip_all_tags( $body ), ENT_QUOTES, 'UTF-8' ) ) );
				}
				if ( ! str_contains( $visible, mb_substr( $transcript, 0, 40 ) ) ) {
					$warnings[] = 'متن کامل ویدیو (transcript) در اسکیما هست ولی در صفحه دیده نمی‌شود؛ گوگل اسکیمای محتوای پنهان را نمی‌پسندد.';
				}
			}
			$clips = array_values( array_filter( (array) ( $obj['hasPart'] ?? [] ), static fn( $c ): bool => is_array( $c ) && in_array( 'Clip', (array) ( $c['@type'] ?? [] ), true ) ) );
			if ( ! empty( $obj['duration'] ) && $clips && ! isset( $clips[ count( $clips ) - 1 ]['endOffset'] ) ) {
				$warnings[] = 'ویدیو مدت دارد ولی پایان آخرین «لحظه کلیدی» (endOffset) مشخص نیست.';
			}
		} );
	}

	// یک مجموعه آیتم فقط در یک فهرست (ItemList/OfferCatalog)
	$lists = [];
	foreach ( $nodes as $node ) {
		hodima_seo_schema_health_walk( $node, static function ( array $obj ) use ( &$lists ): void {
			if ( [] === array_intersect( [ 'ItemList', 'OfferCatalog' ], array_map( 'strval', (array) ( $obj['@type'] ?? [] ) ) ) || empty( $obj['itemListElement'] ) ) {
				return;
			}
			$urls = [];
			foreach ( (array) $obj['itemListElement'] as $item ) {
				if ( is_array( $item ) && is_string( $item['url'] ?? $item['item'] ?? null ) ) {
					$urls[] = (string) ( $item['url'] ?? $item['item'] );
				}
			}
			sort( $urls );
			if ( [] !== $urls ) {
				$lists[] = implode( "\n", $urls );
			}
		} );
	}
	if ( count( $lists ) !== count( array_unique( $lists ) ) ) {
		$errors[] = 'یک فهرست آیتم دو بار در صفحه آمده است (ItemList/OfferCatalog تکراری).';
	}

	return [
		'errors'   => array_values( array_unique( $errors ) ),
		'warnings' => array_values( array_unique( $warnings ) ),
		'types'    => array_keys( $types ),
	];
}

/**
 * $callback( شیء ) برای هر شیء JSON در هر عمق.
 *
 * @param array<mixed> $data
 */
function hodima_seo_schema_health_walk( array $data, callable $callback ): void {
	if ( ! array_is_list( $data ) ) {
		$callback( $data );
	}
	foreach ( $data as $value ) {
		if ( is_array( $value ) ) {
			hodima_seo_schema_health_walk( $value, $callback );
		}
	}
}

/**
 * قواعد یک شیء.
 *
 * @param array<mixed> $obj
 * @param list<string> $errors
 * @param list<string> $warnings
 */
function hodima_seo_schema_health_rules( array $obj, array &$errors, array &$warnings ): void {

	$types = array_map( 'strval', (array) ( $obj['@type'] ?? [] ) );
	$is    = static fn( string ...$names ): bool => [] !== array_intersect( $names, $types );
	$label = implode( '/', $types ) ?: 'شیء';

	foreach ( $obj as $key => $value ) {
		$key = (string) $key;

		if ( in_array( $key, [ 'datePublished', 'dateModified', 'uploadDate', 'validFrom', 'priceValidUntil', 'dateCreated' ], true ) ) {
			foreach ( (array) $value as $date ) {
				if ( ! is_string( $date ) || 1 !== preg_match( '/^[0-9]{4}-[0-9]{2}-[0-9]{2}(T[0-9]{2}:[0-9]{2}(:[0-9]{2})?([+-][0-9]{2}:[0-9]{2}|Z)?)?$/', $date ) ) {
					$errors[] = "{$label}: تاریخ {$key} استاندارد (ISO 8601 با رقم لاتین) نیست: " . wp_json_encode( $date, JSON_UNESCAPED_UNICODE );
				}
			}
		}

		if ( in_array( $key, [ 'url', 'contentUrl', 'embedUrl', 'thumbnailUrl', 'item', 'sameAs', 'license', 'acquireLicensePage' ], true ) ) {
			foreach ( (array) $value as $url ) {
				if ( is_string( $url ) && ( str_contains( $url, '&#038;' ) || str_contains( $url, '&amp;' ) || 1 !== preg_match( '#^https?://#', $url ) ) ) {
					$errors[] = "{$label}: آدرس {$key} معتبر نیست: {$url}";
				}
			}
		}

		if ( ( 'priceCurrency' === $key || 'currency' === $key ) && ( ! is_string( $value ) || 1 !== preg_match( '/^[A-Z]{3}$/', $value ) || 'IRT' === $value ) ) {
			$errors[] = "{$label}: کد ارز {$key} معتبر نیست (تومان باید IRR ×۱۰ باشد).";
		}

		if ( 'unitCode' === $key && in_array( $value, [ 'LBR', 'ONZ' ], true ) ) {
			$errors[] = "{$label}: واحد وزن غیرمتریک ({$value}).";
		}
	}

	if ( $is( 'Offer' ) ) {
		if ( 1 !== preg_match( '/^[0-9]+(\.[0-9]+)?$/', (string) ( $obj['price'] ?? '' ) ) ) {
			$errors[] = 'پیشنهاد (Offer) قیمت عددی ندارد.';
		}
		if ( ! str_starts_with( (string) ( $obj['availability'] ?? '' ), 'https://schema.org/' ) ) {
			$errors[] = 'پیشنهاد (Offer) وضعیت موجودی معتبر ندارد.';
		}
		foreach ( [ 'shippingDetails' => 'اطلاعات ارسال', 'hasMerchantReturnPolicy' => 'سیاست مرجوعی' ] as $need => $text ) {
			if ( ! isset( $obj[ $need ] ) ) {
				$warnings[] = "پیشنهاد (Offer) {$text} ندارد (گوگل برای نتایج فروش توصیه می‌کند).";
			}
		}
	}

	if ( $is( 'Product', 'ProductGroup' ) && ! isset( $obj['inProductGroupWithID'] ) && count( $obj ) > 1 ) {
		foreach ( [ 'name' => 'نام', 'image' => 'تصویر' ] as $need => $text ) {
			if ( empty( $obj[ $need ] ) ) {
				$errors[] = "محصول {$text} ندارد.";
			}
		}
		if ( ! isset( $obj['offers'] ) && ! isset( $obj['hasVariant'] ) && ! isset( $obj['aggregateRating'] ) && ! isset( $obj['review'] ) ) {
			$warnings[] = 'محصول نه قیمت دارد نه امتیاز؛ گوگل برای آن نتیجه غنی نشان نمی‌دهد (قیمت محصول را وارد کنید).';
		}
	}

	if ( $is( 'ProductGroup' ) ) {
		foreach ( (array) ( $obj['hasVariant'] ?? [] ) as $variant ) {
			if ( is_array( $variant ) && ( $variant['inProductGroupWithID'] ?? null ) !== ( $obj['productGroupID'] ?? null ) ) {
				$errors[] = 'یک تنوع محصول به گروه خودش (productGroupID) وصل نیست.';
				break;
			}
		}
	}

	if ( $is( 'BlogPosting', 'Article', 'NewsArticle' ) ) {
		if ( mb_strlen( (string) ( $obj['headline'] ?? '' ) ) > 110 ) {
			$errors[] = 'عنوان مقاله (headline) بیش از ۱۱۰ نویسه است.';
		}
		foreach ( [ 'author' => 'نویسنده', 'publisher' => 'ناشر' ] as $need => $text ) {
			if ( ! is_array( $obj[ $need ] ?? null ) || empty( $obj[ $need ]['@id'] ) ) {
				$warnings[] = "{$text} مقاله به نود شناسه‌دار (Person/سازمان) وصل نیست.";
			}
		}
		if ( empty( $obj['image'] ) ) {
			$warnings[] = 'مقاله تصویر ندارد (تصویر شاخص بگذارید).';
		}
	}

	if ( $is( 'VideoObject' ) && count( $obj ) > 1 ) {
		foreach ( [ 'name' => 'عنوان', 'thumbnailUrl' => 'تصویر کاور', 'uploadDate' => 'تاریخ' ] as $need => $text ) {
			if ( empty( $obj[ $need ] ) ) {
				$errors[] = "ویدیو {$text} ندارد.";
			}
		}
		if ( empty( $obj['contentUrl'] ) && empty( $obj['embedUrl'] ) ) {
			$errors[] = 'ویدیو آدرس فایل یا پخش‌کننده (contentUrl/embedUrl) ندارد.';
		}
	}

	if ( $is( 'BreadcrumbList' ) ) {
		$items = array_values( (array) ( $obj['itemListElement'] ?? [] ) );
		foreach ( $items as $i => $item ) {
			if ( ! is_array( $item ) || ( $item['position'] ?? null ) !== $i + 1 || empty( $item['name'] ) || empty( $item['item'] ) ) {
				$errors[] = 'مسیر راهنما (بردکرامب) پله ناقص یا شماره نادرست دارد.';
				break;
			}
		}
	}

	if ( $is( 'FAQPage' ) ) {
		$names = array_filter( array_map( static fn( $q ): string => is_array( $q ) ? (string) ( $q['name'] ?? '' ) : '', (array) ( $obj['mainEntity'] ?? [] ) ) );
		if ( count( $names ) !== count( array_unique( $names ) ) ) {
			$errors[] = 'پرسش تکراری در FAQ.';
		}
	}

	if ( $is( 'ImageObject' ) && is_array( $obj['creator'] ?? null ) && isset( $obj['creator']['@id'] ) ) {
		$errors[] = 'سازنده تصویر (creator) شناسه دارد؛ اعتبارسنج تصویر گوگل آن را رد می‌کند.';
	}
}

/** صفحه «اسکیما ← سلامت»: فرم بررسی و نتیجه آخرین بررسی. */
function hodima_seo_schema_health_page(): void {

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'شما به این بخش دسترسی ندارید.', 'hodima' ) );
	}

	$custom = '';
	$notice = '';
	$last   = get_option( 'hodima_schema_health_last', [] );

	if ( isset( $_POST['hodima_schema_health_nonce'] ) ) {
		check_admin_referer( 'hodima_schema_health', 'hodima_schema_health_nonce' );

		$custom = isset( $_POST['hodima_health_url'] ) ? esc_url_raw( wp_unslash( $_POST['hodima_health_url'] ) ) : '';

		/*
		 * با آدرس دلخواه فقط همان صفحه (چند ثانیه)؛ بدون آن چکاپ کامل صفحه‌های نمونه.
		 * قبلا (SEO 2.2.1 تا 2.2.2) آدرس دلخواه به اول فهرست کامل اضافه می‌شد و
		 * بررسی یک صفحه هم همه صفحه‌ها را دریافت می‌کرد.
		 */
		$targets = null;
		if ( '' === $custom ) {
			$targets = hodima_seo_schema_health_sample_urls();
		} elseif ( hodima_seo_schema_health_is_local( $custom ) ) {
			$targets = [ [ 'label' => 'آدرس دلخواه', 'url' => $custom ] ];
		} else {
			$notice = hodima_admin_notice( 'این آدرس از همین سایت نیست و بررسی نشد؛ فقط صفحه‌های همین سایت بررسی می‌شوند.', 'warning', false );
		}

		if ( null !== $targets ) {
			$results = [];
			foreach ( $targets as $target ) {
				$results[] = [ 'label' => $target['label'] ] + hodima_seo_schema_health_check_url( $target['url'] );
			}

			$last = [ 'time' => time(), 'results' => $results ];
			update_option( 'hodima_schema_health_last', $last, false );
		}
	}

	hodima_view_header(
		'سلامت اسکیما',
		'اسکیمای چند صفحه نمونه سایت را همان‌طور که گوگل می‌بیند دریافت و با قواعد هدیما بررسی می‌کند.',
		'dashicons-heart'
	);

	echo $notice; // phpcs:ignore WordPress.Security.EscapeOutput -- خروجی hodima_admin_notice

	$results = is_array( $last ) && is_array( $last['results'] ?? null ) ? $last['results'] : [];
	$counts  = [ 'ok' => 0, 'warn' => 0, 'error' => 0 ];
	foreach ( $results as $row ) {
		++$counts[ hodima_seo_schema_health_status( $row ) ];
	}
	?>
	<div class="hd-callout">
		<?php echo hodima_admin_icon( 'dashicons-info-outline' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
		<div>
			<strong>چه چیزی بررسی می‌شود؟</strong>
			<p>با آدرس دلخواه فقط همان صفحه بررسی می‌شود؛ بدون آدرس، چکاپ کامل: صفحه اصلی، آخرین مقاله و محصول، یک محصول متغیر، پرمحصول‌ترین دسته‌ها، وبلاگ، فروشگاه و درباره‌ما. روی هر صفحه: یک گراف واحد، نود سازمان و وب‌سایت، شناسه صفحه برابر canonical، ارجاع‌های بی‌مقصد، تاریخ استاندارد، آدرس‌های سالم، قیمت و ارز محصول، مقاله، ویدیو، مسیر راهنما، FAQ و فهرست تکراری. این بررسی جای ابزار «نتایج غنی» گوگل را نمی‌گیرد ولی خطاهای رایج را فوری نشان می‌دهد.</p>
		</div>
	</div>

	<form method="post" action="" class="hd-card">
		<?php wp_nonce_field( 'hodima_schema_health', 'hodima_schema_health_nonce' ); ?>
		<h2 class="hd-section-title"><?php echo hodima_admin_icon( 'dashicons-search' ); // phpcs:ignore WordPress.Security.EscapeOutput ?> بررسی تازه</h2>
		<p>
			<label for="hodima_health_url">آدرس دلخواه (اختیاری)</label><br>
			<input type="url" name="hodima_health_url" id="hodima_health_url" class="large-text ltr" dir="ltr" value="<?php echo esc_attr( $custom ); ?>" placeholder="<?php echo esc_attr( home_url( '/' ) ); ?>…">
		</p>
		<p class="description">خالی = چکاپ کامل سایت (تا یک دقیقه)؛ با آدرس = فقط همان صفحه (چند ثانیه). فقط صفحه‌های همین سایت.</p>
		<?php submit_button( 'بررسی کن', 'primary', 'hodima_health_run', false ); ?>
	</form>

	<?php if ( [] === $results ) : ?>
		<div class="hd-empty"><p>هنوز بررسی انجام نشده است. «بررسی کن» را بزنید.</p></div>
	<?php else : ?>
		<?php
		$summary_type = $counts['error'] > 0 ? 'danger' : ( $counts['warn'] > 0 ? 'warning' : 'success' );
		?>
		<div class="hd-callout hd-callout--<?php echo esc_attr( $summary_type ); ?>">
			<?php echo hodima_admin_icon( 'danger' === $summary_type ? 'dashicons-warning' : 'dashicons-yes-alt' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
			<div>
				<strong>
					<?php
					printf(
						'%1$s صفحه بی‌مشکل، %2$s صفحه با هشدار، %3$s صفحه با خطا',
						esc_html( number_format_i18n( $counts['ok'] ) ),
						esc_html( number_format_i18n( $counts['warn'] ) ),
						esc_html( number_format_i18n( $counts['error'] ) )
					);
					?>
				</strong>
				<p>آخرین بررسی: <?php echo esc_html( wp_date( 'Y/m/d H:i', (int) ( $last['time'] ?? time() ) ) ); ?></p>
			</div>
		</div>

		<div class="hd-table-wrap">
			<table class="widefat striped hodima-schema-health">
				<thead>
					<tr><th scope="col">صفحه</th><th scope="col">وضعیت</th><th scope="col">نوع‌های اسکیما</th><th scope="col">مشکلات</th></tr>
				</thead>
				<tbody>
					<?php foreach ( $results as $row ) : ?>
						<?php
						$status = hodima_seo_schema_health_status( $row );
						$pill   = [ 'ok' => [ 'ok', 'بی‌مشکل' ], 'warn' => [ 'warn', 'هشدار' ], 'error' => [ 'error', 'خطا' ] ][ $status ];
						?>
						<tr>
							<td>
								<strong><?php echo esc_html( (string) ( $row['label'] ?? '' ) ); ?></strong><br>
								<a href="<?php echo esc_url( (string) ( $row['url'] ?? '' ) ); ?>" class="ltr" dir="ltr" target="_blank" rel="noopener"><?php echo esc_html( rawurldecode( (string) ( $row['url'] ?? '' ) ) ); ?></a>
							</td>
							<td><span class="hd-pill hd-pill--<?php echo esc_attr( $pill[0] ); ?>"><?php echo esc_html( $pill[1] ); ?></span></td>
							<td class="ltr" dir="ltr"><?php echo esc_html( implode( '، ', array_map( 'strval', (array) ( $row['types'] ?? [] ) ) ) ); ?></td>
							<td>
								<?php if ( 'ok' === $status ) : ?>
									<span class="hd-muted">—</span>
								<?php else : ?>
									<ul class="hd-list">
										<?php foreach ( (array) ( $row['errors'] ?? [] ) as $message ) : ?>
											<li><span class="hd-pill hd-pill--error">خطا</span> <?php echo esc_html( (string) $message ); ?></li>
										<?php endforeach; ?>
										<?php foreach ( (array) ( $row['warnings'] ?? [] ) as $message ) : ?>
											<li><span class="hd-pill hd-pill--warn">هشدار</span> <?php echo esc_html( (string) $message ); ?></li>
										<?php endforeach; ?>
									</ul>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	<?php endif; ?>
	<?php
	hodima_view_footer();
}

/**
 * وضعیت یک ردیف نتیجه.
 *
 * @param array<string, mixed> $row
 */
function hodima_seo_schema_health_status( array $row ): string {
	if ( ! empty( $row['errors'] ) ) {
		return 'error';
	}
	return empty( $row['warnings'] ) ? 'ok' : 'warn';
}
