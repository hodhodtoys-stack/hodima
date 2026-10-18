<?php
/**
 * Module: CSS حیاتی (Critical CSS) — بدون سرویس خارجی
 * Path: hodima/inc/performance/critical-css.php
 *
 * چرا (3.8.0، HODIMA-AUDIT.md بخش ۱۸۴): سرور سایت در تهران است و آزمایش PageSpeed از بیرون ایران؛
 * فایل CSS مسدودکننده (۱۵ تا ۳۷KB، ترکیب لایت‌اسپید) گاهی ۲ ثانیه طول می‌کشید و صفحه تا آن موقع
 * سفید بود. CSS حیاتی لایت‌اسپید به QUIC.cloud نیاز دارد که در ایران وصل نمی‌شود.
 *
 * روش:
 *   ۱. ساخت: در «تنظیمات قالب ← پیج اسپید ← CSS حیاتی» مرورگر خود مدیر نمونه هر نوع صفحه را در
 *      قاب پنهان باز می‌کند (۳۹۰ و ۱۳۰۰px)، قانون‌های CSS عنصرهای بالای صفحه را جدا می‌کند و با
 *      admin-ajax ذخیره می‌کند (assets/js/critical-css-admin.js). هر نوع = یک گزینه جدا (بی‌autoload).
 *   ۲. نمایش (فقط مهمان، فقط با کلید روشن یا ?hodima_ccss=1 برای آزمون): `<style data-no-optimize>`
 *      در head و همه stylesheetها با الگوی preload + onload + noscript (همان الگوی async خود
 *      لایت‌اسپید؛ لایت‌اسپید فقط rel="stylesheet" را ترکیب می‌کند، پس دست نمی‌زند).
 *   ۳. ایمنی: اگر وردپرس/قالب/افزونه‌ای به‌روز شود یا چیدمان صفحه اصلی عوض شود (امضا)، CSS حیاتی
 *      آن نوع کنار گذاشته می‌شود و صفحه مثل قبل (CSS مسدودکننده) چاپ می‌شود تا دوباره ساخته شود.
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ============================================================
 * ۱. نوع صفحه و نمونه‌ها
 * ============================================================ */

/**
 * نوع‌های صفحه که CSS حیاتی جدا دارند (کلید = بخشی از نام گزینه ذخیره؛ عوضش نکن).
 *
 * @return array<string, string>
 */
function hodima_ccss_types(): array {

	$types = [
		'home'     => 'صفحه اصلی',
		'product'  => 'محصول',
		'category' => 'دسته محصول',
		'shop'     => 'فروشگاه و برچسب محصول',
		'blog'     => 'وبلاگ و دسته مقاله',
		'post'     => 'مقاله',
		'page'     => 'برگه',
	];

	if ( ! class_exists( 'WooCommerce' ) ) {
		unset( $types['product'], $types['category'], $types['shop'] );
	}

	return $types;
}

/** نوع صفحه فعلی؛ رشته خالی = این صفحه CSS حیاتی ندارد (سبد، پرداخت، جستجو، ۴۰۴…). */
function hodima_ccss_current_type(): string {

	$woo = function_exists( 'is_woocommerce' );

	if ( $woo && ( is_cart() || is_checkout() || is_account_page() ) ) {
		return '';
	}

	return match ( true ) {
		is_front_page()                                   => 'home',
		$woo && is_product()                              => 'product',
		$woo && is_product_category()                     => 'category',
		$woo && ( is_shop() || is_product_taxonomy() )    => 'shop',
		is_home() || is_category()                        => 'blog',
		is_singular( 'post' )                             => 'post',
		is_page()                                         => 'page',
		default                                           => '',
	};
}

/**
 * آدرس نمونه‌های هر نوع (حداکثر دو؛ دو نمونه تا تفاوت محتوا، مثلا دسته با/بی ویدیو، پوشش داده شود).
 *
 * @return list<string>
 */
