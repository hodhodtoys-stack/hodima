<?php
/**
 * لینک‌های مرتبط دستی — خروجی سایت
 * Path: plugins/hodima-seo/inc/manual_related_link/includes/front.php
 *
 * شورت‌کدها — هر کدام مستقل؛ هر جای محتوا که گذاشته شود، فقط کادر خودش
 * همان‌جا چاپ می‌شود:
 *   [hodima_related_categories]  «دسته‌بندی‌های مرتبط» (پیش‌فرض ۲ لینک)
 *   [manual_related_products]    «محصولات مکمل» (پیش‌فرض ۲ لینک)؛ نام‌های دیگر:
 *                                manual_related_links، hodima_complementary_products
 *   [hodima_related_article]     مقاله پیشنهادی، بدون هیچ عنوانی (۱ لینک)
 *
 * نسخه ۱.۴ شورت‌کد manual_related_products را «ویترین قدیمی» می‌دانست و هر
 * سه کادر را پشت هم چاپ می‌کرد؛ از ۱.۵ فقط کادر محصولات مکمل است.
 *
 * ویژگی‌ها: title="" (عنوان دلخواه یا خالی)، heading="h2|h3|h4|p"،
 *           id="…" و type="auto|post|term" (نمایش لینک‌های صفحه‌ای دیگر).
 *
 * باگ اصلی نسخه ۱: تگ <a> خالی بود و عنوان بیرون از آن، alt تصویر هم خالی و
 * aria-hidden؛ یعنی گوگل برای این لینک‌های داخلی هیچ متن لینکی نمی‌دید.
 * حالا کل کارت یک لینک است و عنوان داخل خود لینک.
 */

declare(strict_types=1);

namespace Hodima\RelatedLinks;

use WP_Term;

defined( 'ABSPATH' ) || exit;

final class Front {

	/** زمینه اجباری هنگام اجرای شورت‌کد داخل توضیح ترم. */
	private static ?array $forced = null;

	private static int $instance = 0;

	public static function init(): void {

		foreach ( Group::cases() as $group ) {
			foreach ( $group->tags() as $tag ) {
				add_shortcode( $tag, static fn( $atts ) => self::shortcode( $group, (array) $atts ) );
			}
		}

		add_action( 'wp_enqueue_scripts', [ self::class, 'assets' ] );
		add_filter( 'the_content', [ self::class, 'auto_content' ], 12 );
		add_filter( 'term_description', [ self::class, 'term_description' ], 11, 4 );
		add_filter( 'hodima_schema_webpage_node', [ self::class, 'schema' ], 30, 2 );
	}

	/** @return list<string> */
	public static function tags(): array {
		return array_merge( ...array_map( static fn( Group $g ) => $g->tags(), Group::cases() ) );
	}

	/** آیا شورت‌کد این گروه (با هر نامش) در متن هست؟ */
	public static function has_group( string $text, Group $group ): bool {
		return str_contains( $text, '[' ) && (bool) preg_match( '/' . get_shortcode_regex( $group->tags() ) . '/', $text );
	}

	/**
	 * متنی که شورت‌کدها در آن گذاشته می‌شوند: محتوا + توضیح کوتاه نوشته/محصول
	 * (ووکامرس شورت‌کد توضیح کوتاه را هم اجرا می‌کند)، یا توضیح ترم.
	 */
	public static function source_text( int $object_id, string $context ): string {
		if ( 'term' === $context ) {
			$term = get_term( $object_id );
			return $term instanceof WP_Term ? (string) $term->description : '';
		}
		return get_post_field( 'post_content', $object_id ) . "\n" . get_post_field( 'post_excerpt', $object_id );
	}

	/**
	 * گروه‌هایی که در این متن نمایش داده می‌شوند: شورت‌کدشان در متن است یا
	 * نمایش خودکارشان روشن است. (برای CSS، اسکیما و هشدار «جایی نمایش داده
	 * نمی‌شود» در ویرایشگر و گزارش)
	 *
	 * @return list<Group>
	 */
	public static function placed_groups( string $text, bool $auto_ok = true ): array {
		return array_values( array_filter(
			Group::cases(),
			static fn( Group $g ): bool => ( $auto_ok && Store::auto( $g ) ) || self::has_group( $text, $g )
		) );
	}

	/* =====================================================================
	 * شورت‌کدها
	 * ===================================================================== */

	/** @param array<string, mixed> $atts */
	public static function shortcode( Group $group, array $atts ): string {

		if ( is_admin() && ! wp_doing_ajax() ) {
			return '';
		}

		$atts = shortcode_atts( [ 'title' => null, 'heading' => '', 'id' => '', 'type' => 'auto' ], $atts, $group->shortcode() );
		$ctx  = self::context( $atts );

		return $ctx ? self::render( $group, $ctx[0], $ctx[1], $atts ) : '';
	}

