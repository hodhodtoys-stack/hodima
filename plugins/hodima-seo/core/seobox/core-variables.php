<?php
/**
 * SeoBox — shared core (loaded on admin and front-end)
 * Path: core/seobox/core-variables.php
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* =====================================================================
 * دامنه: نوع‌های پستی و تکسونومی‌ها
 * ===================================================================== */

/**
 * نوع‌های پستی که سئوباکس دارند.
 * نسخه قبلی این فهرست را در چهار جای مختلف هاردکد داشت و ناهماهنگ بود:
 * متاباکس «video» را داشت ولی فیلتر و ستون فهرست نه.
 *
 * @return list<string>
 */
function seobox_post_types(): array {
	$types = (array) apply_filters( 'seobox_post_types', [ 'post', 'page', 'product', 'video' ] );
	return array_values( array_unique( array_filter( $types, 'is_string' ) ) );
}

/**
 * تکسونومی‌هایی که سئوباکس دارند.
 *
 * برچسب محصول و ویژگی‌های ووکامرسِ دارای آرشیو عمومی اضافه شدند: روی
 * سایت متای سئوی همه ترم‌ها خوانده می‌شد ولی این‌ها در پیشخوان کادری
 * نداشتند. ویژگی‌ها در init ثبت می‌شوند؛ پیش از آن فهرست پایه برمی‌گردد
 * (همه هوک‌های سئوباکس که به این فهرست نیاز دارند در init یا بعد از آن
 * ثبت می‌شوند).
 *
 * @return list<string>
 */
function seobox_taxonomies(): array {

	$taxonomies = [ 'category', 'post_tag', 'product_cat', 'product_tag' ];

	if ( did_action( 'init' ) && function_exists( 'wc_get_attribute_taxonomy_names' ) ) {
		foreach ( (array) wc_get_attribute_taxonomy_names() as $attribute ) {
			if ( is_string( $attribute ) && taxonomy_exists( $attribute ) && is_taxonomy_viewable( $attribute ) ) {
				$taxonomies[] = $attribute;
			}
		}
	}

	$taxonomies = (array) apply_filters( 'seobox_taxonomies', $taxonomies );
	return array_values( array_unique( array_filter( $taxonomies, 'is_string' ) ) );
}

/* =====================================================================
 * متغیرهای عنوان و توضیحات
 * ===================================================================== */

/** جداکننده عنوان؛ همان فیلتر وردپرس تا با هر جای دیگر یکسان باشد. */
function seobox_separator(): string {
	return (string) apply_filters( 'document_title_separator', '-' );
}

/** شماره صفحه فعلی سایت (آرشیو: paged، نوشته چندصفحه‌ای و صفحه اصلی ثابت: page). */
function seobox_page_number(): int {
	if ( is_admin() ) {
		return 1;
	}
	return max( 1, (int) get_query_var( 'paged' ), (int) get_query_var( 'page' ) );
}

/** برچسب شماره صفحه («صفحه ۲») یا رشته خالی در صفحه اول. */
function seobox_page_label( ?int $page = null ): string {
	$page ??= seobox_page_number();
	return $page > 1 ? 'صفحه ' . $page : '';
}

/**
 * مقدار متغیرهایی که به خود صفحه وابسته نیستند (برای پیش‌نمایش پنل هم).
 *
 * %currentyear% و %currentmonth% از wp_date() می‌آیند که در این سایت از
 * مبدل تقویم شمسی عبور می‌کند — برای عنوان فارسی همین مطلوب است.
 * %gyear% سال میلادی است و عمدا با DateTime بومی ساخته می‌شود تا از
 * آن مبدل عبور نکند.
 *
 * @return array<string, string>
 */
function seobox_static_variables(): array {
	return [
		'%sitename%'     => wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES ),
		'%sitedesc%'     => wp_specialchars_decode( (string) get_bloginfo( 'description' ), ENT_QUOTES ),
		'%sep%'          => seobox_separator(),
		'%currentyear%'  => (string) wp_date( 'Y' ),
		'%currentmonth%' => (string) wp_date( 'F' ),
		'%gyear%'        => ( new DateTimeImmutable( 'now', wp_timezone() ) )->format( 'Y' ),
	];
}

/**
 * متغیرهای قابل استفاده در عنوان و توضیحات.
 *
 * @param string|null $title مقدار %title%؛ null یعنی نام نوشته/ترم.
 */
