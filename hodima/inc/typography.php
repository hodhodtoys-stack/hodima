<?php
/**
 * تایپوگرافی: فونت، متن، لینک‌ها و تیترهای H1 تا H6 (تب «تایپوگرافی» تنظیمات قالب)
 * Path: hodima/inc/typography.php
 *
 * از 2.9.6. پیش از آن فونت با نام ثابت و !important در style.css قفل بود،
 * اندازه تیترها ثابت و نامرتب (H2 ۱۶px و کوچک‌تر از H3) و رنگ لینک آبی
 * ثابت جدا از پالت. حالا همه مقدارها توکن‌های assets/css/tokens.css‌اند و
 * این فایل فقط مقدارهایی را که در پیشخوان با پیش‌فرض فرق دارند، مثل پالت
 * رنگ (hodima_palette_css)، بعد از tokens.css چاپ می‌کند: سایتی که چیزی را
 * عوض نکرده هیچ CSS اضافه‌ای نمی‌گیرد.
 *
 *   - فونت آپلودی: فایل woff2/woff هر وزن از کتابخانه رسانه (فقط مدیر قالب
 *     می‌تواند فونت آپلود کند؛ محتوای فایل با امضای woff بررسی می‌شود).
 *   - ویرایشگر نوشته‌ها (بلوک و کلاسیک) همان فونت و تیترهای سایت را نشان می‌دهد.
 *   - preload فونت همان فونت انتخاب‌شده است (ui-performance.php).
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/** نام خانواده فونت آپلودی در CSS (نام دلخواه؛ فقط داخل همین سایت). */
const HODIMA_FONT_CUSTOM_FAMILY = 'Hodima Custom';

/** پسوندهای مجاز فایل فونت آپلودی و نوع MIME هر کدام. */
const HODIMA_FONT_MIMES = [
	'woff2' => 'font/woff2',
	'woff'  => 'font/woff',
];

/** امضای چهاربایتی ابتدای فایل (wOF2 / wOFF) برای هر پسوند. */
const HODIMA_FONT_MAGIC = [
	'woff2' => 'wOF2',
	'woff'  => 'wOFF',
];

/* =========================================================================
 * ۱. فونت‌ها
 * ========================================================================= */

/** پیوست کتابخانه رسانه با پسوند woff2/woff است؟ */
function hodima_is_font_attachment( int $id ): bool {

	if ( $id <= 0 || 'attachment' !== get_post_type( $id ) ) {
		return false;
	}

	$ext = strtolower( pathinfo( (string) get_attached_file( $id ), PATHINFO_EXTENSION ) );

	return isset( HODIMA_FONT_MIMES[ $ext ] );
}

/**
 * فایل‌های فونت آپلودی که واقعا هستند: وزن ← آدرس فایل.
 *
 * @return array<int, string>
 */
function hodima_typography_custom_faces(): array {

	$faces = [];

	foreach ( array_keys( hodima_typography_weights() ) as $weight ) {
		$id = (int) hodima_setting( "font_custom_{$weight}" );
		if ( hodima_is_font_attachment( $id ) ) {
			$url = (string) wp_get_attachment_url( $id );
			if ( '' !== $url ) {
				$faces[ (int) $weight ] = $url;
			}
		}
	}

	return $faces;
}

/**
 * فونت واقعی یک انتخاب: «فونت آپلودی» بدون هیچ فایلی همان وزیرمتن است
 * (فایل در کتابخانه رسانه پاک شده باشد، سایت بی‌فونت نماند).
 */
function hodima_typography_font_key( string $choice ): string {

	if ( 'custom' === $choice && ! hodima_typography_custom_faces() ) {
		return 'vazirmatn';
	}

	return in_array( $choice, [ 'vazirmatn', 'custom', 'system' ], true ) ? $choice : 'vazirmatn';
}

/** فونت متن و فونت تیترها (کلید: vazirmatn | custom | system). */
function hodima_typography_body_font(): string {
	return hodima_typography_font_key( (string) hodima_setting( 'font_body' ) );
}

function hodima_typography_heading_font(): string {
	$choice = (string) hodima_setting( 'font_heading' );
	return 'body' === $choice ? hodima_typography_body_font() : hodima_typography_font_key( $choice );
}