	/**
	 * شیء صاحب لینک‌ها.
	 *
	 * @param array<string, mixed> $atts
	 * @return array{0:int, 1:string}|null
	 */
	private static function context( array $atts ): ?array {

		$type = strtolower( trim( (string) ( $atts['type'] ?? 'auto' ) ) );
		$id   = absint( $atts['id'] ?? 0 );

		if ( $id ) {
			return [ $id, 'term' === $type ? 'term' : 'post' ];
		}
		if ( null !== self::$forced ) {
			return self::$forced;
		}

		if ( 'term' !== $type && ( in_the_loop() || ( 'post' === $type && get_the_ID() ) ) ) {
			$post_id = (int) get_the_ID();
			return $post_id ? [ $post_id, 'post' ] : null;
		}
		if ( 'post' !== $type && ( is_category() || is_tag() || is_tax() ) ) {
			$term_id = (int) get_queried_object_id();
			return $term_id ? [ $term_id, 'term' ] : null;
		}
		if ( 'term' !== $type && is_singular() ) {
			$post_id = (int) get_queried_object_id();
			return $post_id ? [ $post_id, 'post' ] : null;
		}

		return null;
	}

	/* =====================================================================
	 * رندر
	 * ===================================================================== */

	/** @param array<string, mixed> $opts title، heading، limit */
	public static function render( Group $group, int $object_id, string $context, array $opts = [] ): string {

		/*
		 * عمدا «فقط یک بار چاپ» اینجا نیست: قالب توضیح دسته را دو بار
		 * می‌خواند (if ( get_the_archive_description() ) echo …) و افزونه‌ها
		 * the_content را در <head> هم اجرا می‌کنند؛ فراخوانی دوم خالی می‌شد.
		 * جلوگیری از تکرار در نمایش خودکار است (append_auto).
		 */
		$limit = isset( $opts['limit'] ) ? (int) $opts['limit'] : null;
		$items = Store::visible( $group, $object_id, $context, $limit );
		if ( ! $items ) {
			return '';
		}

		self::enqueue();

		$id      = 'hodima-rl-' . $group->value . '-' . ( ++self::$instance );
		$title   = $group->has_title() ? ( null !== ( $opts['title'] ?? null ) ? (string) $opts['title'] : Store::title( $group ) ) : '';
		$heading = in_array( $opts['heading'] ?? '', Store::HEADINGS, true ) ? (string) $opts['heading'] : (string) Store::settings()['heading'];
		$tag     = Group::Article === $group ? 'aside' : 'section';

		$label = '' !== $title
			? sprintf( 'aria-labelledby="%s-title"', esc_attr( $id ) )
			: sprintf( 'aria-label="%s"', esc_attr( $group->aria_label() ) );

		// data-nosnippet: متن کارت‌ها در توضیح نتایج گوگل نیاید (لینک‌ها خزیده می‌شوند).
		$html = sprintf( '<%1$s class="hodima-rl hodima-rl--%2$s" id="%3$s" %4$s data-nosnippet>', $tag, esc_attr( $group->value ), esc_attr( $id ), $label );

		if ( '' !== $title ) {
			$html .= sprintf( '<%1$s class="hodima-rl__title" id="%2$s-title">%3$s</%1$s>', $heading, esc_attr( $id ), esc_html( $title ) );
		}

		$html .= '<ul class="hodima-rl__list">';
		foreach ( $items as $position => $item ) {
			$html .= '<li class="hodima-rl__item">' . self::card( $group, $item, $position + 1 ) . '</li>';
		}
		$html .= '</ul></' . $tag . '>';

		return $html;
	}

	/** @param array<string, mixed> $item */
	private static function card( Group $group, array $item, int $position ): string {

		$title = (string) $item['title'];
		$media = '';

		if ( $item['img_id'] ) {
			// alt خالی درست است: عنوان همین کارت داخل همین لینک است و alt
			// تکراری فقط باعث می‌شود صفحه‌خوان عنوان را دو بار بخواند.
			// کارت مقاله نیمی از عرض محتوا است: اندازه بزرگ‌تر با srcset
			$media = wp_get_attachment_image( (int) $item['img_id'], Group::Article === $group ? 'medium_large' : 'medium', false, [
				'class'    => 'hodima-rl__img',
				'alt'      => '',
				'loading'  => 'lazy',
				'decoding' => 'async',
				'sizes'    => Group::Article === $group ? '(max-width: 48rem) 50vw, 24rem' : '(max-width: 48rem) 45vw, 12rem',
			] );
		}

		// کارت بدون تصویر: نماد گروه (نسخه ۱ کارت بی‌تصویر را بی‌صدا حذف می‌کرد).
		// حرف اول عنوان امتحان شد ولی «ه» تنها شبیه عدد ۵ دیده می‌شد.
		if ( '' === $media ) {
			$media = '<span class="hodima-rl__placeholder">' . self::icon( $group ) . '</span>';
		}

		return sprintf(
			'<a class="hodima-rl__card" href="%1$s" data-hodima-rl="%2$s" data-hodima-rl-pos="%3$d"><span class="hodima-rl__media">%4$s</span><span class="hodima-rl__name">%5$s</span></a>',
			esc_url( (string) $item['url'] ),
			esc_attr( $group->value ),
			$position,
			$media,
			esc_html( $title )
		);
	}

