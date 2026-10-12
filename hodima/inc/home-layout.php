<?php
/**
 * چیدمان صفحه اصلی — داده، پاک‌سازی، خواندن از متن برگه و رندر
 * Path: hodima/inc/home-layout.php
 *
 * بازسازی قالب، مرحله ۳. قبلا صفحه اصلی فقط از شورت‌کدهای نوشته‌شده در
 * متن برگه صفحه اصلی ساخته می‌شد ([section03]، [latest-products]، [plasco]، …)
 * و هر عنوان یا فهرستی (مثلا «اهداف و مزایای ما»، «نبض بازار»، دسته‌های
 * مستثنا) داخل کد بود. حالا «نمایش ← تنظیمات قالب هدیما ← صفحه اصلی»:
 * بخش‌ها با ترتیب دلخواه، روشن/خاموش و تنظیمات هر بخش.
 *
 * تا مدیر «ساخت صفحه اصلی از این چیدمان» را روشن نکرده، صفحه اصلی دقیقا
 * مثل قبل از متن برگه ساخته می‌شود (front-page.php → index.php). اولین بار
 * که تب باز شود، چیدمان از روی همان متن برگه پیشنهاد می‌شود.
 *
 * داده: گزینه hodima_home_layout — فهرست بخش‌ها، هر کدام
 *   [ 'id' => string, 'type' => string, 'enabled' => bool, …تنظیمات نوع ]
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

const HODIMA_HOME_LAYOUT_OPTION = 'hodima_home_layout';

/* =========================================================================
 * ۱. انواع بخش
 * ========================================================================= */

/**
 * تعریف انواع بخش. هر فیلد: type (text|lines|number|tone|category|categories|html|link)،
 * label، default، و برای number: min/max.
 *
 * @return array<string, array{label:string, icon:string, help:string, single:bool, fields:array<string, array<string, mixed>>}>
 */