function seobox_parse_variables( string $text, int $object_id, string $object_type = 'post', ?string $title = null ): string {

	if ( ! str_contains( $text, '%' ) ) {
		return $text;
	}

	if ( null === $title ) {
		$title = '';
		if ( 'term' === $object_type ) {
			$term  = $object_id > 0 ? get_term( $object_id ) : null;
			$title = $term instanceof WP_Term ? $term->name : '';
		} elseif ( $object_id > 0 ) {
			$title = (string) get_post_field( 'post_title', $object_id );
		}
	}

	$replacements = [ '%title%' => $title, '%page%' => seobox_page_label() ] + seobox_static_variables();

	return seobox_tidy_text( str_replace( array_keys( $replacements ), array_values( $replacements ), $text ) );
}

/**
 * فاصله‌ها و جداکننده‌های اضافه‌ای که متغیر خالی (مثل %page% در صفحه اول
 * یا %sitedesc% بدون شعار) جا می‌گذارد: «الف - - ب» ← «الف - ب».
 */
function seobox_tidy_text( string $text ): string {

	$text = trim( (string) preg_replace( '/\s+/u', ' ', $text ) );
	$sep  = trim( seobox_separator() );

	if ( '' !== $sep ) {
		// فقط جداکننده‌ای که با فاصله جدا شده؛ «کش--مو» یا «۱۴۰۴-۱۴۰۵» دست نمی‌خورد
		$q    = preg_quote( $sep, '/' );
		$text = (string) preg_replace( '/\s' . $q . '(?:\s+' . $q . ')+\s/u', ' ' . $sep . ' ', $text );
		$text = trim( (string) preg_replace( [ '/^' . $q . '\s+/u', '/\s+' . $q . '$/u' ], '', $text ) );
	}

	return $text;
}

/**
 * الگوی پیش‌فرض عنوان وقتی عنوان سئو خالی است.
 *
 * صفحه اصلی «نام سایت - شعار» می‌گیرد؛ قبلا «%title% %sep% %sitename%»
 * بود و چون %title% در صفحه اصلی همان نام سایت است، عنوان «هدهدلی - هدهدلی» می‌شد.
 */
function seobox_default_title_pattern( bool $is_front_page = false ): string {
	return $is_front_page ? '%sitename% %sep% %sitedesc%' : '%title% %sep% %sitename%';
}

/** آیا این نوشته برگه ثابت صفحه اصلی است؟ */
function seobox_is_front_page_post( int $post_id ): bool {
	return $post_id > 0 && 'page' === get_option( 'show_on_front' ) && (int) get_option( 'page_on_front' ) === $post_id;
}

/* =====================================================================
 * توضیحات جایگزین (یک قاعده برای پنل و سایت)
 * ===================================================================== */

/**
 * متن ساده برای توضیحات: بدون شورت‌کد و تگ، یک خط، حداکثر ۱۶۰ نویسه و
 * بریده‌شده روی مرز کلمه.
 *
 * نسخه قبلی پنل را با ۱۵۵ نویسه بی‌مرز و سایت را با ۱۶۰ نویسه می‌برید؛
 * متن «اگر خالی بماند: …» با خروجی واقعی فرق داشت.
 */
function seobox_plain_text( string $text, int $max = 160 ): string {

	$text = wp_strip_all_tags( strip_shortcodes( $text ) );
	// شورت‌کد افزونه‌ای که دیگر نصب نیست ثبت نشده و strip_shortcodes آن را نمی‌شناسد
	$text = (string) preg_replace( '/\[\/?[a-z][\w-]*(?:\s[^\]]*)?\]/i', ' ', $text );
	$text = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
	$text = trim( (string) preg_replace( '/\s+/u', ' ', $text ) );

	if ( mb_strlen( $text ) > $max ) {
		$cut  = mb_substr( $text, 0, $max - 3 );
		$last = mb_strrpos( $cut, ' ' );
		$text = ( false !== $last && $last > (int) ( $max * 0.6 ) ? mb_substr( $cut, 0, $last ) : $cut ) . '…';
	}

	return $text;
}

/** توضیحات جایگزین یک نوشته: خلاصه، وگرنه متن. نوشته رمزدار هیچ (متنش نباید منتشر شود). */
function seobox_post_fallback_description( WP_Post $post ): string {

	if ( '' !== $post->post_password ) {
		return '';
	}

	$text = '' !== trim( $post->post_excerpt ) ? $post->post_excerpt : $post->post_content;
	return seobox_plain_text( $text );
}