function hodima_ccss_sample_urls( string $type ): array {

	$latest = static fn( string $post_type, array $extra = [] ): array => array_map(
		'get_permalink',
		get_posts( [ 'post_type' => $post_type, 'post_status' => 'publish', 'numberposts' => 2, 'fields' => 'ids', 'orderby' => 'date', 'order' => 'DESC', 'suppress_filters' => false ] + $extra )
	);

	$urls = match ( $type ) {
		'home'     => [ home_url( '/' ) ],
		'product'  => $latest( 'product' ),
		'category' => hodima_ccss_term_urls( 'product_cat', (int) get_option( 'default_product_cat' ) ),
		'shop'     => function_exists( 'wc_get_page_permalink' ) ? [ wc_get_page_permalink( 'shop' ) ] : [],
		'blog'     => (int) get_option( 'page_for_posts' ) ? [ (string) get_permalink( (int) get_option( 'page_for_posts' ) ) ] : hodima_ccss_term_urls( 'category', (int) get_option( 'default_category' ) ),
		'post'     => $latest( 'post' ),
		'page'     => $latest( 'page', [ 'post__not_in' => hodima_ccss_special_pages() ] ),
		default    => [],
	};

	return array_values( array_filter( array_map( 'strval', $urls ) ) );
}

/**
 * پرمحصول‌ترین ترم‌ها (بدون «دسته‌بندی نشده»).
 *
 * @return list<string>
 */
function hodima_ccss_term_urls( string $taxonomy, int $exclude ): array {

	$terms = get_terms( [ 'taxonomy' => $taxonomy, 'hide_empty' => true, 'orderby' => 'count', 'order' => 'DESC', 'number' => 2, 'exclude' => $exclude ? [ $exclude ] : [] ] );

	if ( ! is_array( $terms ) ) {
		return [];
	}

	return array_values( array_filter( array_map( static fn( mixed $term ): string => $term instanceof WP_Term ? (string) get_term_link( $term ) : '', $terms ) ) );
}

/**
 * برگه‌هایی که نمونه «برگه» نیستند (صفحه اصلی، وبلاگ، سبد، پرداخت، حساب، فروشگاه).
 *
 * @return list<int>
 */
function hodima_ccss_special_pages(): array {

	$ids = [ (int) get_option( 'page_on_front' ), (int) get_option( 'page_for_posts' ) ];

	if ( function_exists( 'wc_get_page_id' ) ) {
		foreach ( [ 'cart', 'checkout', 'myaccount', 'shop' ] as $page ) {
			$ids[] = (int) wc_get_page_id( $page );
		}
	}

	return array_values( array_filter( $ids, static fn( int $id ): bool => $id > 0 ) );
}

/* ============================================================
 * ۲. ذخیره و امضا
 * ============================================================ */

/** نام گزینه هر نوع (autoload خاموش؛ فقط صفحه همان نوع می‌خواند). */
function hodima_ccss_option( string $type ): string {
	return 'hodima_ccss_' . $type;
}

/**
 * داده ذخیره‌شده یک نوع: css، sig، time، bytes، urls؛ یا null.
 *
 * @return array{css: string, sig: string, time: int, bytes: int, urls: list<string>}|null
 */
function hodima_ccss_get( string $type ): ?array {

	$data = get_option( hodima_ccss_option( $type ) );

	if ( ! is_array( $data ) || ! is_string( $data['css'] ?? null ) || '' === $data['css'] ) {
		return null;
	}

	return [
		'css'   => $data['css'],
		'sig'   => (string) ( $data['sig'] ?? '' ),
		'time'  => (int) ( $data['time'] ?? 0 ),
		'bytes' => (int) ( $data['bytes'] ?? strlen( $data['css'] ) ),
		'urls'  => array_values( array_map( 'strval', (array) ( $data['urls'] ?? [] ) ) ),
	];
}

