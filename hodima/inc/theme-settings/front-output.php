<?php
/**
 * Theme Settings — توابع نمایشی برای قالب: لوگو، کانال‌های تماس، شبکه‌های اجتماعی، نمادهای اعتماد
 * Path: hodima/inc/theme-settings/front-output.php
 *
 * از theme-settings.php جدا شد (3.6.3، بخش ۱۶۱ HODIMA-AUDIT.md): کد بدون تغییر، فقط جابه‌جا؛
 * ترتیب بارگذاری (و ثبت هوک‌ها) همان ترتیب قبلی فایل است (فهرست: theme-settings.php).
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* =========================================================================
 * ۴. توابع نمایشی برای قالب
 * ========================================================================= */

/** HTML لوگوی هدر: تصویر تنظیمات ← لوگوی سفارشی وردپرس ← نام سایت. */
function hodima_logo_html(): string {

	$site_name = get_bloginfo( 'name' );
	$logo_id   = (int) hodima_setting( 'logo_id' );

	if ( ! $logo_id ) {
		$logo_id = (int) get_theme_mod( 'custom_logo' );
	}

	if ( $logo_id && wp_attachment_is_image( $logo_id ) ) {
		return (string) wp_get_attachment_image( $logo_id, 'medium', false, [
			'alt'           => $site_name,
			'loading'       => 'eager',
			'fetchpriority' => 'high',
			'decoding'      => 'async',
			'class'         => 'header__logo-img',
		] );
	}

	return '<span class="header__logo-text">' . esc_html( $site_name ) . '</span>';
}

/**
 * کانال‌های تماس پیکربندی‌شده برای پنجره پشتیبانی، به ترتیب enum
 * Hodima_Contact_Channel (تماس، واتس‌اپ، روبیکا، تلگرام). کانال بدون مقدار رد می‌شود.
 *
 * @return list<Hodima_Contact_Link>
 */
function hodima_contact_channels(): array {

	$links = [];

	foreach ( Hodima_Contact_Channel::cases() as $channel ) {
		$value = (string) hodima_setting( $channel->setting() );
		if ( '' !== $value ) {
			$links[] = new Hodima_Contact_Link( $channel, $value );
		}
	}

	return $links;
}

/**
 * یک ردیف شبکه ← شکل استاندارد (بی‌آدرس = null). آدرس بدون http(s) با https:// کامل می‌شود؛
 * نام خالی از دامنه آدرس ساخته می‌شود.
 *
 * @param array<string, mixed> $row
 * @return array{id:string, label:string, url:string, icon_id:int}|null
 */
function hodima_social_clean_item( array $row ): ?array {

	$url = trim( (string) ( $row['url'] ?? '' ) );
	if ( '' !== $url && ! preg_match( '#^[a-z][a-z0-9+.-]*:#i', $url ) ) {
		$url = 'https://' . ltrim( $url, '/' );
	}
	$url = esc_url_raw( $url, [ 'https', 'http' ] );
	if ( '' === $url ) {
		return null;
	}

	$label = mb_substr( sanitize_text_field( (string) ( $row['label'] ?? '' ) ), 0, 40 );
	if ( '' === $label ) {
		$label = (string) preg_replace( '/^www\./', '', (string) wp_parse_url( $url, PHP_URL_HOST ) );
	}

	$icon = absint( $row['icon_id'] ?? 0 );

	return [
		'id'      => preg_replace( '/[^a-z0-9]/', '', strtolower( (string) ( $row['id'] ?? '' ) ) ) ?: 'n' . substr( md5( $url . $label ), 0, 8 ),
		'label'   => $label,
		'url'     => $url,
		'icon_id' => $icon && wp_attachment_is_image( $icon ) ? $icon : 0,
	];
}

/**
 * شبکه‌های قدیمی (۳.۲.۲ و پیش از آن): دو کلید social_{شبکه}_url و _icon در گزینه تنظیمات.
 *
 * @param array<string, mixed> $settings گزینه خام تنظیمات
 * @return list<array{id:string, label:string, url:string, icon_id:int}>
 */
function hodima_social_legacy_items( array $settings ): array {

	$items = [];

	foreach ( hodima_social_legacy_networks() as $key => $label ) {
		$item = hodima_social_clean_item( [
			'id'      => 'legacy' . $key,
			'label'   => $label,
			'url'     => (string) ( $settings[ "social_{$key}_url" ] ?? '' ),
			'icon_id' => (int) ( $settings[ "social_{$key}_icon" ] ?? 0 ),
		] );
		if ( $item ) {
			$items[] = $item;
		}
	}

	return $items;
}

/** آیا مدیر فهرست تازه را ذخیره کرده؟ تا آن موقع شبکه‌های قدیمی خوانده می‌شوند (بدون مهاجرت مخرب). */
function hodima_social_saved(): bool {
	return is_array( get_option( HODIMA_SOCIAL_OPTION, false ) );
}