/** توضیحات جایگزین یک ترم: توضیح خود ترم. */
function seobox_term_fallback_description( WP_Term $term ): string {
	return seobox_plain_text( $term->description );
}

/* =====================================================================
 * ربات‌ها
 * ===================================================================== */

/**
 * دستورات ربات ذخیره‌شده، همیشه به شکل آرایه.
 *
 * نسخه قبلی مقدار را مستقیم به in_array() می‌داد. اگر ردیف قدیمی به صورت
 * رشته ذخیره شده بود ("noindex,follow")، با declare(strict_types=1)
 * خطای کشنده TypeError رخ می‌داد و متاباکس سئو باز نمی‌شد.
 *
 * @return array{index: bool, follow: bool}
 */
function seobox_normalize_robots( mixed $raw ): array {

	if ( is_string( $raw ) ) {
		$raw = preg_split( '/[\s,]+/', strtolower( $raw ), -1, PREG_SPLIT_NO_EMPTY );
	}

	$raw = array_map( 'strtolower', array_map( 'strval', array_filter( (array) $raw, 'is_scalar' ) ) );

	return [
		'index'  => ! in_array( 'noindex', $raw, true ),
		'follow' => ! in_array( 'nofollow', $raw, true ),
	];
}

/**
 * وضعیت نهایی ربات یک نوشته/ترم — همان چیزی که روی سایت چاپ می‌شود.
 *
 * index از سئوباکس *و* تشخیص واحد Core (hodima_is_noindex) می‌آید. قبلا
 * متاتگ فقط _seobox_robots را می‌خواند، در حالی که سایت‌مپ، IndexNow و
 * خوشه‌بندی کلیدهای قدیمی (یواست/AIOSEO/…_noindex) را هم noindex
 * می‌دانستند؛ صفحه از سایت‌مپ حذف می‌شد ولی خودش «index» می‌گفت.
 *
 * @return array{index: bool, follow: bool, external: bool} external یعنی noindex از منبعی جز سئوباکس
 */
function seobox_object_robots( int $id, string $type ): array {

	if ( $id <= 0 ) {
		return [ 'index' => true, 'follow' => true, 'external' => false ];
	}

	$meta_type = 'term' === $type ? 'term' : 'post';
	$flags     = seobox_normalize_robots( get_metadata( $meta_type, $id, '_seobox_robots', true ) );
	$external  = $flags['index'] && function_exists( 'hodima_is_noindex' ) && hodima_is_noindex( $id, $meta_type );

	return [ 'index' => $flags['index'] && ! $external, 'follow' => $flags['follow'], 'external' => $external ];
}

/**
 * کلیدهای متای noindex قدیمی (غیر سئوباکس) با همان قاعده hodima_is_noindex.
 *
 * @return list<string>
 */
function seobox_legacy_noindex_keys( int $id, string $type ): array {

	if ( $id <= 0 ) {
		return [];
	}

	$keys = [];

	foreach ( (array) get_metadata( 'term' === $type ? 'term' : 'post', $id ) as $key => $values ) {

		$lower = strtolower( (string) $key );
		$known = in_array( $lower, [ '_yoast_wpseo_meta-robots-noindex', '_aioseo_robots_noindex' ], true );

		if ( ! $known && 'noindex' !== $lower && ! str_ends_with( $lower, '_noindex' ) ) {
			continue;
		}

		foreach ( (array) $values as $value ) {
			if ( in_array( strtolower( trim( (string) $value ) ), [ '1', 'yes', 'true', 'on' ], true ) ) {
				$keys[] = (string) $key;
				break;
			}
		}
	}

	return $keys;
}

/**
 * شناسه همه نوشته‌ها/ترم‌های noindex، با همان قاعده hodima_is_noindex
 * (برای فیلتر فهرست پیشخوان؛ یک کوئری به جای یکی برای هر ردیف).
 *
 * @param string      $type     'post' یا 'term'
 * @param string|null $taxonomy برای ترم: فقط همین تکسونومی
 * @return list<int>
 */