function hodima_home_section_types(): array {

	static $types = null;

	return $types ??= (array) apply_filters( 'hodima_home_section_types', [
		'intro'      => [
			'label'  => 'معرفی و ویدیو',
			'icon'   => 'dashicons-format-video',
			'single' => true,
			'help'   => 'عنوان اصلی صفحه (H1) = عنوان برگه صفحه اصلی؛ متن معرفی و ویدیو از کادر «رسانه» همان برگه (افزونه Hodima Media). ستون وسط از تنظیمات زیر.',
			'fields' => [
				'features_title' => [ 'type' => 'text', 'label' => 'عنوان ستون وسط', 'default' => 'اهداف و مزایای ما' ],
				'features'       => [ 'type' => 'lines', 'label' => 'موارد ستون وسط (هر خط یک مورد)', 'default' => [
					'شبکه تأمین گسترده و تیمی مجرب در واردات',
					'ارائه کالاهای پرفروش و هم‌راستا با ترندهای جهانی',
					'تأمین نیاز بازار با قیمت رقابتی با حذف واسطه‌ها',
					'تمرکز بر رضایت مشتریان در سراسر منطقه',
				] ],
				'video_title'    => [ 'type' => 'text', 'label' => 'عنوان ستون ویدیو', 'default' => 'درباره ما' ],
			],
		],
		'categories' => [
			'label'  => 'دسته‌بندی کالاها',
			'icon'   => 'dashicons-category',
			'single' => true,
			'help'   => 'همه دسته‌های محصول دارای کالا، با تصویر دسته (ووکامرس)، به‌جز دسته‌هایی که اینجا تیک بزنید.',
			'fields' => [
				'title'   => [ 'type' => 'text', 'label' => 'عنوان بخش', 'default' => 'دسته‌بندی کالاها' ],
				'exclude' => [ 'type' => 'categories', 'label' => 'دسته‌هایی که نمایش داده نشوند', 'default' => [ 'latest-products', 'equipments', 'plasco', 'rhinestones', 'toys', 'shanci' ] ],
			],
		],
		'latest'     => [
			'label'  => 'جدیدترین محصولات',
			'icon'   => 'dashicons-star-filled',
			'single' => false,
			'help'   => 'آخرین محصولات موجود در انبار، از همه دسته‌ها.',
			'fields' => [
				'title' => [ 'type' => 'text', 'label' => 'عنوان بخش', 'default' => 'جدیدترین محصولات' ],
				'limit' => [ 'type' => 'number', 'label' => 'تعداد محصول', 'default' => 12, 'min' => 1, 'max' => 30 ],
				'link'  => [ 'type' => 'link', 'label' => 'لینک «مشاهده همه» (آدرس یا نامک؛ خالی = بدون لینک)', 'default' => 'latest-products' ],
				'tone'  => [ 'type' => 'tone', 'label' => 'رنگ زمینه', 'default' => 'third' ],
			],
		],
		'products'   => [
			'label'  => 'محصولات یک دسته',
			'icon'   => 'dashicons-products',
			'single' => false,
			'help'   => 'آخرین محصولات موجود یک دسته، با لینک «مشاهده همه» به صفحه همان دسته. هر چند تا که لازم است اضافه کنید.',
			'fields' => [
				'category' => [ 'type' => 'category', 'label' => 'دسته', 'default' => '' ],
				'title'    => [ 'type' => 'text', 'label' => 'عنوان بخش (خالی = نام دسته)', 'default' => '' ],
				'limit'    => [ 'type' => 'number', 'label' => 'تعداد محصول', 'default' => 12, 'min' => 1, 'max' => 30 ],
				'tone'     => [ 'type' => 'tone', 'label' => 'رنگ زمینه', 'default' => 'primary' ],
			],
		],
		'blog'       => [
			'label'  => 'آخرین مقالات',
			'icon'   => 'dashicons-admin-post',
			'single' => true,
			'help'   => 'آخرین نوشته‌های وبلاگ با تصویر شاخص و لینک به برگه نوشته‌ها.',
			'fields' => [
				'title'     => [ 'type' => 'text', 'label' => 'عنوان بخش', 'default' => 'نبض بازار' ],
				'limit'     => [ 'type' => 'number', 'label' => 'تعداد مقاله', 'default' => 8, 'min' => 1, 'max' => 20 ],
				'more_text' => [ 'type' => 'text', 'label' => 'متن لینک «همه مقالات» (خالی = بدون لینک)', 'default' => 'همه مقالات' ],
			],
		],
		'content'    => [
			'label'  => 'متن برگه صفحه اصلی',
			'icon'   => 'dashicons-media-text',
			'single' => true,
			'help'   => 'هر چه در ویرایشگر برگه صفحه اصلی نوشته شده (متن، تصویر یا شورت‌کد). برای چیدمان قدیمی یا محتوای آزاد.',
			'fields' => [],
		],
		'custom'     => [
			'label'  => 'محتوای دلخواه',
			'icon'   => 'dashicons-shortcode',
			'single' => false,
			'help'   => 'یک شورت‌کد (مثلا اسلایدر یا استوری افزونه Hodima Media) یا HTML ساده. هر شورت‌کد را در بخش جدا بگذارید تا ترتیب و روشن/خاموش‌اش مستقل باشد.',
			'fields' => [
				'label' => [ 'type' => 'text', 'label' => 'نام این بخش (فقط برای شما در پیشخوان)', 'default' => '' ],
				'html'  => [ 'type' => 'html', 'label' => 'شورت‌کد یا HTML', 'default' => '' ],
			],
		],
	] );
}

/**
 * مقدار پیش‌فرض فیلدهای یک نوع بخش.
 *
 * @return array<string, mixed>
 */
function hodima_home_section_defaults( string $type ): array {
	return array_map(
		static fn( array $field ): mixed => $field['default'],
		hodima_home_section_types()[ $type ]['fields'] ?? []
	);
}

/**
 * رنگ‌های زمینه بخش‌های محصول (همان سه رنگ قبلی اسلایدرها) — از enum
 * Hodima_Home_Tone (inc/classes)؛ همین شکل آرایه برای کدهای قبلی.
 *
 * @return array<string, array{label:string, css:string}>
 */
function hodima_home_tones(): array {

	$tones = [];

	foreach ( Hodima_Home_Tone::cases() as $tone ) {
		$tones[ $tone->value ] = [ 'label' => $tone->label(), 'css' => $tone->css() ];
	}

	return $tones;
}

/* =========================================================================
 * ۲. خواندن و پاک‌سازی
 * ========================================================================= */

/** آیا مدیر چیدمان را تا به حال ذخیره کرده است؟ */
function hodima_home_layout_saved(): bool {
	return is_array( get_option( HODIMA_HOME_LAYOUT_OPTION, false ) );
}

/**
 * چیدمان ذخیره‌شده (یکدست‌شده).
 *
 * @return list<array<string, mixed>>
 */
