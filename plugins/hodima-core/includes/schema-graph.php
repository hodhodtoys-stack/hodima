<?php
/**
 * Hodima Core — گراف واحد اسکیما (JSON-LD)
 * Path: plugins/hodima-core/includes/schema-graph.php
 *
 * ─────────────────────────────────────────────────────────────────────
 * چرا
 * ─────────────────────────────────────────────────────────────────────
 * پیش از این، اسکیما از ۱۷ نقطه مختلف (قالب و سه افزونه) هر کدام با یک
 * تگ <script> جداگانه چاپ می‌شد و یک پاک‌کن regex روی خروجی <head>
 * تکراری‌ها را حذف می‌کرد. آن پاک‌کن دو ایراد ذاتی داشت:
 *
 *   ۱. حذف اشتباه: دو نود با @type و @id یکسان را «تکراری» می‌دانست و
 *      دومی را *کامل* دور می‌انداخت — حتی وقتی محتوای متفاوتی داشت.
 *      مثال واقعی: FAQPage «#faq» هم از سیستم رسانه می‌آمد و هم از AEO؛
 *      سوال‌های AEO هرگز به گوگل نمی‌رسید.
 *   ۲. فقط <head> را می‌دید؛ اسکیمای فوتر و بدنه (جدول، فهرست ویدیوها،
 *      فهرست مقالات، فروشگاه) اصلا بررسی نمی‌شد.
 *
 * ─────────────────────────────────────────────────────────────────────
 * روش جدید (مثل Yoast)
 * ─────────────────────────────────────────────────────────────────────
 * هر سازنده به جای echo، نودهایش را به hodima_schema_add() می‌دهد. در
 * انتهای صفحه (wp_footer) *یک* تگ با *یک* @graph چاپ می‌شود.
 *
 * نودهای هم‌شناسه (@id یکسان) حذف نمی‌شوند، *ادغام* می‌شوند — دقیقا همان
 * کاری که گوگل با نودهای هم‌شناسه انجام می‌دهد، ولی بدون مقادیر تکراری:
 *
 *   ویژگی جدید                  → اضافه می‌شود
 *   مقدار یکسان                 → یک بار
 *   دو فهرست                    → اجتماع (بدون عضو تکراری؛ سوال‌های FAQ
 *                                  با متن یکسان یک بار)
 *   ارجاع/شیء و فهرست           → اجتماع
 *   دو شیء بدون @id یا هم‌شناسه → ادغام بازگشتی
 *   دو ارجاع به شناسه‌های متفاوت → هر دو (فهرست)
 *   دو مقدار ساده متفاوت        → اولی می‌ماند (سازنده اصلی زودتر اجرا
 *                                  می‌شود) و تعارض در گزارش ثبت می‌شود
 *
 * نود بدون @id فقط وقتی حذف می‌شود که *دقیقا* همان نود قبلا آمده باشد.
 *
 * ─────────────────────────────────────────────────────────────────────
 * API
 * ─────────────────────────────────────────────────────────────────────
 *   hodima_schema_add( array $payload, string $source = '' )
 *       $payload یکی از سه شکل رایج JSON-LD: {@graph:[…]}، فهرست نودها،
 *       یا یک نود تکی. @context حذف می‌شود (یک بار در خروجی می‌آید).
 *
 *   فیلتر hodima_schema_graph ( array $nodes )
 *       آخرین فرصت افزونه‌ها برای تغییر گراف پیش از چاپ.
 *
 *   فیلتر hodima_schema_graph_debug ( bool )
 *       گزارش منبع هر نود و تعارض‌ها به صورت کامنت HTML (فقط برای مدیر).
 */

declare(strict_types=1);

namespace Hodima\Core;

defined( 'ABSPATH' ) || exit;

final class Schema_Graph {

	/** اولویت چاپ در wp_footer — بعد از همه سازنده‌های فوتر (بیشینه قبلی ۹۹). */
	public const int PRINT_PRIORITY = 9999;

	private const string CONTEXT = 'https://schema.org';

	/** @var array<string, array> نودها به ترتیب ورود؛ کلید = @id یا «#anon:هش» */
	private static array $nodes = [];