/**
 * شبکه‌های اجتماعی نوار فوتر، به ترتیب مدیر.
 *
 * @return list<array{id:string, label:string, url:string, icon_id:int}>
 */
function hodima_social_links(): array {

	if ( ! hodima_social_saved() ) {
		return hodima_social_legacy_items( (array) get_option( HODIMA_SETTINGS_OPTION, [] ) );
	}

	$items = [];

	foreach ( (array) get_option( HODIMA_SOCIAL_OPTION, [] ) as $row ) {
		$item = is_array( $row ) ? hodima_social_clean_item( $row ) : null;
		if ( $item ) {
			$items[] = $item;
		}
	}

	return array_slice( $items, 0, HODIMA_SOCIAL_MAX );
}

/**
 * پاک‌سازی گزینه. دو شکل ورودی:
 * - فرم پیشخوان: کلید `_sent` + هر ردیف با کلید یکتا (ترتیب = ترتیب ارسال)؛ ردیف بی‌آدرس کنار گذاشته می‌شود.
 * - فهرست تمیز (list): وردپرس هنگام ساخته شدن اولین‌باره گزینه (update_option ← add_option) callback را
 *   «دوباره» روی مقدار پاک‌شده اجرا می‌کند؛ بدون این شاخه اولین ذخیره همیشه فهرست خالی می‌شد (باگ 3.2.3 و 3.2.4).
 * هر چیز دیگر (ذخیره از جای دیگر): مقدار قبلی می‌ماند.
 *
 * @return list<array{id:string, label:string, url:string, icon_id:int}>
 */
function hodima_social_sanitize( mixed $input ): array {

	if ( ! is_array( $input ) || ( ! isset( $input['_sent'] ) && ! array_is_list( $input ) ) ) {
		return hodima_social_links();
	}

	$items = [];

	foreach ( $input as $uid => $row ) {
		if ( ! is_array( $row ) ) {
			continue;
		}
		$item = hodima_social_clean_item( ( is_string( $uid ) ? [ 'id' => $uid ] : [] ) + $row );
		if ( $item ) {
			$items[] = $item;
		}
	}

	return array_slice( $items, 0, HODIMA_SOCIAL_MAX );
}

/**
 * نمادهای اعتماد دارای تصویر، به ترتیب ۱ تا ۳ (نماد بدون تصویر رد می‌شود).
 *
 * @return list<array{image_id:int, url:string}>
 */
function hodima_trust_badges(): array {

	$badges = [];

	for ( $i = 1; $i <= HODIMA_TRUST_SLOTS; $i++ ) {
		$suffix   = 1 === $i ? '' : "_{$i}";
		$image_id = (int) hodima_setting( "trust_image_id{$suffix}" );

		if ( $image_id && wp_attachment_is_image( $image_id ) ) {
			$badges[] = [ 'image_id' => $image_id, 'url' => (string) hodima_setting( "trust_url{$suffix}" ) ];
		}
	}

	return $badges;
}

/**
 * مسیر یک آدرس برای مقایسه: بدون دامنه، پارامتر و پوشه نصب وردپرس، رمزگشایی‌شده
 * (نامک فارسی کپی‌شده از نوار آدرس کدگذاری شده است)، با / در ابتدا و انتها.
 */
function hodima_normalize_path( string $url ): string {

	$path = rawurldecode( (string) wp_parse_url( $url, PHP_URL_PATH ) );
	$base = rtrim( (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH ), '/' );

	if ( '' !== $base && str_starts_with( $path, $base . '/' ) ) {
		$path = substr( $path, strlen( $base ) );
	}

	$path = trim( strtolower( $path ), '/' ); // strtolower در PHP 8 فقط ASCII را تغییر می‌دهد؛ نامک فارسی سالم می‌ماند

	return '' === $path ? '/' : '/' . $path . '/';
}

/**
 * آیا نوار شبکه‌های اجتماعی در صفحه فعلی نمایش داده شود؟
 * صفحه‌های فهرست «social_hide_urls» مستثنا هستند؛ خط با * در انتها همه زیرصفحه‌ها را هم می‌گیرد.
 */
function hodima_socials_visible_here(): bool {

	$rules = trim( (string) hodima_setting( 'social_hide_urls' ) );
	$show  = true;

	if ( '' !== $rules ) {
		$current = hodima_normalize_path( (string) wp_unslash( $_SERVER['REQUEST_URI'] ?? '/' ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- فقط مقایسه مسیر

		foreach ( preg_split( '/\R/u', $rules ) ?: [] as $rule ) {

			$rule = trim( $rule );
			if ( '' === $rule ) {
				continue;
			}

			$prefix = str_ends_with( $rule, '*' );
			$path   = hodima_normalize_path( rtrim( $rule, '*' ) );

			if ( $prefix ? str_starts_with( $current, $path ) : $current === $path ) {
				$show = false;
				break;
			}
		}
	}

	/** برای استثنای برنامه‌نویسی (مثلا بر اساس نوع صفحه). */
	return (bool) apply_filters( 'hodima_show_socials', $show );
}