function hodima_home_layout(): array {

	$stored = get_option( HODIMA_HOME_LAYOUT_OPTION, [] );

	return hodima_home_normalize_layout( is_array( $stored ) ? $stored : [] );
}

/**
 * آیا صفحه اصلی از چیدمان ساخته شود؟
 * کلید «ساخت صفحه اصلی از این چیدمان» روشن، صفحه اصلی یک برگه، و دست‌کم یک بخش روشن.
 */
function hodima_home_builder_active(): bool {

	if ( ! function_exists( 'hodima_setting' ) || ! hodima_setting( 'home_builder' ) || ! is_front_page() || ! is_page() ) {
		return false;
	}

	foreach ( hodima_home_layout() as $item ) {
		if ( $item['enabled'] ) {
			return true;
		}
	}

	return false;
}

/**
 * یکدست‌سازی: نوع ناشناخته حذف، فیلدهای ناشناخته حذف، فیلد غایب = پیش‌فرض،
 * بخش‌های تک‌نمونه (معرفی، دسته‌ها، مقالات، متن برگه) فقط اولین نمونه.
 * @param array<mixed> $items
 * @return list<array<string, mixed>>
 */
function hodima_home_normalize_layout( array $items ): array {

	$types = hodima_home_section_types();
	$seen  = [];
	$clean = [];

	foreach ( $items as $item ) {

		$type = is_array( $item ) ? (string) ( $item['type'] ?? '' ) : '';

		if ( ! isset( $types[ $type ] ) || ( $types[ $type ]['single'] && isset( $seen[ $type ] ) ) ) {
			continue;
		}

		$seen[ $type ] = true;
		$id            = sanitize_key( (string) ( $item['id'] ?? '' ) ) ?: 's' . substr( md5( $type . count( $clean ) ), 0, 8 );

		$entry = [ 'id' => $id, 'type' => $type, 'enabled' => ! empty( $item['enabled'] ) ]
			+ array_intersect_key( $item, $types[ $type ]['fields'] )
			+ hodima_home_section_defaults( $type );

		// «محتوای دلخواه» با چند شورت‌کد/بلوک پشت‌سرهم ← هر کدام بخش جدا (3.1.3)
		if ( 'custom' === $type ) {
			foreach ( hodima_home_split_custom( $entry ) as $part ) {
				$clean[] = $part;
			}
			continue;
		}

		$clean[] = $entry;
	}

	return $clean;
}

/**
 * یک متن را به تکه‌ها بشکن: متن عادی و شورت‌کدها. هر تکه شورت‌کد می‌گوید «تنها در خط خودش»
 * (standalone) هست یا نه؛ فقط شورت‌کد تنها از متن جدا می‌شود (شورت‌کد داخل <div> یا وسط
 * جمله دست نمی‌خورد). نشانه‌های بلوک گوتنبرگ (<!-- wp:shortcode -->) و <p> دور شورت‌کد حذف می‌شوند.
 *
 * @return list<array{text?:string, match?:array<int, array{0:string, 1:int}>, raw?:string, standalone?:bool}>
 */
function hodima_home_scan_content( string $content ): array {

	$content = (string) preg_replace( '/<!--\s*\/?wp:[^>]*?-->/', "\n", $content );

	if ( ! preg_match_all( '/' . get_shortcode_regex() . '/s', $content, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE ) ) {
		return [ [ 'text' => $content ] ];
	}

	$parts      = [];
	$offset     = 0;
	$prev_alone = false;

	foreach ( $matches as $m ) {

		$start = (int) $m[0][1];
		$raw   = (string) $m[0][0];
		$end   = $start + strlen( $raw );
		$gap   = substr( $content, $offset, $start - $offset );
		$tail  = substr( $content, $end, 120 );

		$gap_blank  = '' === trim( (string) preg_replace( '#</?p[^>]*>#i', '', $gap ) );
		$line_start = (bool) preg_match( '/(?:^|\R)[ \t]*(?:<p[^>]*>\s*)?$/', $gap )
			&& ( ! $gap_blank || 0 === $offset || $prev_alone || (bool) preg_match( '/\R/', $gap ) );
		$line_end   = (bool) preg_match( '/^[ \t]*(?:<\/p>)?[ \t]*(?:\R|$)/', $tail ) || (bool) preg_match( '/^[ \t]*\[/', $tail );
		$alone      = $line_start && $line_end;

		if ( $alone ) {
			$gap = (string) preg_replace( '/<p[^>]*>\s*$/i', '', $gap );
			if ( preg_match( '/^[ \t]*<\/p>/i', $tail, $closing ) ) {
				$end += strlen( $closing[0] );
			}
		}

		if ( '' !== $gap ) {
			$parts[] = [ 'text' => $gap ];
		}

		$parts[]    = [ 'match' => $m, 'raw' => $raw, 'standalone' => $alone ];
		$offset     = $end;
		$prev_alone = $alone;
	}

	$rest = substr( $content, $offset );
	if ( '' !== $rest ) {
		$parts[] = [ 'text' => (string) preg_replace( '/^\s*<\/p>/i', '', $rest ) ];
	}

	return $parts;
}