	/** @var array<string, list<string>> منبع(های) هر نود — برای گزارش */
	private static array $sources = [];

	/** @var list<string> تعارض‌های ادغام — برای گزارش */
	private static array $conflicts = [];

	/** @var list<array> payloadهایی که واژگان schema.org ندارند؛ دست‌نخورده چاپ می‌شوند */
	private static array $foreign = [];

	public static bool $printed = false;

	public static function boot(): void {
		add_action( 'wp_footer', [ self::class, 'print' ], self::PRINT_PRIORITY );
	}

	/**
	 * افزودن یک payload به گراف.
	 */
	public static function add( array $payload, string $source = '' ): void {

		if ( empty( $payload ) ) {
			return;
		}

		// payload دیرهنگام (بعد از چاپ گراف): جداگانه چاپ شود تا هیچ اسکیمایی گم نشود
		if ( self::$printed ) {
			static $late = 0;
			self::echo_script( $payload, 'hodima-schema-late-' . ++$late );
			return;
		}

		if ( isset( $payload['@context'] ) && ! self::is_schema_org( $payload['@context'] ) ) {
			self::$foreign[] = $payload;
			return;
		}

		foreach ( self::split( $payload ) as $node ) {
			self::add_node( $node, $source );
		}
	}

	/** آیا گراف نودی با این شناسه دارد؟ */
	public static function has( string $id ): bool {
		return isset( self::$nodes[ $id ] );
	}

	/** @return list<array> نودهای فعلی گراف (برای تست و ابزارها) */
	public static function nodes(): array {
		return array_values( self::$nodes );
	}

	public static function print(): void {

		if ( self::$printed ) {
			return;
		}

		self::$printed = true;

		$nodes = (array) apply_filters( 'hodima_schema_graph', array_values( self::$nodes ) );
		$nodes = array_values( array_filter( $nodes, static fn( $node ): bool => is_array( $node ) && [] !== $node ) );

		if ( self::debug_enabled() ) {
			echo self::debug_comment( $nodes ); // phpcs:ignore WordPress.Security.EscapeOutput -- متن داخلش پاک‌سازی شده است
		}

		if ( [] !== $nodes ) {
			echo "\n<!-- Hodima Schema Graph -->\n";
			self::echo_script( [ '@context' => self::CONTEXT, '@graph' => $nodes ], 'hodima-schema-graph' );
		}

		foreach ( self::$foreign as $i => $payload ) {
			self::echo_script( $payload, 'hodima-schema-extra-' . ( $i + 1 ) );
		}
	}

	/* =================================================================
	 * ورود نودها
	 * ================================================================= */

	/** سه شکل payload → فهرست نودها (بدون @context). */
	private static function split( array $payload ): array {

		if ( isset( $payload['@graph'] ) && is_array( $payload['@graph'] ) ) {
			$nodes = $payload['@graph'];
		} elseif ( array_is_list( $payload ) ) {
			$nodes = $payload;
		} else {
			$nodes = [ $payload ];
		}

		$out = [];

		foreach ( $nodes as $node ) {

			if ( ! is_array( $node ) || [] === $node ) {
				continue;
			}

			// @context درون هر نود (مثلا فهرست ImageObjectها) — یک بار در خروجی می‌آید
			if ( isset( $node['@context'] ) && self::is_schema_org( $node['@context'] ) ) {
				unset( $node['@context'] );
			}

			$out[] = $node;
		}

		return $out;
	}

	private static function add_node( array $node, string $source ): void {

		$id = isset( $node['@id'] ) && is_string( $node['@id'] ) && '' !== $node['@id'] ? $node['@id'] : null;

		if ( null === $id ) {
			// نود بدون شناسه: فقط تکرار *دقیق* حذف می‌شود
			$key = '#anon:' . md5( (string) wp_json_encode( $node ) );
			if ( ! isset( self::$nodes[ $key ] ) ) {
				self::$nodes[ $key ] = $node;
			}
			self::$sources[ $key ][] = $source;
			return;
		}

		if ( ! isset( self::$nodes[ $id ] ) ) {
			self::$nodes[ $id ]   = $node;
			self::$sources[ $id ] = [ $source ];
			return;
		}

		self::$nodes[ $id ]     = self::merge( self::$nodes[ $id ], $node, $id );
		self::$sources[ $id ][] = $source;
	}