function seobox_noindex_ids( string $type, ?string $taxonomy = null ): array {

	global $wpdb;

	$where = "( ( m.meta_key = 'noindex' OR m.meta_key LIKE '%\\_noindex' ) AND LOWER( m.meta_value ) IN ( '1', 'yes', 'true', 'on' ) )
		OR ( m.meta_key = '_seobox_robots' AND LOWER( m.meta_value ) LIKE '%noindex%' )";

	if ( 'term' === $type ) {
		$sql = "SELECT DISTINCT m.term_id FROM {$wpdb->termmeta} m";
		if ( null !== $taxonomy ) {
			$sql .= $wpdb->prepare( " INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_id = m.term_id AND tt.taxonomy = %s", $taxonomy );
		}
	} else {
		$sql = "SELECT DISTINCT m.post_id FROM {$wpdb->postmeta} m";
	}

	$ids = array_values( array_unique( array_map( 'intval', (array) $wpdb->get_col( "{$sql} WHERE {$where}" ) ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL -- ثابت یا prepare‌شده

	if ( $ids ) {
		update_meta_cache( 'term' === $type ? 'term' : 'post', $ids ); // بررسی پایین بدون کوئری جدا برای هر شناسه
	}

	return array_values( array_filter(
		$ids,
		// فیلتر hodima_is_noindex ممکن است نتیجه را عوض کند؛ آخرین حرف با همان تابع
		static fn( int $id ): bool => $id > 0 && ( ! function_exists( 'hodima_is_noindex' ) || hodima_is_noindex( $id, 'term' === $type ? 'term' : 'post' ) )
	) );
}

/* =====================================================================
 * دسترسی و پاک‌سازی ورودی
 * ===================================================================== */

/**
 * آیا کاربر فعلی تنظیمات پیشرفته (ربات‌ها، canonical، پیش‌نمایش) را دارد؟
 *
 * قبلا هر نویسنده یا مشارکت‌کننده‌ای که نوشته خودش را ویرایش می‌کرد
 * می‌توانست canonical را به دامنه دیگری بدهد یا صفحه را noindex کند.
 * پیش‌فرض: ویرایشگر و مدیر.
 */
function seobox_can_edit_advanced(): bool {
	return current_user_can( (string) apply_filters( 'seobox_advanced_capability', 'edit_others_posts' ) );
}

/**
 * متن یک‌خطی عنوان/توضیحات.
 *
 * sanitize_text_field هر «%» با دو نویسه هگز بعدش را کد درصدی می‌داند و
 * حذف می‌کند: «%title%ab» ← «%title». «%» پیش از پاک‌سازی موقتا با یک
 * نویسه خصوصی یونیکد جایگزین و بعد برگردانده می‌شود.
 */
function seobox_sanitize_text( string $raw ): string {
	$guard = "\u{E000}";
	return str_replace( $guard, '%', sanitize_text_field( str_replace( '%', $guard, $raw ) ) );
}

/**
 * آدرس canonical دستی.
 *
 * @return string|false رشته خالی = خالی، false = نامعتبر (مقدار قبلی دست نمی‌خورد)
 *
 * قبلا آدرس با نامک فارسی («https://site/محصول/گل-سر/») در
 * FILTER_VALIDATE_URL رد می‌شد (فقط ASCII می‌پذیرد) و canonical ذخیره‌شده
 * بی‌صدا *پاک* می‌شد. حالا نویسه‌های غیر ASCII کد درصدی می‌شوند (همان
 * شکلی که مرورگر می‌فرستد) و آدرس نسبی («/path/») به آدرس سایت کامل می‌شود.
 */
function seobox_sanitize_canonical( string $raw ): string|false {

	$url = trim( $raw );

	if ( '' === $url ) {
		return '';
	}

	if ( str_starts_with( $url, '/' ) && ! str_starts_with( $url, '//' ) ) {
		$url = home_url( $url );
	}

	$url = preg_replace_callback( '/[^\x21-\x7e]+/u', static fn( array $m ): string => rawurlencode( $m[0] ), $url );

	if ( ! is_string( $url ) ) {
		return false; // UTF-8 نامعتبر
	}

	$url = esc_url_raw( $url, [ 'http', 'https' ] );

	return ( '' !== $url && false !== filter_var( $url, FILTER_VALIDATE_URL ) ) ? $url : false;
}

/** آدرس canonical ذخیره‌شده برای نمایش خوانا در فیلد (نامک فارسی به جای %D9%…). */
function seobox_display_url( string $url ): string {
	return '' === $url ? '' : rawurldecode( $url );
}

/* =====================================================================
 * ذخیره مشترک پست و ترم
 * ===================================================================== */

/**
 * @param string $type 'post' یا 'term'
 */
function seobox_save_fields( int $object_id, string $type ): void {

	$meta_type = 'term' === $type ? 'term' : 'post';

	$input = static function ( string $key ): ?string {
		// phpcs:ignore WordPress.Security.NonceVerification -- nonce در فراخواننده بررسی شده
		return isset( $_POST[ $key ] ) && is_scalar( $_POST[ $key ] ) ? (string) wp_unslash( $_POST[ $key ] ) : null;
	};

	/*
	 * هر فیلد اعتبارسنجی و اگر خالی یا برابر پیش‌فرض بود *حذف* می‌شود.
	 *
	 * نسخه قدیمی برای هر پست و ترمی که ذخیره می‌شد هشت ردیف متا
	 * می‌نوشت — حتی بدون هیچ تنظیم سئو. روی یک فروشگاه با هزاران محصول
	 * یعنی ده‌ها هزار ردیف بی‌مصرف.
	 */
	$values = [];

	foreach ( [ 'title', 'description' ] as $field ) {
		$raw = $input( 'seobox_' . $field );
		if ( null !== $raw ) {
			// توضیحات متا یک خط است؛ خط جدید در اسنیپت نمایش داده نمی‌شود
			$values[ $field ] = seobox_sanitize_text( $raw );
		}
	}

	// فیلدهای پیشرفته فقط برای کاربر مجاز؛ برای بقیه مقدار فعلی دست نمی‌خورد
	if ( seobox_can_edit_advanced() ) {

		$raw = $input( 'seobox_canonical' );
		if ( null !== $raw ) {
			$url = seobox_sanitize_canonical( $raw );
			if ( false === $url ) {
				if ( function_exists( 'hodima_admin_flash' ) ) {
					hodima_admin_flash( sprintf( 'سئوباکس: آدرس canonical «<code dir="ltr">%s</code>» معتبر نیست و ذخیره نشد؛ مقدار قبلی حفظ شد. آدرس کامل با http(s) یا مسیری که با / شروع می‌شود وارد کنید.', esc_html( $raw ) ), 'error' );
				}
			} else {
				$values['canonical'] = $url;
			}
		}

		/*
		 * این مقادیر مستقیما در متاتگ robots قرار می‌گیرند. نسخه قدیمی هیچ
		 * اعتبارسنجی نداشت؛ مقدار «5, noindex» در فیلد اسنیپت یک دستور
		 * noindex پنهان در خروجی می‌ساخت.
		 */
		foreach ( [ 'adv_snippet', 'adv_video' ] as $field ) {
			$raw = $input( 'seobox_' . $field );
			if ( null !== $raw ) {
				$raw               = trim( $raw );
				$values[ $field ] = ( '' !== $raw && ctype_digit( $raw ) ) ? (string) (int) $raw : '';
			}
		}

		$raw = $input( 'seobox_adv_image' );
		if ( null !== $raw ) {
			$raw = sanitize_key( $raw );
			// «large» پیش‌فرض است؛ فقط انحراف از پیش‌فرض ذخیره می‌شود
			$values['adv_image'] = in_array( $raw, [ 'none', 'standard' ], true ) ? $raw : '';
		}

		// ربات‌ها: فقط وقتی با پیش‌فرض (index, follow) فرق دارد
		$index  = $input( 'seobox_robot_index' );
		$follow = $input( 'seobox_robot_follow' );

		if ( null !== $index && null !== $follow ) {
			$index  = 'noindex' !== sanitize_key( $index );
			$follow = 'nofollow' !== sanitize_key( $follow );

			if ( $index && $follow ) {
				delete_metadata( $meta_type, $object_id, '_seobox_robots' );
			} else {
				update_metadata( $meta_type, $object_id, '_seobox_robots', [ $index ? 'index' : 'noindex', $follow ? 'follow' : 'nofollow' ] );
			}
		}

		/*
		 * noindex قدیمی (یواست/AIOSEO/…_noindex) فقط با تیک صریح مدیر پاک
		 * می‌شود؛ فقط کلیدهایی که الان واقعا noindex می‌سازند.
		 */
		if ( '1' === $input( 'seobox_clear_legacy_noindex' ) ) {
			foreach ( seobox_legacy_noindex_keys( $object_id, $meta_type ) as $key ) {
				delete_metadata( $meta_type, $object_id, $key );
			}
		}
	}

	foreach ( $values as $field => $value ) {
		'' === $value
			? delete_metadata( $meta_type, $object_id, '_seobox_' . $field )
			: update_metadata( $meta_type, $object_id, '_seobox_' . $field, $value );
	}

	// فیلد «کلمه کلیدی کانونی» در رابط وجود ندارد و ذخیره نمی‌شود؛
	// مقدار قدیمی (اگر باشد) دست‌نخورده می‌ماند.
}