/** متنی که ارزش یک بخش را دارد؟ (فقط <p>/فاصله خالی دور شورت‌کدها نه؛ تصویر/ویدیو/جدول بله) */
function hodima_home_text_has_content( string $text ): bool {
	return '' !== trim( wp_strip_all_tags( str_replace( '&nbsp;', ' ', $text ) ) ) || (bool) preg_match( '/<(img|iframe|video|figure|table)\b/i', $text );
}

/**
 * یک بخش «محتوای دلخواه» که چند شورت‌کد/بلوک دارد ← چند بخش (هر شورت‌کد تنها، و متن بین‌شان).
 * با یک تکه همان بخش بی‌تغییر برمی‌گردد. شناسه اولی همان قبلی و بقیه «شناسه-۲»، «شناسه-۳»…
 *
 * @param array<string, mixed> $item بخش یکدست‌شده (type=custom)
 * @return list<array<string, mixed>>
 */
function hodima_home_split_custom( array $item ): array {

	$html = (string) $item['html'];

	if ( '' === trim( $html ) || ! str_contains( $html, '[' ) ) {
		return [ $item ];
	}

	$segments = [];
	$pending  = '';
	$flush    = static function () use ( &$pending, &$segments ): void {
		$text = trim( (string) preg_replace( [ '/<p[^>]*>\s*$/i', '/^\s*<\/p>/i' ], '', trim( $pending ) ) );
		if ( hodima_home_text_has_content( $text ) ) {
			$segments[] = $text;
		}
		$pending = '';
	};

	foreach ( hodima_home_scan_content( $html ) as $part ) {
		if ( isset( $part['text'] ) ) {
			$pending .= $part['text'];
		} elseif ( ! empty( $part['standalone'] ) ) {
			$flush();
			$segments[] = trim( (string) $part['raw'] );
		} else {
			$pending .= (string) $part['raw'];
		}
	}
	$flush();

	if ( count( $segments ) < 2 ) {
		return [ $item ];
	}

	$items = [];
	foreach ( $segments as $n => $segment ) {
		$items[] = [
			'id'    => 0 === $n ? $item['id'] : $item['id'] . '-' . ( $n + 1 ),
			'html'  => $segment,
			'label' => 0 === $n ? $item['label'] : '',
		] + $item;
	}

	return $items;
}

/**
 * sanitize_callback گزینه hodima_home_layout.
 *
 * ورودی فرم: [ '_sent' => 1, '_import'? => 1, '<uid>' => [ 'type', 'enabled', …فیلدها ] ]
 * به ترتیب ظاهرشدن در فرم (ترتیب جابه‌جاشده با کشیدن یا دکمه‌ها).
 * ورودی فهرست (از کد، یا فراخوانی دوباره وردپرس هنگام add_option) هم پذیرفته می‌شود.
 * ورودی غیرآرایه (فرمی که این بخش را نداشت) = دست نزدن به چیدمان فعلی.
 *
 * @param mixed $input
 * @return list<array<string, mixed>>
 */