/** فهرست فونت CSS (font-family) هر کلید. */
function hodima_typography_stack( string $font ): string {
	return match ( $font ) {
		'custom' => "'" . HODIMA_FONT_CUSTOM_FAMILY . "', Tahoma, sans-serif",
		// فونت رابط کاربری سیستم (ویندوز: Segoe UI، اپل: San Francisco، اندروید: Roboto) — همه فارسی دارند
		'system' => "system-ui, -apple-system, 'Segoe UI', Roboto, Tahoma, sans-serif",
		default  => "'Vazirmatn', Tahoma, sans-serif",
	};
}

/** آدرس امن برای url('…') در CSS (بدون کوتیشن و بک‌اسلش؛ ویرایشگر کلاسیک CSS را در "…" می‌گذارد). */
function hodima_typography_css_url( string $url ): string {
	return str_replace( [ "'", '"', '\\', "\n", "\r", ')' ], [ '%27', '%22', '', '', '', '%29' ], esc_url_raw( $url ) );
}

/**
 * @font-face فونت آپلودی (رشته خالی اگر به کار نرفته). $always: حتی اگر انتخاب
 * نشده (پیش‌نمایش صفحه تنظیمات، پیش از ذخیره انتخاب).
 */
function hodima_typography_font_faces_css( bool $always = false ): string {

	if ( ! $always && 'custom' !== hodima_typography_body_font() && 'custom' !== hodima_typography_heading_font() ) {
		return '';
	}

	$css = '';
	foreach ( hodima_typography_custom_faces() as $weight => $url ) {
		$format = str_ends_with( strtolower( (string) wp_parse_url( $url, PHP_URL_PATH ) ), '.woff' ) ? 'woff' : 'woff2';
		$css   .= sprintf(
			"@font-face{font-family:'%s';src:url('%s') format('%s');font-weight:%d;font-style:normal;font-display:swap}",
			HODIMA_FONT_CUSTOM_FAMILY,
			hodima_typography_css_url( $url ),
			$format,
			$weight
		);
	}

	return $css;
}

/**
 * فایل‌هایی که پیش از CSS بارگذاری شوند: وزن ۴۰۰ فونت متن و ۷۰۰ فونت تیترها
 * (یا نزدیک‌ترین وزن موجود فونت آپلودی). فونت سیستم فایلی ندارد.
 * پیش‌فرض همان دو فایل قبلی: Vazirmatn-Regular و Vazirmatn-Bold.
 *
 * @return list<string>
 */
function hodima_typography_preload_urls(): array {

	$urls = [];

	foreach ( [ [ hodima_typography_body_font(), 400 ], [ hodima_typography_heading_font(), 700 ] ] as [ $font, $weight ] ) {

		$url = match ( $font ) {
			'custom'    => hodima_typography_nearest_face( $weight ),
			'vazirmatn' => hodima_typography_vazirmatn_url( $weight ),
			default     => '',
		};

		if ( '' !== $url && ! in_array( $url, $urls, true ) ) {
			$urls[] = $url;
		}
	}

	return $urls;
}

/** آدرس فایل وزیرمتن همراه قالب (رشته خالی اگر فایل نیست). */
function hodima_typography_vazirmatn_url( int $weight ): string {

	$names = [ 300 => 'Light', 400 => 'Regular', 500 => 'Medium', 600 => 'SemiBold', 700 => 'Bold', 800 => 'ExtraBold', 900 => 'Black' ];
	$file  = 'assets/fonts/Vazirmatn-' . ( $names[ $weight ] ?? 'Regular' ) . '.woff2';

	return is_file( get_theme_file_path( $file ) ) ? get_theme_file_uri( $file ) : '';
}

/** فایل فونت آپلودی با نزدیک‌ترین وزن (همان انتخاب مرورگر در نبود وزن دقیق، تقریبی). */
function hodima_typography_nearest_face( int $weight ): string {

	$faces = hodima_typography_custom_faces();
	if ( ! $faces ) {
		return '';
	}

	uksort( $faces, static fn( int $a, int $b ): int => abs( $a - $weight ) <=> abs( $b - $weight ) ?: $b <=> $a );

	return (string) reset( $faces );
}

/* =========================================================================
 * ۲. CSS تنظیمات (فقط مقدارهای تغییرکرده؛ پیش‌فرض‌ها در tokens.css)
 * ========================================================================= */