/**
 * امضای «همان CSSی که ساخته شد»: نسخه وردپرس، قالب، افزونه‌های فعال و ووکامرس، تنظیمات قالب
 * (هدر، چیدمان…؛ جز خود کلید CSS حیاتی)؛ برای صفحه اصلی چیدمان بخش‌ها هم. هر تغییر ← امضا عوض ←
 * آن نوع تا ساخت دوباره کنار گذاشته می‌شود. از نسخه‌ها (نه فهرست stylesheetهای صفحه) چون دو محصول
 * ممکن است CSSهای متفاوتی بگیرند. گزینه‌ها با پیش‌فرض صریح خوانده می‌شوند: register_setting در
 * پیشخوان پیش‌فرض دیگری می‌دهد و امضای admin-ajax با سایت فرق می‌کرد (باگ پیدا‌شده در آزمون).
 */
function hodima_ccss_signature( string $type ): string {

	$parts = [
		get_bloginfo( 'version' ),
		(string) wp_get_theme( get_template() )->get( 'Version' ),
		defined( 'WC_VERSION' ) ? WC_VERSION : '',
		implode( ',', array_map( 'strval', (array) get_option( 'active_plugins', [] ) ) ),
	];

	foreach ( [ 'HODIMA_CORE_PLUGIN_VERSION', 'HODIMA_SEO_VERSION', 'HODIMA_COMMERCE_VERSION', 'HODIMA_MEDIA_VERSION' ] as $constant ) {
		$parts[] = defined( $constant ) ? (string) constant( $constant ) : '';
	}

	$settings = get_option( 'hodima_theme_settings', [] );
	if ( is_array( $settings ) ) {
		unset( $settings['critical_css'] );
	}
	$parts[] = md5( (string) wp_json_encode( $settings ) );

	if ( 'home' === $type ) {
		$parts[] = md5( (string) wp_json_encode( get_option( 'hodima_home_layout', 'none' ) ) );
	}

	return md5( implode( '|', $parts ) );
}

/* ============================================================
 * ۳. خروجی سایت
 * ============================================================ */