function hodima_home_sanitize_layout( $input ): array {

	if ( ! is_array( $input ) ) {
		$current = get_option( HODIMA_HOME_LAYOUT_OPTION, [] );
		return is_array( $current ) ? $current : [];
	}

	if ( ! empty( $input['_import'] ) ) {
		function_exists( 'add_settings_error' ) && add_settings_error( 'hodima_theme_settings', 'hodima_home_imported', 'چیدمان صفحه اصلی از روی متن برگه صفحه اصلی خوانده شد. بررسی کنید و اگر درست بود «ساخت صفحه اصلی از این چیدمان» را روشن کنید.', 'info' );
		return hodima_home_layout_from_content();
	}

	if ( ! array_is_list( $input ) ) {
		if ( ! isset( $input['_sent'] ) ) {
			$current = get_option( HODIMA_HOME_LAYOUT_OPTION, [] );
			return is_array( $current ) ? $current : [];
		}
		unset( $input['_sent'] );
		foreach ( $input as $uid => &$row ) {
			if ( is_array( $row ) ) {
				$row['id'] = (string) $uid;
			}
		}
		unset( $row );
		$input = array_values( array_filter( $input, 'is_array' ) );
	}

	$types = hodima_home_section_types();
	$items = [];
	$seen  = [];

	foreach ( $input as $row ) {

		// بخش تک‌نمونه‌ی تکراری حذف می‌شود؛ بی‌صدا نباشد
		$row_type = (string) ( $row['type'] ?? '' );
		if ( isset( $types[ $row_type ] ) && $types[ $row_type ]['single'] ) {
			if ( isset( $seen[ $row_type ] ) ) {
				function_exists( 'add_settings_error' ) && add_settings_error( 'hodima_theme_settings', 'hodima_home_dup_' . $row_type, sprintf( 'بخش «%s» فقط یک‌بار می‌تواند در صفحه اصلی باشد؛ نمونه دوم حذف شد.', $types[ $row_type ]['label'] ), 'warning' );
			}
			$seen[ $row_type ] = true;
		}

		$type = (string) ( $row['type'] ?? '' );
		if ( ! isset( $types[ $type ] ) ) {
			continue;
		}

		$item = [
			'id'      => sanitize_key( (string) ( $row['id'] ?? '' ) ),
			'type'    => $type,
			'enabled' => ! empty( $row['enabled'] ),
		];

		// فیلد نفرستاده = پیش‌فرض (فرم همه فیلدها را می‌فرستد؛ این برای ورودی از کد است)
		foreach ( $types[ $type ]['fields'] as $key => $field ) {
			$item[ $key ] = array_key_exists( $key, $row ) ? hodima_home_sanitize_field( $field, $row[ $key ] ) : $field['default'];
		}

		$items[] = $item;
	}

	return hodima_home_normalize_layout( $items );
}

/**
 * پاک‌سازی یک فیلد بر اساس نوعش.
 *
 * @param array<string, mixed> $field
 */
function hodima_home_sanitize_field( array $field, mixed $raw ): mixed {

	return match ( $field['type'] ) {
		'number'     => min( (int) ( $field['max'] ?? 100 ), max( (int) ( $field['min'] ?? 1 ), (int) ( is_scalar( $raw ) ? $raw : $field['default'] ) ) ),
		'tone'       => isset( hodima_home_tones()[ (string) $raw ] ) ? (string) $raw : (string) $field['default'],
		'lines'      => array_values( array_filter( array_map(
			static fn( string $line ): string => sanitize_text_field( $line ),
			is_array( $raw ) ? array_map( 'strval', $raw ) : ( preg_split( '/\R/u', is_string( $raw ) ? $raw : '' ) ?: [] )
		) ) ),
		'category'   => sanitize_title( is_string( $raw ) ? $raw : '' ),
		'categories' => array_values( array_unique( array_filter( array_map( 'sanitize_title', array_map( 'strval', is_array( $raw ) ? $raw : [] ) ) ) ) ),
		'link'       => hodima_home_sanitize_link( is_string( $raw ) ? $raw : '' ),
		// شورت‌کد/HTML: مدیر با unfiltered_html هر چه بخواهد (مثل ویرایشگر برگه)، بقیه HTML امن
		'html'       => current_user_can( 'unfiltered_html' ) ? trim( (string) ( is_string( $raw ) ? $raw : '' ) ) : wp_kses_post( trim( is_string( $raw ) ? $raw : '' ) ),
		default      => sanitize_text_field( is_string( $raw ) ? $raw : '' ),
	};
}

/** لینک: آدرس کامل http(s) یا نامک/مسیر داخلی (مثل latest-products یا /shop/). */
function hodima_home_sanitize_link( string $raw ): string {

	$raw = trim( $raw );

	if ( '' === $raw ) {
		return '';
	}

	if ( preg_match( '#^https?://#i', $raw ) ) {
		return esc_url_raw( $raw, [ 'http', 'https' ] );
	}

	// مسیر داخلی: هر بخش جدا (نامک فارسی کدگذاری‌شده هم حفظ شود)
	$parts = array_filter( array_map( static fn( string $p ): string => sanitize_title( rawurldecode( $p ) ), explode( '/', $raw ) ) );

	return implode( '/', $parts );
}