	/** نماد SVG درون‌خطی (بدون فونت یا فایل خارجی). */
	private static function icon( Group $group ): string {
		$path = match ( $group ) {
			Group::Categories => 'M3 6.5A1.5 1.5 0 0 1 4.5 5h4l2 2h9A1.5 1.5 0 0 1 21 8.5v9a1.5 1.5 0 0 1-1.5 1.5h-15A1.5 1.5 0 0 1 3 17.5z',
			Group::Products   => 'M6 8h12l-1 11.5a1.5 1.5 0 0 1-1.5 1.5h-7A1.5 1.5 0 0 1 7 19.5zM9 8V6.5a3 3 0 0 1 6 0V8',
			Group::Article    => 'M7 3h7l4 4v13a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1zM9 12h6M9 16h6M14 3v4h4',
		};
		return '<svg class="hodima-rl__icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="' . $path . '"/></svg>';
	}

	/* =====================================================================
	 * نمایش خودکار (تنظیمات ← «نمایش خودکار»)
	 * ===================================================================== */

	/** بعد از محتوای نوشته/محصول/برگه (اولویت ۱۲: بعد از do_shortcode). */
	public static function auto_content( mixed $content ): mixed {

		if ( ! is_string( $content ) || is_admin() || is_feed() || ! is_singular() || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}

		$post_id = (int) get_the_ID();
		if ( $post_id !== (int) get_queried_object_id() || ! in_array( get_post_type( $post_id ), Store::post_types(), true ) ) {
			return $content;
		}

		return self::append_auto( $content, $post_id, 'post' );
	}

	private static function append_auto( string $content, int $object_id, string $context ): string {

		// اگر شورت‌کد همین گروه در متن بوده (و چاپ شده)، دوباره اضافه نشود.
		$present = static fn( Group $g ): bool => str_contains( $content, 'hodima-rl--' . $g->value . '"' );

		if ( Store::auto( Group::Article ) && ! $present( Group::Article ) ) {
			$box = self::render( Group::Article, $object_id, $context );
			if ( '' !== $box ) {
				$content = self::insert_after_paragraph( $content, $box, (int) Store::settings()['article_paragraph'] );
			}
		}

		foreach ( [ Group::Categories, Group::Products ] as $group ) {
			if ( Store::auto( $group ) && ! $present( $group ) ) {
				$content .= self::render( $group, $object_id, $context );
			}
		}

		return $content;
	}

	/** بعد از پاراگراف N ام (۰ یا پاراگراف کمتر = انتهای محتوا). */
	private static function insert_after_paragraph( string $content, string $box, int $n ): string {

		if ( $n > 0 && preg_match_all( '#</p>#i', $content, $m, PREG_OFFSET_CAPTURE ) && count( $m[0] ) > $n ) {
			$at = $m[0][ $n - 1 ][1] + 4;
			return substr( $content, 0, $at ) . $box . substr( $content, $at );
		}

		return $content . $box;
	}

	/**
	 * توضیح دسته/برچسب.
	 *
	 * باگ نسخه ۱: قالب توضیح دسته را با term_description() چاپ می‌کند که
	 * شورت‌کد اجرا نمی‌کند؛ شورت‌کد در توضیح دسته به شکل متن خام
	 * «[manual_related_products]» روی صفحه دیده می‌شد. حالا فقط شورت‌کدهای
	 * همین ماژول اجرا می‌شوند (نه همه شورت‌کدها) و در متا/اسکیمای
	 * wp_head و wp_footer حذف می‌شوند تا در توضیح صفحه نیایند.
	 */
	public static function term_description( mixed $value, mixed $term_id = 0, mixed $taxonomy = '', mixed $context = 'display' ): mixed {

		if ( ! is_string( $value ) || 'display' !== $context || is_admin() ) {
			return $value;
		}

		$has     = '' !== $value && self::has_tags( $value );
		$queried = get_queried_object();
		$body    = ! doing_action( 'wp_head' ) && ! doing_action( 'wp_footer' ) && ! is_feed() && ! wp_is_json_request();
		$match   = $queried instanceof WP_Term && (int) $queried->term_id === (int) $term_id
			&& in_array( $queried->taxonomy, Store::taxonomies(), true );

		if ( ! $body || ! $match ) {
			return $has ? self::strip_tags( $value ) : $value;
		}

		if ( $has ) {
			self::$forced = [ (int) $term_id, 'term' ];
			try {
				$value = self::do_own_shortcodes( $value );
			} finally {
				self::$forced = null;
			}
		}

		return self::append_auto( $value, (int) $term_id, 'term' );
	}