/** رنگ انتخابی (کلید hodima_typography_color_options) ← متغیر پالت. */
function hodima_typography_color_var( string $key ): string {
	return 'text' === $key ? 'var(--hodima-text-dark)' : 'var(--hodima-' . sanitize_key( $key ) . ')';
}

/**
 * متغیرهای :root که با پیش‌فرض فرق دارند.
 *
 * @return array<string, string>
 */
function hodima_typography_vars(): array {

	$fields = hodima_settings_fields();
	$vars   = [];

	$changed = static function ( string $key ) use ( $fields ): bool {
		$value   = hodima_setting( $key );
		$default = $fields[ $key ]['default'] ?? null;
		return is_numeric( $default ) ? (float) $value !== (float) $default : (string) $value !== (string) $default;
	};

	// فونت‌ها
	$body    = hodima_typography_body_font();
	$heading = hodima_typography_heading_font();
	if ( 'vazirmatn' !== $body ) {
		$vars['--hodima-font'] = hodima_typography_stack( $body );
	}
	if ( $heading !== $body ) {
		$vars['--hodima-font-heading'] = hodima_typography_stack( $heading );
	}

	// متن و لینک
	if ( $changed( 'body_size' ) ) {
		$vars['--hodima-body-size'] = hodima_number_text( (float) hodima_setting( 'body_size' ) );
	}
	if ( $changed( 'body_line_height' ) ) {
		$vars['--hodima-body-line-height'] = hodima_number_text( (float) hodima_setting( 'body_line_height' ) );
	}
	$text = sanitize_hex_color( (string) hodima_setting( 'color_text' ) );
	if ( $text && $changed( 'color_text' ) ) {
		$vars['--hodima-text-dark'] = strtolower( $text );
	}
	if ( $changed( 'link_color' ) ) {
		$vars['--hodima-link'] = hodima_typography_color_var( (string) hodima_setting( 'link_color' ) );
	}
	if ( $changed( 'link_hover_color' ) ) {
		$vars['--hodima-link-hover'] = hodima_typography_color_var( (string) hodima_setting( 'link_hover_color' ) );
	}
	if ( ! hodima_setting( 'content_link_underline' ) ) {
		$vars['--hodima-content-link-line'] = 'none';
	}

	// تیترها
	foreach ( array_keys( hodima_typography_heading_defaults() ) as $level ) {
		$map = [
			"h{$level}_size"        => "--hodima-h{$level}-size-max",
			"h{$level}_size_mobile" => "--hodima-h{$level}-size-min",
			"h{$level}_weight"      => "--hodima-h{$level}-weight",
			"h{$level}_line_height" => "--hodima-h{$level}-line-height",
		];
		foreach ( $map as $key => $var ) {
			if ( $changed( $key ) ) {
				$vars[ $var ] = hodima_number_text( (float) hodima_setting( $key ) );
			}
		}
		if ( $changed( "h{$level}_color" ) ) {
			$vars[ "--hodima-h{$level}-color" ] = hodima_typography_color_var( (string) hodima_setting( "h{$level}_color" ) );
		}
	}

	return $vars;
}

/** CSS تنظیمات تایپوگرافی، یا رشته خالی با همه تنظیمات پیش‌فرض. */
function hodima_typography_css(): string {

	$css = '';
	foreach ( hodima_typography_vars() as $name => $value ) {
		$css .= $name . ':' . $value . ';';
	}

	return hodima_typography_font_faces_css() . ( '' !== $css ? ':root{' . $css . '}' : '' );
}

/** بعد از tokens.css (همان handle؛ مثل پالت). */
function hodima_print_typography(): void {
	$css = hodima_typography_css();
	if ( '' !== $css && wp_style_is( 'hodima-tokens', 'enqueued' ) ) {
		wp_add_inline_style( 'hodima-tokens', $css );
	}
}
add_action( 'wp_enqueue_scripts', 'hodima_print_typography', 21 );

/** صفحه تنظیمات قالب: فونت آپلودی ذخیره‌شده برای پیش‌نمایش (فقط همان صفحه). */
add_action( 'admin_enqueue_scripts', static function ( string $hook ): void {
	if ( 'appearance_page_' . HODIMA_SETTINGS_PAGE === $hook ) {
		$css = hodima_typography_font_faces_css( true );
		if ( '' !== $css ) {
			wp_add_inline_style( 'hodima-theme-settings', $css );
		}
	}
}, 20 );