/**
 * آیا لینک ذخیره‌شده به چیزی می‌رسد؟ (فقط برای هشدار پیشخوان؛ در سایت لینک بدون بررسی چاپ می‌شود.)
 * آدرس کامل همیشه درست؛ مسیر داخلی: برگه، دسته محصول با همان نامک، یا هر نوشته/برگه با همان آدرس.
 */
function hodima_home_link_resolves( string $link ): bool {

	$path = trim( rawurldecode( $link ), '/' );

	if ( '' === $path || preg_match( '#^https?://#i', $link ) ) {
		return true;
	}

	if ( get_page_by_path( $path ) instanceof WP_Post ) {
		return true;
	}

	if ( taxonomy_exists( 'product_cat' ) && get_term_by( 'slug', sanitize_title( basename( $path ) ), 'product_cat' ) instanceof WP_Term ) {
		return true;
	}

	return url_to_postid( home_url( '/' . $path . '/' ) ) > 0;
}

/**
 * مشکل یک بخش که باعث می‌شود چیزی چاپ نشود یا لینکش خراب باشد (برای نشان کنار نام بخش در پیشخوان).
 *
 * @param array<string, mixed> $item
 */
function hodima_home_section_warning( array $item ): string {

	switch ( $item['type'] ) {

		case 'products':
			$slug = (string) $item['category'];
			if ( '' === $slug ) {
				return 'دسته انتخاب نشده؛ این بخش نمایش داده نمی‌شود';
			}
			$term = taxonomy_exists( 'product_cat' ) ? get_term_by( 'slug', $slug, 'product_cat' ) : false;
			if ( ! $term instanceof WP_Term ) {
				return 'این دسته دیگر وجود ندارد؛ بخش نمایش داده نمی‌شود';
			}
			return 0 === $term->count ? 'این دسته محصولی ندارد؛ بخش نمایش داده نمی‌شود' : '';

		case 'latest':
			return ! hodima_home_link_resolves( (string) $item['link'] ) ? 'لینک «مشاهده همه» به صفحه‌ای نمی‌رسد' : '';

		case 'custom':
			$html = trim( (string) $item['html'] );
			if ( '' === $html ) {
				return 'خالی است؛ نمایش داده نمی‌شود';
			}
			if ( preg_match( '/^\[([^\s\]\/]+)[^\]]*\]$/', $html, $tag ) && ! shortcode_exists( $tag[1] ) ) {
				return sprintf( 'شورت‌کد [%s] ثبت نشده (افزونه‌اش فعال است؟)', $tag[1] );
			}
			return '';
	}

	return '';
}

/**
 * خلاصه جلوی نام بخش در پیشخوان: عنوان، نام دسته، یا برای «محتوای دلخواه» نام دلخواه / نام شورت‌کد / ابتدای متن.
 *
 * @param array<string, mixed> $item
 * @param array<int, WP_Term>  $terms
 */
function hodima_home_section_summary( array $item, array $terms = [] ): string {

	$summary = trim( (string) ( $item['title'] ?? '' ) ) ?: trim( (string) ( $item['label'] ?? '' ) );

	if ( '' !== $summary ) {
		return $summary;
	}

	if ( 'products' === $item['type'] ) {
		foreach ( $terms as $term ) {
			if ( $term->slug === ( $item['category'] ?? '' ) ) {
				return $term->name;
			}
		}
	}

	if ( 'custom' === $item['type'] ) {
		$html = trim( (string) $item['html'] );
		if ( preg_match( '/^\[([^\s\]\/]+)/', $html, $tag ) ) {
			return '[' . $tag[1] . ']';
		}
		return mb_strimwidth( trim( wp_strip_all_tags( $html ) ), 0, 40, '…' );
	}

	return '';
}

/** آدرس کامل یک لینک ذخیره‌شده. */
function hodima_home_link_url( string $link ): string {
	if ( '' === $link ) {
		return '';
	}
	return preg_match( '#^https?://#i', $link ) ? $link : home_url( '/' . trim( $link, '/' ) . '/' );
}

/* =========================================================================
 * ۳. خواندن چیدمان از متن برگه صفحه اصلی (یک‌باره، برای انتقال)
 * ========================================================================= */