	/* =================================================================
	 * ادغام
	 * ================================================================= */

	/**
	 * ادغام دو شیء JSON-LD (هم‌شناسه، یا هر دو بدون شناسه).
	 */
	private static function merge( array $base, array $incoming, string $path ): array {

		foreach ( $incoming as $key => $value ) {

			if ( '@id' === $key || '@context' === $key ) {
				continue;
			}

			if ( ! array_key_exists( $key, $base ) ) {
				$base[ $key ] = $value;
				continue;
			}

			$current = $base[ $key ];

			if ( self::same( $current, $value ) ) {
				continue;
			}

			if ( '@type' === $key ) {
				self::$conflicts[] = sprintf( '%s @type: «%s» ماند، «%s» نادیده گرفته شد', $path, self::brief( $current ), self::brief( $value ) );
				continue;
			}

			$base[ $key ] = self::merge_values( $current, $value, $path . ' › ' . $key );
		}

		return $base;
	}

	private static function merge_values( mixed $current, mixed $value, string $path ): mixed {

		$current_list = is_array( $current ) && array_is_list( $current );
		$value_list   = is_array( $value ) && array_is_list( $value );

		// دو شیء (نه فهرست)
		if ( is_array( $current ) && is_array( $value ) && ! $current_list && ! $value_list ) {

			$current_id = $current['@id'] ?? null;
			$value_id   = $value['@id'] ?? null;

			if ( $current_id === $value_id ) {
				return self::merge( $current, $value, $path );
			}

			// دو موجودیت متفاوت برای یک ویژگی: هر دو حفظ می‌شوند
			self::$conflicts[] = sprintf( '%s: دو موجودیت متفاوت (%s و %s) — هر دو نگه داشته شد', $path, self::brief( $current ), self::brief( $value ) );
			return [ $current, $value ];
		}

		// حداقل یکی فهرست است، یا فهرست و شیء
		if ( $current_list || $value_list ) {
			return self::union(
				$current_list ? $current : [ $current ],
				$value_list ? $value : [ $value ]
			);
		}

		// دو مقدار ساده متفاوت: مقدار سازنده اول
		self::$conflicts[] = sprintf( '%s: «%s» ماند، «%s» نادیده گرفته شد', $path, self::brief( $current ), self::brief( $value ) );
		return $current;
	}

	/**
	 * اجتماع دو فهرست.
	 *   - عضو هم‌شناسه ادغام می‌شود
	 *   - عضو دقیقا تکراری حذف می‌شود
	 *   - Question با متن سوال یکسان یک بار (FAQ دو منبع)
	 */
	private static function union( array $list, array $extra ): array {

		foreach ( $extra as $item ) {

			foreach ( $list as $i => $existing ) {

				if ( self::same( $existing, $item ) ) {
					continue 2;
				}

				if ( is_array( $existing ) && is_array( $item ) ) {

					if ( isset( $existing['@id'], $item['@id'] ) && $existing['@id'] === $item['@id'] ) {
						$list[ $i ] = self::merge( $existing, $item, (string) $item['@id'] );
						continue 2;
					}

					if ( self::is_question( $existing ) && self::is_question( $item )
						&& self::question_key( $existing ) === self::question_key( $item ) ) {
						continue 2;
					}
				}
			}

			$list[] = $item;
		}

		return $list;
	}

	/* =================================================================
	 * ابزارها
	 * ================================================================= */

	private static function same( mixed $a, mixed $b ): bool {
		return $a === $b || wp_json_encode( $a ) === wp_json_encode( $b );
	}

	private static function is_question( array $node ): bool {
		return 'Question' === ( $node['@type'] ?? null ) && isset( $node['name'] ) && is_string( $node['name'] );
	}

	private static function question_key( array $node ): string {
		$text = trim( wp_strip_all_tags( (string) $node['name'] ) );
		return function_exists( 'mb_strtolower' ) ? mb_strtolower( $text ) : strtolower( $text );
	}