/**
 * بررسی پس از ذخیره: «فونت آپلودی» بدون فایل ← وزیرمتن، با پیام.
 *
 * @param array<string, mixed> $clean
 * @return array<string, mixed>
 */
function hodima_typography_validate( array $clean ): array {

	$has_files   = false;
	$has_regular = ! empty( $clean['font_custom_400'] );
	foreach ( array_keys( hodima_typography_weights() ) as $weight ) {
		$has_files = $has_files || ! empty( $clean[ "font_custom_{$weight}" ] );
	}

	$uses_custom = 'custom' === ( $clean['font_body'] ?? '' ) || 'custom' === ( $clean['font_heading'] ?? '' );

	if ( $uses_custom && ! $has_files ) {
		foreach ( [ 'font_body' => 'vazirmatn', 'font_heading' => 'body' ] as $key => $fallback ) {
			if ( 'custom' === ( $clean[ $key ] ?? '' ) ) {
				$clean[ $key ] = $fallback;
			}
		}
		add_settings_error( HODIMA_SETTINGS_OPTION, 'font_custom_missing', '«فونت آپلودی» انتخاب شده بود ولی هیچ فایل فونتی در قاب «فونت آپلودی» نیست؛ وزیرمتن ذخیره شد.' );
	} elseif ( $uses_custom && ! $has_regular ) {
		add_settings_error( HODIMA_SETTINGS_OPTION, 'font_custom_regular', 'فونت آپلودی وزن «معمولی (۴۰۰)» ندارد؛ متن معمولی با نزدیک‌ترین وزن موجود نمایش داده می‌شود. بهتر است فایل وزن ۴۰۰ را هم اضافه کنید.', 'warning' );
	}

	return $clean;
}

/* =========================================================================
 * ۳. آپلود فایل فونت (فقط مدیر قالب)
 * ------------------------------------------------------------------------
 * وردپرس فونت را در کتابخانه رسانه نمی‌پذیرد. اینجا woff2/woff فقط برای
 * کاربری با دسترسی «edit_theme_options» مجاز می‌شود، و چون تشخیص نوع فایل
 * (finfo) روی بیشتر سرورها woff2 را «application/octet-stream» می‌شناسد و
 * وردپرس رد می‌کند، نوع با امضای چهار بایت اول فایل (wOF2/wOFF) تأیید می‌شود.
 * ========================================================================= */

add_filter( 'upload_mimes', 'hodima_typography_upload_mimes' );

/**
 * @param mixed $mimes
 * @return mixed
 */
function hodima_typography_upload_mimes( mixed $mimes ): mixed {

	if ( is_array( $mimes ) && current_user_can( 'edit_theme_options' ) ) {
		$mimes += HODIMA_FONT_MIMES;
	}

	return $mimes;
}

add_filter( 'wp_check_filetype_and_ext', 'hodima_typography_check_font_file', 10, 3 );

/**
 * @param mixed $data  [ext, type, proper_filename]
 * @param mixed $file  مسیر موقت فایل
 * @param mixed $filename نام اصلی فایل
 * @return mixed
 */
function hodima_typography_check_font_file( mixed $data, mixed $file, mixed $filename ): mixed {

	if ( ! is_array( $data ) || ! is_string( $file ) || ! is_string( $filename ) || ! current_user_can( 'edit_theme_options' ) ) {
		return $data;
	}

	$ext = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );
	if ( ! isset( HODIMA_FONT_MAGIC[ $ext ] ) || ! is_readable( $file ) ) {
		return $data;
	}

	$magic = (string) file_get_contents( $file, false, null, 0, 4 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- چهار بایت فایل موقت آپلود

	if ( HODIMA_FONT_MAGIC[ $ext ] === $magic ) {
		$data['ext']  = $ext;
		$data['type'] = HODIMA_FONT_MIMES[ $ext ];
	} else {
		// پسوند فونت ولی محتوای دیگر: رد
		$data['ext']  = false;
		$data['type'] = false;
	}

	return $data;
}