/**
 * شورت‌کدهای متن برگه صفحه اصلی → بخش‌ها، به همان ترتیب:
 *   [section07] → معرفی، [section03] → دسته‌ها، [section09] → مقالات،
 *   [latest-products limit=N] → جدیدترین، [نامک دسته …] → محصولات دسته
 *   (با همان رنگ زمینه قبلی)، [section10] (خالی) → نادیده، بقیه شورت‌کدها و
 *   متن/HTML → «محتوای دلخواه» (متن پشت‌سرهم یک بخش).
 * @return list<array<string, mixed>>
 */
function hodima_home_layout_from_content( ?string $content = null ): array {

	if ( null === $content ) {
		$front_id = (int) get_option( 'page_on_front' );
		$content  = $front_id > 0 ? (string) get_post_field( 'post_content', $front_id ) : '';
	}

	$items      = [];
	$pending    = '';
	$cat_map    = function_exists( 'hodima_home_category_shortcode_map' ) ? hodima_home_category_shortcode_map() : [];
	$cat_tones  = hodima_home_legacy_category_tones( $cat_map );
	$tone_by_css = [];
	foreach ( Hodima_Home_Tone::cases() as $tone_case ) {
		$tone_by_css[ $tone_case->legacy_css() ] = $tone_case->value; // bg_color قدیمی شورت‌کد ← رنگ زمینه امروز
	}

	$flush = static function () use ( &$pending, &$items ): void {
		$text = trim( (string) preg_replace( [ '/<p[^>]*>\s*$/i', '/^\s*<\/p>/i' ], '', trim( $pending ) ) );
		// فقط پاراگراف/فاصله خالی (wpautop دور شورت‌کدها) بخش نمی‌سازد
		if ( hodima_home_text_has_content( $text ) ) {
			$items[] = [ 'type' => 'custom', 'enabled' => true, 'html' => $text ];
		}
		$pending = '';
	};

	foreach ( hodima_home_scan_content( $content ) as $part ) {

		if ( isset( $part['text'] ) ) {
			$pending .= $part['text'];
			continue;
		}

		$m    = $part['match'];
		$tag  = $m[2][0];
		$atts = shortcode_parse_atts( $m[3][0] );
		$atts = is_array( $atts ) ? $atts : [];
		$item = null;

		// شورت‌کد دوطرفه ([x]…[/x]) یا با escape ([[x]]) دست‌نخورده می‌ماند
		$plain = '' === ( $m[1][0] ?? '' ) && '' === ( $m[6][0] ?? '' ) && '' === ( $m[5][0] ?? '' );

		if ( $plain ) {
			$item = match ( true ) {
				'section07' === $tag       => [ 'type' => 'intro' ],
				'section03' === $tag       => [ 'type' => 'categories' ],
				'section09' === $tag       => [ 'type' => 'blog' ],
				'section10' === $tag       => [ 'type' => 'skip' ],
				// شورت‌کد latest-products فقط limit را می‌پذیرفت
				'latest-products' === $tag => [ 'type' => 'latest', 'limit' => (int) ( $atts['limit'] ?? 12 ) ],
				isset( $cat_map[ $tag ] ) && ( ! isset( $atts['bg_color'] ) || isset( $tone_by_css[ $atts['bg_color'] ] ) ) => [
					'type'     => 'products',
					'category' => $tag,
					'title'    => isset( $atts['title'] ) ? sanitize_text_field( (string) $atts['title'] ) : '',
					'limit'    => (int) ( $atts['limit'] ?? 12 ),
					'tone'     => isset( $atts['bg_color'] ) ? $tone_by_css[ $atts['bg_color'] ] : ( $cat_tones[ $tag ] ?? 'primary' ),
				],
				default                    => null,
			};
		}

		// شورت‌کد ناشناخته: اگر تنها در خط خودش بود (مثل [hodima-Stories] یا [hodima-slider1]) بخش جدا؛
		// وگرنه (داخل متن/تگ) جزو متن اطرافش می‌ماند
		if ( null === $item ) {
			if ( ! empty( $part['standalone'] ) ) {
				$flush();
				$items[] = [ 'type' => 'custom', 'enabled' => true, 'html' => trim( (string) $part['raw'] ) ];
			} else {
				$pending .= (string) $part['raw'];
			}
			continue;
		}

		$flush();

		if ( 'skip' !== $item['type'] ) {
			$items[] = [ 'enabled' => true ] + $item;
		}
	}

	$flush();

	$id = 0;
	foreach ( $items as &$item ) {
		++$id;
		$item['id'] = 'i' . $id;
	}
	unset( $item );

	return hodima_home_normalize_layout( $items );
}