	public static function has_tags( string $text ): bool {
		return str_contains( $text, '[' ) && (bool) preg_match( '/' . get_shortcode_regex( self::tags() ) . '/', $text );
	}

	private static function strip_tags( string $text ): string {
		return (string) preg_replace( '/' . get_shortcode_regex( self::tags() ) . '/', '', $text );
	}

	private static function do_own_shortcodes( string $text ): string {
		global $shortcode_tags;
		$saved          = $shortcode_tags;
		$shortcode_tags = array_intersect_key( (array) $saved, array_flip( self::tags() ) );
		try {
			return do_shortcode( $text );
		} finally {
			$shortcode_tags = $saved;
		}
	}

	/* =====================================================================
	 * برنامه صفحه: کدام گروه‌ها در صفحه فعلی نمایش داده می‌شوند؟
	 * (برای CSS در <head> و relatedLink اسکیما که پیش از محتوا ساخته می‌شوند)
	 * ===================================================================== */

	/** @return array{0:int, 1:string, 2:list<Group>}|null شناسه، زمینه، گروه‌ها */
	private static function page_plan(): ?array {

		static $plan = false;
		if ( false !== $plan ) {
			return $plan;
		}

		$plan = null;

		if ( is_singular() ) {
			$id      = (int) get_queried_object_id();
			$context = 'post';
			$text    = self::source_text( $id, 'post' );
			// نمایش خودکار فقط برای پست‌تایپ‌های دارای کادر ویرایشگر
			$auto_ok = in_array( get_post_type( $id ), Store::post_types(), true );
		} elseif ( ( is_category() || is_tag() || is_tax() ) && ! is_paged() ) {
			// قالب توضیح دسته را فقط در صفحه اول نشان می‌دهد
			$term = get_queried_object();
			if ( ! $term instanceof WP_Term || ! in_array( $term->taxonomy, Store::taxonomies(), true ) ) {
				return $plan;
			}
			$id      = $term->term_id;
			$context = 'term';
			$text    = (string) $term->description;
			$auto_ok = true;
		} else {
			return $plan;
		}

		$groups = self::placed_groups( $text, $auto_ok );

		return $plan = $groups ? [ $id, $context, $groups ] : null;
	}

	/**
	 * relatedLink در نود صفحه (#webpage): آدرس لینک‌هایی که واقعا در این صفحه
	 * نمایش داده می‌شوند. قانون گراف واحد: نود صفحه را فقط homepage-schema
	 * می‌سازد و بقیه با همین فیلتر غنی‌اش می‌کنند.
	 *
	 * @param array<string, mixed> $node
	 * @return array<string, mixed>
	 */
	public static function schema( array $node, string $page_url = '' ): array {

		$plan = self::page_plan();
		if ( ! $plan ) {
			return $node;
		}

		[ $id, $context, $groups ] = $plan;

		$urls = [];
		foreach ( $groups as $group ) {
			foreach ( Store::visible( $group, $id, $context ) as $item ) {
				$urls[] = (string) $item['url'];
			}
		}

		$urls = array_values( array_unique( array_merge( (array) ( $node['relatedLink'] ?? [] ), $urls ) ) );
		if ( $urls ) {
			$node['relatedLink'] = $urls;
		}

		return $node;
	}

	/* =====================================================================
	 * دارایی‌ها
	 * ===================================================================== */

	public static function assets(): void {

		wp_register_style( 'hodima-related-links', HODIMA_SEO_URL . '/inc/manual_related_link/assets/front.css', [], VERSION );
		wp_register_script( 'hodima-related-links-track', HODIMA_SEO_URL . '/inc/manual_related_link/assets/front.js', [], VERSION, [ 'in_footer' => true, 'strategy' => 'defer' ] );

		// اگر معلوم است کادری چاپ می‌شود، CSS در <head> بیاید (بدون پرش ظاهر).
		$plan = self::page_plan();
		if ( $plan ) {
			foreach ( $plan[2] as $group ) {
				if ( Store::visible( $group, $plan[0], $plan[1] ) ) {
					self::enqueue();
					break;
				}
			}
		}
	}

	private static function enqueue(): void {
		wp_enqueue_style( 'hodima-related-links' );
		if ( Store::settings()['track_clicks'] ) {
			wp_enqueue_script( 'hodima-related-links-track' );
		}
	}
}