/** درخواست ساخت از پیشخوان (قاب پنهان صفحه تنظیمات؛ فقط مدیر). */
function hodima_ccss_is_build_request(): bool {
	return isset( $_GET['hodima_ccss_build'] ) && current_user_can( 'manage_options' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- فقط نمایش صفحه بدون نوار مدیریت برای خواندن CSS
}

/**
 * CSS حیاتی این درخواست، یا رشته خالی (حالت عادی).
 * ?hodima_ccss=1 / 0: آزمون پیش از روشن‌کردن (PageSpeed، پنجره ناشناس) یا خاموش موقت؛ فقط مهمان.
 */
function hodima_ccss_for_request(): string {

	static $css = null;

	// پیش از اجرای کوئری اصلی (صفحه ورود، درخواست‌های زودهنگام) نوع صفحه معلوم نیست؛ حافظه هم نشود
	if ( ! did_action( 'wp' ) ) {
		return '';
	}

	if ( null !== $css ) {
		return $css;
	}

	$css = '';

	if ( is_admin() || is_user_logged_in() || is_customize_preview() || is_preview() || is_feed() || is_embed() || wp_doing_ajax() ) {
		return $css;
	}

	$preview = isset( $_GET['hodima_ccss'] ) ? sanitize_key( wp_unslash( $_GET['hodima_ccss'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- فقط انتخاب حالت نمایش
	$enabled = '1' === $preview || ( '0' !== $preview && (bool) hodima_setting( 'critical_css' ) );

	if ( ! (bool) apply_filters( 'hodima_critical_css_enabled', $enabled ) ) {
		return $css;
	}

	$type = hodima_ccss_current_type();
	$data = '' !== $type ? hodima_ccss_get( $type ) : null;

	if ( null !== $data && hash_equals( $data['sig'], hodima_ccss_signature( $type ) ) ) {
		$css = $data['css'];
	}

	return $css;
}

// پیش از wp_print_styles (اولویت ۸): CSS حیاتی پیش از همه stylesheetها
add_action( 'wp_head', static function (): void {

	$css = hodima_ccss_for_request();

	if ( '' === $css ) {
		return;
	}

	// data-no-optimize: «ترکیب CSS درون‌خطی» لایت‌اسپید آن را به فایل مسدودکننده نبرد
	echo '<style id="hodima-critical-css" data-no-optimize="1">' . $css . '</style>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CSS ذخیره‌شده مدیر؛ هنگام ذخیره «</» خنثی شده است

	/*
	 * همه stylesheetها با هم فعال شوند، نه هر کدام هر وقت رسید. باگ پیدا‌شده در آزمون (CSS کُند):
	 * CSS ووکامرس زودتر از CSS قالب می‌رسید و کارت‌های فروشگاه یک لحظه چیدمان ووکامرس را
	 * می‌گرفتند و برمی‌گشتند (CLS ۰٫۳۶ دو بار). حالا تا همه preloadها نرسیده‌اند همان CSS حیاتی
	 * می‌ماند و بعد یک‌جا (به ترتیب سند) stylesheet می‌شوند. پشتیبان: ۸ ثانیه بعد از DOMContentLoaded
	 * (رویداد load نه: کروم برای preload منتظر نمی‌ماند و زودتر از رسیدن CSS همه را فعال می‌کرد).
	 * پیوندی که media آن با صفحه نمی‌خواند (مثلا smallscreen ووکامرس در دسکتاپ) اصلا دانلود نمی‌شود و
	 * onload ندارد؛ آماده حساب می‌شود (وگرنه همه تا ۸ ثانیه منتظر می‌ماندند — پیدا‌شده در آزمون).
	 * data-no-optimize و data-no-defer: لایت‌اسپید ترکیب/تأخیرش ندهد (وگرنه onload پیوندها بی‌اثر می‌ماند؛
	 * پیوندها اگر این تابع نبود خودشان stylesheet می‌شوند).
	 */
	echo '<script data-no-optimize="1" data-no-defer="1">(function(){var d=document,done=0;function all(){return d.querySelectorAll("link[data-hodima-css]")}function flip(){if(done)return;done=1;all().forEach(function(l){l.rel="stylesheet"})}function check(){if(d.readyState==="loading")return;var a=all();for(var i=0;i<a.length;i++){var m=a[i].media;if(!a[i].hasAttribute("data-ok")&&!(m&&m!=="all"&&!matchMedia(m).matches))return}flip()}window.hodimaCss=function(l){l.setAttribute("data-ok","1");check()};d.addEventListener("DOMContentLoaded",function(){check();setTimeout(flip,8000)})})();</script>' . "\n";
}, 7 );

/**
 * یک تگ <link rel="stylesheet"> ← preload + onload و نسخه noscript (بدون شناسه، تا تکراری نشود).
 * تگ چاپ (media="print") یا با data-no-async همان می‌ماند.
 */
function hodima_ccss_async_tag( string $tag ): string {

	if ( str_contains( $tag, 'data-no-async' ) || preg_match( '/\bmedia=([\'"])print\1/', $tag ) ) {
		return $tag;
	}

	$async = (string) preg_replace( '/\brel=([\'"])stylesheet\1/', 'rel=$1preload$1 as=$1style$1 data-hodima-css onload="this.onload=null;window.hodimaCss?hodimaCss(this):this.rel=\'stylesheet\'" onerror="this.onerror=null;window.hodimaCss?hodimaCss(this):this.rel=\'stylesheet\'"', $tag, 1, $count );

	if ( 1 !== $count ) {
		return $tag;
	}

	$noscript = (string) preg_replace( '/\sid=([\'"])[^\'"]*\1/', '', trim( $tag ), 1 );

	return $async . '<noscript>' . $noscript . '</noscript>';
}

/** لایت‌اسپید فعال است؟ آن‌وقت CSS را بعد از ترکیب خودش async می‌کنیم (پایین)، نه پیش از آن. */
function hodima_ccss_litespeed(): bool {
	return function_exists( 'hodima_litespeed_active' ) && hodima_litespeed_active();
}

/*
 * بدون لایت‌اسپید: هر stylesheet وردپرس همان لحظه چاپ async می‌شود.
 */
add_filter( 'style_loader_tag', static function ( mixed $tag ): mixed {

	if ( ! is_string( $tag ) || hodima_ccss_litespeed() || '' === hodima_ccss_for_request() ) {
		return $tag;
	}

	return hodima_ccss_async_tag( $tag ) . "\n";
}, 20 );

/*
 * با لایت‌اسپید: stylesheetها دست‌نخورده می‌مانند تا لایت‌اسپید مثل همیشه همه را در *یک* فایل
 * کوچک‌شده ترکیب کند؛ بعد از کار آن (finalize اولویت ۲۰) همان فایل نهایی async می‌شود.
 * باگ نسخه اول (۳.۸.۰، دیده‌شده در PageSpeed سایت زنده): preload در سطح وردپرس ترکیب لایت‌اسپید را
 * دور می‌زد و به‌جای یک فایل، ۱۸ فایل CSS هم‌زمان با بالاترین اولویت دانلود می‌شد؛ روی مسیر کُند
 * تهران–گوگل فونت Bold و عکس اصلی محصول تا ۷٫۵ ثانیه عقب افتاد و جابه‌جایی فونت CLS ۰٫۹ ساخت.
 */
add_filter( 'litespeed_buffer_finalize', static function ( mixed $buffer ): mixed {

	if ( ! is_string( $buffer ) || ! str_contains( $buffer, 'id="hodima-critical-css"' ) ) {
		return $buffer;
	}

	// محتوای <noscript> و <script> دست نخورد
	$parts = preg_split( '#(<noscript\b.*?</noscript>|<script\b.*?</script>)#is', $buffer, -1, PREG_SPLIT_DELIM_CAPTURE );
	if ( ! is_array( $parts ) ) {
		return $buffer;
	}

	foreach ( $parts as $i => $part ) {
		if ( 1 === $i % 2 ) {
			continue;
		}
		$parts[ $i ] = (string) preg_replace_callback( '#<link\b[^>]*\brel=([\'"])stylesheet\1[^>]*>#i', static fn( array $m ): string => hodima_ccss_async_tag( $m[0] ), $part );
	}

	return implode( '', $parts );
}, 25 );

/*
 * با CSS حیاتی، لایت‌اسپید <style>های درون‌خطی (متغیرهای پالت و تایپوگرافی…) را به فایل ترکیبی
 * نبرد: آن فایل حالا async است و این مقدارها باید از اول اعمال شوند. (نسخه اول: همین‌ها یک فایل
 * ۲KB مسدودکننده جدا ساخته بودند.)
 */
add_filter( 'litespeed_optm_css_comb_ext_inl', static fn( mixed $combine ): mixed => '' !== hodima_ccss_for_request() ? false : $combine );

// قاب ساخت: بدون نوار مدیریت (عنصرها و CSS آن در CSS حیاتی مهمان لازم نیست)
add_filter( 'show_admin_bar', static fn( mixed $show ): mixed => hodima_ccss_is_build_request() ? false : $show, 99 );

/* ============================================================
 * ۴. پیشخوان: قاب «CSS حیاتی» و ذخیره با admin-ajax
 * ============================================================ */

add_action( 'admin_enqueue_scripts', static function ( string $hook ): void {

	if ( ! defined( 'HODIMA_SETTINGS_HOOK' ) || HODIMA_SETTINGS_HOOK !== $hook ) {
		return;
	}

	hodima_enqueue_asset( 'hodima-critical-css-admin', 'assets/js/critical-css-admin.js', [], [ 'in_footer' => true, 'strategy' => 'defer' ] );
} );

/** محتوای قاب «CSS حیاتی» در تب «پیج اسپید» (قاب با layout «ccss»؛ کلید روشن/خاموش زیر آن). */
function hodima_ccss_admin_panel(): void {

	$types = hodima_ccss_types();
	$rows  = [];

	foreach ( $types as $type => $label ) {
		$rows[ $type ] = [
			'label' => $label,
			'urls'  => hodima_ccss_sample_urls( $type ),
			'data'  => hodima_ccss_get( $type ),
			'fresh' => false,
		];
		if ( null !== $rows[ $type ]['data'] ) {
			$rows[ $type ]['fresh'] = hash_equals( $rows[ $type ]['data']['sig'], hodima_ccss_signature( $type ) );
		}
	}

	$config = [
		'ajax'  => admin_url( 'admin-ajax.php' ),
		'nonce' => wp_create_nonce( 'hodima_ccss' ),
		'types' => array_map( static fn( array $row ): array => [ 'label' => $row['label'], 'urls' => $row['urls'] ], $rows ),
	];
	?>
	<div class="hodima-ccss" data-hodima-ccss="<?php echo esc_attr( (string) wp_json_encode( $config ) ); ?>">
		<ol class="hodima-ccss__steps">
			<li><strong>ساخت:</strong> دکمه «ساخت CSS حیاتی» را بزنید و تا پایان این صفحه را باز نگه دارید (برای هر نوع صفحه چند ثانیه). مرورگر شما نمونه هر نوع صفحه را پشت صحنه در اندازه موبایل و دسکتاپ باز می‌کند.</li>
			<li><strong>آزمون (اختیاری):</strong> لینک «آزمون» هر ردیف را در <a href="https://pagespeed.web.dev/" target="_blank" rel="noopener noreferrer">PageSpeed</a> یا پنجره ناشناس مرورگر باز کنید؛ فقط همان آدرس با CSS حیاتی نمایش داده می‌شود.</li>
			<li><strong>روشن کردن:</strong> کلید پایین را روشن و «ذخیره تنظیمات» را بزنید. اگر ظاهر جایی به‌هم ریخت، خاموش کنید؛ سایت فورا مثل قبل می‌شود.</li>
		</ol>

		<div class="hodima-ccss__table-wrap">
			<table class="hodima-ccss__table">
				<thead>
					<tr><th scope="col">نوع صفحه</th><th scope="col">وضعیت</th><th scope="col">نمونه</th></tr>
				</thead>
				<tbody>
					<?php foreach ( $rows as $type => $row ) : ?>
						<?php
						$state = match ( true ) {
							null === $row['data'] => 'none',
							! $row['fresh']       => 'stale',
							default               => 'ok',
						};
						?>
						<tr data-type="<?php echo esc_attr( $type ); ?>">
							<th scope="row"><?php echo esc_html( $row['label'] ); ?></th>
							<td>
								<span class="hodima-ccss__state" data-state="<?php echo esc_attr( $state ); ?>">
									<?php
									echo esc_html( match ( $state ) {
										'none'  => 'ساخته نشده',
										'stale' => 'کهنه — دوباره بسازید',
										default => 'آماده',
									} );
									?>
								</span>
								<?php if ( null !== $row['data'] ) : ?>
									<span class="hodima-ccss__meta"><?php echo esc_html( sprintf( '%s کیلوبایت · %s', number_format_i18n( $row['data']['bytes'] / 1024, 1 ), wp_date( 'j F Y، H:i', $row['data']['time'] ) ) ); ?></span>
								<?php endif; ?>
							</td>
							<td class="hodima-ccss__links">
								<?php if ( ! $row['urls'] ) : ?>
									<span class="hodima-ccss__meta">نمونه‌ای پیدا نشد</span>
								<?php endif; ?>
								<?php foreach ( $row['urls'] as $i => $url ) : ?>
									<a href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener"><?php echo esc_html( sprintf( 'نمونه %s', number_format_i18n( $i + 1 ) ) ); ?></a>
									<a href="<?php echo esc_url( add_query_arg( 'hodima_ccss', '1', $url ) ); ?>" target="_blank" rel="noopener" class="hodima-ccss__test">آزمون</a>
								<?php endforeach; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>

		<div class="hodima-ccss__actions">
			<button type="button" class="button button-primary" data-hodima-ccss-build>
				<span class="dashicons dashicons-update" aria-hidden="true"></span> ساخت CSS حیاتی
			</button>
			<button type="button" class="button" data-hodima-ccss-clear>
				<span class="dashicons dashicons-trash" aria-hidden="true"></span> حذف همه
			</button>
		</div>
		<div class="hodima-ccss__progress" hidden>
			<progress max="100" value="0" aria-label="پیشرفت ساخت CSS حیاتی"></progress>
			<p class="hodima-ccss__log" role="status" aria-live="polite"></p>
		</div>
		<div class="hodima-ccss__stage" aria-hidden="true"></div>
	</div>
	<?php
}

/** ذخیره CSS حیاتی یک نوع (از critical-css-admin.js). */
add_action( 'wp_ajax_hodima_ccss_save', static function (): void {

	check_ajax_referer( 'hodima_ccss', 'nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( [ 'message' => 'دسترسی ندارید.' ], 403 );
	}

	$type  = isset( $_POST['type'] ) ? sanitize_key( wp_unslash( $_POST['type'] ) ) : '';
	$css   = isset( $_POST['css'] ) ? (string) wp_unslash( $_POST['css'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- CSS خام مدیر؛ پایین «</» خنثی می‌شود تا از <style> بیرون نزند
	$types = hodima_ccss_types();

	if ( ! isset( $types[ $type ] ) || '' === trim( $css ) ) {
		wp_send_json_error( [ 'message' => 'داده نامعتبر.' ], 400 );
	}

	// بیرون‌زدن از <style> ممکن نباشد؛ سقف ۳۰۰KB (CSS حیاتی معمول ۸ تا ۲۵KB)
	$css = str_ireplace( '</', '<\/', $css );
	if ( strlen( $css ) > 300 * 1024 ) {
		wp_send_json_error( [ 'message' => 'CSS حیاتی بیش از حد بزرگ است.' ], 400 );
	}

	$urls = isset( $_POST['urls'] ) ? array_values( array_filter( array_map( 'esc_url_raw', (array) wp_unslash( $_POST['urls'] ) ) ) ) : []; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- esc_url_raw روی هر مورد

	$data = [
		'css'   => $css,
		'sig'   => hodima_ccss_signature( $type ),
		'time'  => time(),
		'bytes' => strlen( $css ),
		'urls'  => $urls,
	];

	update_option( hodima_ccss_option( $type ), $data, false );

	wp_send_json_success( [
		'type'  => $type,
		'state' => 'ok',
		'meta'  => sprintf( '%s کیلوبایت · %s', number_format_i18n( $data['bytes'] / 1024, 1 ), wp_date( 'j F Y، H:i', $data['time'] ) ),
	] );
} );

/** حذف همه، یا پایان ساخت: کش صفحه‌ها پاک شود تا HTML تازه ساخته شود. */
add_action( 'wp_ajax_hodima_ccss_finish', static function (): void {

	check_ajax_referer( 'hodima_ccss', 'nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( [ 'message' => 'دسترسی ندارید.' ], 403 );
	}

	if ( ! empty( $_POST['clear'] ) ) {
		foreach ( array_keys( hodima_ccss_types() ) as $type ) {
			delete_option( hodima_ccss_option( $type ) );
		}
	}

	do_action( 'litespeed_purge_all' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- API پاک‌سازی کش افزونه LiteSpeed Cache

	wp_send_json_success();
} );