/**
 * رنگ زمینه‌ای که هر شورت‌کد دسته قبلا داشت: سه رنگ برند به نوبت، به ترتیب
 * فهرست دسته‌ها (همان منطق logic.php؛ latest-products قبلا ثبت شده و نوبت نمی‌گیرد).
 *
 * @param array<string, string> $cat_map
 * @return array<string, string>
 */
function hodima_home_legacy_category_tones( array $cat_map ): array {

	$cycle = [ 'primary', 'secondary', 'third' ];
	$tones = [];
	$index = 0;

	foreach ( array_keys( $cat_map ) as $slug ) {
		if ( 'latest-products' === $slug || preg_match( '/^section\d{2}$/', (string) $slug ) ) {
			continue;
		}
		$tones[ $slug ] = $cycle[ $index % 3 ];
		++$index;
	}

	return $tones;
}

/* =========================================================================
 * ۴. رندر بخش‌ها (front-page.php)
 * ========================================================================= */

/*
 * front-page.php فقط وقتی انتخاب شود که چیدمان فعال است. وگرنه وردپرس مثل
 * قبل از نبودن این فایل پیش می‌رود (قالب برگه انتخاب‌شده در ویرایشگر،
 * page.php یا index.php) — هیچ تغییری برای سایتی که چیدمان را روشن نکرده.
 */
add_filter( 'frontpage_template', static fn( $template ) => hodima_home_builder_active() ? $template : '' );

/**
 * چاپ یک بخش چیدمان.
 *
 * @param array<string, mixed> $item
 */
function hodima_home_render_section( array $item ): void {

	$args = array_diff_key( $item, [ 'id' => 1, 'type' => 1, 'enabled' => 1 ] );

	switch ( $item['type'] ) {

		case 'intro':
		case 'categories':
		case 'blog':
			get_template_part( 'home/parts/' . $item['type'], null, $args );
			break;

		case 'latest':
			if ( function_exists( 'hodima_home_product_slider' ) ) {
				echo hodima_home_product_slider( [ // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- خروجی ساخته‌شده و escape‌شده در logic.php
					'title'    => $args['title'],
					'link'     => hodima_home_link_url( (string) $args['link'] ),
					'category' => '',
					'limit'    => (int) $args['limit'],
					'bg_color' => hodima_home_tones()[ $args['tone'] ]['css'] ?? 'transparent',
				] );
			}
			break;

		case 'products':
			$term = '' !== $args['category'] ? get_term_by( 'slug', (string) $args['category'], 'product_cat' ) : false;
			if ( $term instanceof WP_Term && function_exists( 'hodima_home_product_slider' ) ) {
				$link = get_term_link( $term );
				echo hodima_home_product_slider( [ // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- خروجی ساخته‌شده و escape‌شده در logic.php
					'title'    => '' !== (string) $args['title'] ? $args['title'] : $term->name,
					'link'     => is_wp_error( $link ) ? '' : $link,
					'category' => $term->slug,
					'limit'    => (int) $args['limit'],
					'bg_color' => hodima_home_tones()[ $args['tone'] ]['css'] ?? 'transparent',
				] );
			}
			break;

		case 'content':
			the_content();
			break;

		case 'custom':
			if ( '' !== (string) $args['html'] ) {
				echo do_shortcode( (string) $args['html'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- محتوای مدیر (هنگام ذخیره پاک‌سازی شد)
			}
			break;
	}
}

/* =========================================================================
 * ۵. پیشخوان و کش
 * ========================================================================= */

// تب «صفحه اصلی» در تنظیمات قالب (ثبت گزینه، فهرست بخش‌ها)
if ( is_admin() ) {
	require_once __DIR__ . '/theme-settings/home-layout-admin.php';
}

// با تغییر چیدمان، نسخه کش‌شده صفحه اصلی (لایت‌اسپید) پاک شود
add_action( 'update_option_' . HODIMA_HOME_LAYOUT_OPTION, 'hodima_home_layout_purge' );
add_action( 'add_option_' . HODIMA_HOME_LAYOUT_OPTION, 'hodima_home_layout_purge' );

function hodima_home_layout_purge(): void {
	if ( function_exists( 'hodima_litespeed_purge_home' ) ) {
		hodima_litespeed_purge_home();
	}
}