	private static function is_schema_org( mixed $context ): bool {
		return is_string( $context ) && 1 === preg_match( '#^https?://schema\.org/?$#i', $context );
	}

	private static function brief( mixed $value ): string {
		$json = is_string( $value ) ? $value : (string) wp_json_encode( $value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		return mb_strimwidth( $json, 0, 90, '…' );
	}

	private static function echo_script( array $payload, string $id ): void {

		$json = wp_json_encode( $payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP );

		if ( false === $json ) {
			return;
		}

		printf( '<script type="application/ld+json" id="%s">%s</script>' . "\n", esc_attr( $id ), $json ); // phpcs:ignore WordPress.Security.EscapeOutput -- JSON_HEX_TAG
	}

	private static function debug_enabled(): bool {

		$enabled = 'yes' === get_option( 'hodima_schema_graph_debug', 'no' )
			&& is_user_logged_in()
			&& current_user_can( 'manage_options' );

		return (bool) apply_filters( 'hodima_schema_graph_debug', $enabled );
	}

	/** گزارش برای مدیر: منبع هر نود، تعارض‌ها و ارجاع‌های بدون مقصد. */
	private static function debug_comment( array $nodes ): string {

		// شناسه‌های تعریف‌شده، شامل نودهای تودرتو (مثل لوگو داخل Organization)
		$ids = [];
		self::collect_ids( $nodes, $ids );

		$lines = [ 'Hodima Schema Graph — گزارش مدیر (فقط برای شما نمایش داده می‌شود)' ];

		foreach ( self::$nodes as $key => $node ) {
			$type    = self::brief( $node['@type'] ?? '?' );
			$sources = implode( ' + ', array_filter( array_unique( self::$sources[ $key ] ?? [] ) ) );
			$lines[] = sprintf( '  %s  %s  ← %s', $type, str_starts_with( $key, '#anon:' ) ? '(بدون @id)' : $key, '' !== $sources ? $sources : '?' );
		}

		$site     = trailingslashit( home_url() );
		$dangling = [];

		self::collect_refs( $nodes, $refs );

		foreach ( array_unique( $refs ?? [] ) as $ref ) {
			// ارجاع به صفحه‌ای دیگر از همین سایت (مثلا #product یک محصول مرتبط) مجاز است
			if ( ! isset( $ids[ $ref ] ) && str_starts_with( $ref, $site . '#' ) ) {
				$dangling[] = $ref;
			}
		}

		if ( [] !== self::$conflicts ) {
			$lines[] = 'تعارض‌ها:';
			foreach ( self::$conflicts as $conflict ) {
				$lines[] = '  - ' . $conflict;
			}
		}

		if ( [] !== $dangling ) {
			$lines[] = 'ارجاع بدون نود در همین صفحه:';
			foreach ( $dangling as $ref ) {
				$lines[] = '  - ' . $ref;
			}
		}

		// «--» داخل کامنت HTML مجاز نیست
		return "\n<!--\n" . str_replace( '--', '- -', esc_html( implode( "\n", $lines ) ) ) . "\n-->\n";
	}

	/** شناسه همه نودهایی که تعریف شده‌اند (نه فقط ارجاع). */
	private static function collect_ids( array $data, array &$ids ): void {
		foreach ( $data as $value ) {
			if ( ! is_array( $value ) ) {
				continue;
			}
			if ( isset( $value['@id'] ) && is_string( $value['@id'] ) && count( $value ) > 1 ) {
				$ids[ $value['@id'] ] = true;
			}
			self::collect_ids( $value, $ids );
		}
	}

	/** همه ارجاع‌های {"@id": …} بدون ویژگی دیگر. */
	private static function collect_refs( array $data, ?array &$refs ): void {
		$refs ??= [];
		foreach ( $data as $value ) {
			if ( ! is_array( $value ) ) {
				continue;
			}
			if ( 1 === count( $value ) && isset( $value['@id'] ) && is_string( $value['@id'] ) ) {
				$refs[] = $value['@id'];
				continue;
			}
			self::collect_refs( $value, $refs );
		}
	}
}

Schema_Graph::boot();