/* =========================================================================
 * ۴. ویرایشگر نوشته‌ها: همان فونت، متن، لینک و تیترهای سایت
 * ------------------------------------------------------------------------
 * assets/css/editor.css (فونت‌ها و قانون‌ها) + tokens.css (مقدارها)؛ مقدارهای
 * تغییرکرده پیشخوان (پالت و تایپوگرافی) جدا به هر دو ویرایشگر داده می‌شوند.
 * ========================================================================= */

add_action( 'after_setup_theme', 'hodima_typography_editor_styles', 11 );

function hodima_typography_editor_styles(): void {
	add_theme_support( 'editor-styles' );
	add_editor_style( [ 'assets/css/tokens.css', 'assets/css/editor.css' ] );
}

/** CSS تنظیمات پیشخوان برای ویرایشگرها (پالت + تایپوگرافی). */
function hodima_typography_editor_css(): string {
	return ( function_exists( 'hodima_palette_css' ) ? hodima_palette_css() : '' ) . hodima_typography_css();
}

add_filter( 'block_editor_settings_all', 'hodima_typography_block_editor_settings' );

/**
 * @param mixed $settings
 * @return mixed
 */
function hodima_typography_block_editor_settings( mixed $settings ): mixed {

	$css = hodima_typography_editor_css();

	if ( is_array( $settings ) && '' !== $css ) {
		$settings['styles']   = is_array( $settings['styles'] ?? null ) ? $settings['styles'] : [];
		$settings['styles'][] = [ 'css' => $css ];
	}

	return $settings;
}

add_filter( 'tiny_mce_before_init', 'hodima_typography_classic_editor' );

/**
 * @param mixed $init
 * @return mixed
 */
function hodima_typography_classic_editor( mixed $init ): mixed {

	$css = hodima_typography_editor_css();

	if ( is_array( $init ) && '' !== $css ) {
		$init['content_style'] = trim( ( is_string( $init['content_style'] ?? null ) ? $init['content_style'] : '' ) . ' ' . str_replace( '"', "'", $css ) );
	}

	return $init;
}

/* =========================================================================
 * ۵. پیش‌نمایش در صفحه تنظیمات (admin.js با هر تغییر متغیرها را عوض می‌کند)
 * ========================================================================= */

function hodima_typography_preview(): void {

	$vars = '';
	foreach ( hodima_typography_vars() as $name => $value ) {
		$vars .= $name . ':' . $value . ';';
	}
	?>
	<div
		class="hodima-type-preview"
		data-hodima-type-preview
		data-mode="desktop"
		data-stacks="<?php echo esc_attr( (string) wp_json_encode( array_combine( [ 'vazirmatn', 'custom', 'system' ], array_map( 'hodima_typography_stack', [ 'vazirmatn', 'custom', 'system' ] ) ) ) ); ?>"
		data-family="<?php echo esc_attr( HODIMA_FONT_CUSTOM_FAMILY ); ?>"
		style="<?php echo esc_attr( $vars ); ?>"
	>
		<div class="hodima-type-preview__modes" role="group" aria-label="اندازه پیش‌نمایش">
			<button type="button" class="hodima-type-preview__mode" data-hodima-type-mode="desktop" aria-pressed="true"><span class="dashicons dashicons-desktop" aria-hidden="true"></span> دسکتاپ</button>
			<button type="button" class="hodima-type-preview__mode" data-hodima-type-mode="mobile" aria-pressed="false"><span class="dashicons dashicons-smartphone" aria-hidden="true"></span> موبایل</button>
		</div>
		<div class="hodima-type-preview__page" aria-hidden="true">
			<?php foreach ( array_keys( hodima_typography_heading_defaults() ) as $level ) : ?>
				<p class="hodima-type-preview__h hodima-type-preview__h<?php echo (int) $level; ?>">تیتر نمونه سطح <?php echo esc_html( hodima_fa_digits( $level ) ); ?> (H<?php echo (int) $level; ?>)</p>
			<?php endforeach; ?>
			<p class="hodima-type-preview__text">این یک متن نمونه برای دیدن خوانایی است؛ کیفیت کالا و ارسال سریع، <span class="hodima-type-preview__link">لینک داخل متن</span> و ادامه جمله. اعداد: ۱۲۳۴۵ و 67890.</p>
		</div>
	</div>
	<?php
}
