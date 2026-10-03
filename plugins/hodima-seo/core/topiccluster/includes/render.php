<?php
/**
 * خوشه موضوعی — کادر سایت (شورت‌کد، نمایش خودکار، توضیح دسته)
 * Path: core/topiccluster/includes/render.php
 *
 * شورت‌کد: [hodima_topic_cluster]
 *   id="12" type="post|term"   نمایش خوشه صفحه یا دسته‌ای دیگر
 *   show="parents,children,siblings"   کدام بخش‌ها (پیش‌فرض از تنظیمات)
 *   limit="8"                  حداکثر لینک هر بخش
 *   heading="h2|h3|h4|p"       تگ عنوان بخش‌ها
 *   layout="grid|list"         چیدمان فرزندان
 *
 * سه بخش کادر:
 *   مرجع (والدها)      ← لینک به پیلار
 *   زیرمجموعه‌ها      ← روی پیلار: لینک به همه فرزندان
 *   هم‌خوشه‌ها (جدید) ← روی فرزند: بقیه مطالب همان خوشه. نسخه قبلی فرزند
 *                       فقط به پیلار لینک می‌داد و مقاله‌های یک خوشه هیچ
 *                       لینکی به هم نداشتند.
 *
 * نشانه‌گذاری: <nav> با فهرست واقعی (ul/li) و عنوان قابل انتخاب. کلاس‌های
 * قدیمی (hodima-tc-parent، hodima-tc-child، hodima-tc-unified-box …) حفظ
 * شده‌اند تا CSS سفارشی و راهنمای «بررسی در سورس صفحه» نشکنند.
 */

declare(strict_types=1);

namespace Hodima\TopicCluster;

use WP_Term;

defined( 'ABSPATH' ) || exit;

final class Render {

	public const TAG    = 'hodima_topic_cluster';
	public const ANCHOR = 'topic-cluster-section';
	public const HANDLE = 'hodima-tc-front';

	/** نشانه‌ای که وجود کادر چاپ‌شده را در متن نشان می‌دهد. */
	private const MARKER = 'class="hodima-tc ';

	private static int $instance = 0;

	/**
	 * گره‌ای که لنگر #topic-cluster-section را گرفته (یک بار در صفحه).
	 * کلید Ref نگه داشته می‌شود، نه یک پرچم: قالب توضیح دسته را دو بار
	 * می‌خواند (if ( get_the_archive_description() ) echo …) و خروجی بار اول
	 * دور ریخته می‌شود؛ با پرچم ساده لنگر در همان خروجی دورریخته می‌ماند.
	 */
	private static ?string $anchor_owner = null;

	/** گره اجباری هنگام اجرای شورت‌کد داخل توضیح یک ترم. */
	private static ?Ref $forced = null;

	public static function init(): void {
		add_shortcode( self::TAG, [ self::class, 'shortcode' ] );
		add_filter( 'the_content', [ self::class, 'auto_content' ], 12 );
		add_filter( 'term_description', [ self::class, 'term_description' ], 10, 4 );
		add_action( 'wp_enqueue_scripts', [ self::class, 'assets' ] );
		add_action( 'template_redirect', [ self::class, 'short_description' ] );
	}

	/* =================================================================
	 * گره صفحه جاری
	 * ================================================================= */

	public static function current(): ?Ref {

		if ( is_singular( Graph::post_types() ) ) {
			$id = (int) get_queried_object_id();
			return $id ? Ref::post( $id ) : null;
		}

		$queried = get_queried_object();
		if ( $queried instanceof WP_Term && in_array( $queried->taxonomy, Graph::taxonomies(), true ) ) {
			return Ref::term( (int) $queried->term_id );
		}

		return null;
	}

	/** جای نمایش خودکار این گره در تنظیمات. */
	public static function place( Ref $ref ): string {
		return $ref->is_term() ? 'term' : Graph::post_type_of( $ref );
	}

	/** متنی که شورت‌کد در آن گذاشته می‌شود (محتوا + توضیح کوتاه، یا توضیح ترم). */
	public static function source_text( Ref $ref ): string {
		if ( $ref->is_term() ) {
			$term = get_term( $ref->id );
			return $term instanceof WP_Term ? (string) $term->description : '';
		}
		return get_post_field( 'post_content', $ref->id ) . "\n" . get_post_field( 'post_excerpt', $ref->id );
	}

	/** آیا صفحه این گره کادر خوشه را نشان می‌دهد؟ (شورت‌کد در متن یا نمایش خودکار) */
	public static function has_box( Ref $ref ): bool {
		return Settings::auto_insert( self::place( $ref ) ) || has_shortcode( self::source_text( $ref ), self::TAG );
	}

	/* =================================================================
	 * شورت‌کد
	 * ================================================================= */

	/** @param array<string, string>|string $atts */
	public static function shortcode( $atts = [] ): string {

		if ( is_admin() && ! wp_doing_ajax() ) {
			return '';
		}

		$atts = shortcode_atts(
			[ 'id' => 0, 'type' => '', 'show' => '', 'limit' => '', 'heading' => '', 'layout' => '' ],
			is_array( $atts ) ? $atts : [],
			self::TAG
		);

		$id  = absint( $atts['id'] );
		$ref = $id
			? new Ref( Kind::from_context( sanitize_key( (string) $atts['type'] ) ), $id )
			: ( self::$forced ?? self::current() );

		if ( null === $ref ) {
			return self::debug( 'Object ID not detected' );
		}

		return self::box( $ref, $atts );
	}

	/**
	 * داده کادر یک گره (برای نمایش، AEO و پیش‌نمایش پیشخوان).
	 *
	 * @param array<string, mixed> $opts show، limit
	 * @return array{parents: list<array<string, mixed>>, children: list<array<string, mixed>>, siblings: list<array<string, mixed>>}
	 */
	public static function sections( Ref $ref, array $opts = [] ): array {

		$s     = Settings::all();
		$limit = (int) ( $opts['limit'] ?? 0 );
		$show  = self::show_list( (string) ( $opts['show'] ?? '' ) );
		$keep  = static fn( array $node ): bool => ! ( $s['hide_noindex'] && $node['noindex'] );

		$parents = [];
		if ( in_array( 'parents', $show, true ) ) {
			foreach ( Graph::parents( $ref ) as $parent ) {
				$node = Graph::node( $parent );
				if ( null !== $node && $keep( $node ) ) {
					$parents[] = $node;
				}
			}
		}

		$children = [];
		if ( in_array( 'children', $show, true ) && Graph::is_pillar( $ref ) ) {
			$children = array_values( array_filter( Graph::children( $ref ), $keep ) );
			$max      = $limit ?: (int) $s['children_limit'];
			if ( $max > 0 ) {
				$children = array_slice( $children, 0, $max );
			}
		}

		// هم‌خوشه‌ها فقط وقتی خود صفحه فهرست زیرمجموعه ندارد (کادر شلوغ نشود)
		$siblings = [];
		if ( in_array( 'siblings', $show, true ) && ! $children ) {
			$max      = $limit ?: (int) $s['siblings_limit'];
			$siblings = array_values( array_filter( Graph::siblings( $ref, $max + 10 ), $keep ) );
			$siblings = array_slice( $siblings, 0, $max );
		}

		return [ 'parents' => $parents, 'children' => $children, 'siblings' => $siblings ];
	}

	/** @return list<string> */
	private static function show_list( string $show ): array {

		if ( '' !== trim( $show ) ) {
			return array_values( array_intersect(
				array_map( 'trim', explode( ',', strtolower( $show ) ) ),
				[ 'parents', 'children', 'siblings' ]
			) );
		}

		$s    = Settings::all();
		$list = [ 'parents' ];
		if ( $s['show_children'] ) {
			$list[] = 'children';
		}
		if ( $s['show_siblings'] ) {
			$list[] = 'siblings';
		}
		return $list;
	}

	/** @param array<string, mixed> $opts */
	public static function box( Ref $ref, array $opts = [] ): string {

		$data = self::sections( $ref, $opts );

		if ( ! $data['parents'] && ! $data['children'] && ! $data['siblings'] ) {
			return self::debug( 'No parents, children or siblings' );
		}

		wp_enqueue_style( self::HANDLE );

		$s       = Settings::all();
		$uid     = 'hodima-tc-' . ( ++self::$instance );
		$heading = in_array( $opts['heading'] ?? '', Settings::HEADINGS, true ) ? (string) $opts['heading'] : (string) $s['heading'];
		$layout  = in_array( $opts['layout'] ?? '', Settings::LAYOUTS, true ) ? (string) $opts['layout'] : (string) $s['layout'];
		$self    = Graph::node( $ref );

		$html = sprintf(
			'<nav class="hodima-tc hodima-tc-unified-box hodima-tc--%1$s" aria-label="%2$s" data-nosnippet>',
			esc_attr( $layout ),
			esc_attr( null !== $self ? 'خوشه محتوایی: ' . $self['title'] : 'خوشه محتوایی' )
		);

		if ( $data['parents'] ) {
			$html .= self::group( $uid . '-parents', 'parents', self::parents_label( $data['parents'] ), $heading, array_map(
				static function ( array $node ): string {
					// لنگر فقط وقتی صفحه والد واقعا کادر دارد (وگرنه به جایی نمی‌رسد)
					$ref  = new Ref( Kind::from( $node['kind'] ), (int) $node['id'] );
					$href = $node['url'] . ( self::has_box( $ref ) ? '#' . self::ANCHOR : '' );
					return sprintf( '<a class="hodima-tc__chip hodima-tc-parent hodima-tc-parent-btn" href="%s">%s</a>', esc_url( $href ), esc_html( $node['title'] ) );
				},
				$data['parents']
			) );
		}

		if ( $data['children'] ) {
			self::$anchor_owner ??= $ref->key();
			$id = self::$anchor_owner === $ref->key() ? self::ANCHOR : '';

			$html .= self::group( $uid . '-children', 'children', (string) $s['label_children'], $heading, array_map(
				static fn( array $node ): string => sprintf( '<a class="hodima-tc__link hodima-tc-child hodima-tc-child-link" href="%s">%s</a>', esc_url( $node['url'] ), esc_html( $node['title'] ) ),
				$data['children']
			), $id );
		}

		if ( $data['siblings'] ) {
			$html .= self::group( $uid . '-siblings', 'siblings', (string) $s['label_siblings'], $heading, array_map(
				static fn( array $node ): string => sprintf( '<a class="hodima-tc__link hodima-tc-sibling" href="%s">%s</a>', esc_url( $node['url'] ), esc_html( $node['title'] ) ),
				$data['siblings']
			) );
		}

		return $html . '</nav>';
	}

	/** @param list<string> $links */
	private static function group( string $id, string $name, string $label, string $heading, array $links, string $anchor = '' ): string {

		$legacy = [ 'parents' => 'hodima-tc-parents-section', 'children' => 'hodima-tc-children-section', 'siblings' => 'hodima-tc-siblings-section' ][ $name ];
		$list   = 'parents' === $name ? 'hodima-tc__chips' : 'hodima-tc__links';

		$out = sprintf(
			'<div class="hodima-tc__group hodima-tc__group--%1$s hodima-tc-section %2$s"%3$s>',
			esc_attr( $name ),
			esc_attr( $legacy ),
			'' !== $anchor ? ' id="' . esc_attr( $anchor ) . '"' : ''
		);

		if ( '' !== $label ) {
			$out .= sprintf( '<%1$s class="hodima-tc__title hodima-tc-header" id="%2$s">%3$s</%1$s>', $heading, esc_attr( $id ), esc_html( $label ) );
		}

		$out .= sprintf( '<ul class="%s"%s>', $list, '' !== $label ? ' aria-labelledby="' . esc_attr( $id ) . '"' : '' );
		foreach ( $links as $link ) {
			$out .= '<li>' . $link . '</li>';
		}

		return $out . '</ul></div>';
	}

	/**
	 * عنوان بخش والدها. نسخه قبلی برای برگه‌ها هم «دسته‌بندی‌های مرجع»
	 * می‌نوشت در حالی که والد برگه، برگه است.
	 *
	 * @param list<array<string, mixed>> $parents
	 */
	private static function parents_label( array $parents ): string {

		$custom = (string) Settings::get( 'label_parents' );
		if ( '' !== $custom ) {
			return $custom;
		}

		$kinds = array_unique( array_column( $parents, 'kind' ) );

		return match ( true ) {
			[ 'term' ] === array_values( $kinds ) => count( $parents ) > 1 ? 'دسته‌بندی‌های مرجع' : 'دسته‌بندی مرجع',
			[ 'post' ] === array_values( $kinds ) => 'راهنمای مرجع',
			default                               => 'مرجع‌های این مطلب',
		};
	}

	/* =================================================================
	 * نمایش خودکار
	 * ================================================================= */

	/** بعد از محتوای نوشته/برگه/محصول (اولویت ۱۲: بعد از do_shortcode). */
	public static function auto_content( mixed $content ): mixed {

		if ( ! is_string( $content ) || is_admin() || is_feed() || ! is_singular() || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}

		$id = (int) get_the_ID();
		if ( ! $id || $id !== (int) get_queried_object_id() ) {
			return $content;
		}

		$ref = Ref::post( $id );
		if ( ! Graph::is_member( $ref ) || ! Settings::auto_insert( self::place( $ref ) ) || str_contains( $content, self::MARKER ) ) {
			return $content;
		}

		$box = self::box( $ref );
		if ( '' === $box || str_starts_with( $box, '<!--' ) ) {
			return $content;
		}

		return self::insert_after_paragraph( $content, $box, (int) Settings::get( 'paragraph' ) );
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
	 * باگ نسخه قبلی: add_filter( 'term_description', 'do_shortcode' ) روی
	 * توضیح *همه* دسته‌ها در *همه* جا اجرا می‌شد: داخل <head> (توضیح متا و
	 * اسکیمای ویدیو)، فید، REST و هر ابزارکی که توضیح دسته دیگری را چاپ
	 * می‌کرد. کادر خوشه در آن جاها یا متن «دسته‌بندی‌های مرجع: …» را وارد
	 * توضیح می‌کرد یا خوشه صفحه جاری را زیر دسته‌ای دیگر می‌گذاشت.
	 *
	 * حالا کادر خوشه فقط در بدنه صفحه همان دسته ساخته می‌شود و جاهای دیگر
	 * شورت‌کدش حذف می‌شود. بقیه شورت‌کدهای توضیح دسته مثل قبل اجرا می‌شوند
	 * (محتوای فعلی سایت به آن وابسته است).
	 */
	public static function term_description( mixed $value, mixed $term_id = 0, mixed $taxonomy = '', mixed $context = 'display' ): mixed {

		if ( ! is_string( $value ) ) {
			return $value;
		}

		$queried = get_queried_object();
		$body    = 'display' === $context && ! is_admin() && ! doing_action( 'wp_head' ) && ! doing_action( 'wp_footer' ) && ! is_feed() && ! wp_is_json_request();
		$match   = $body && $queried instanceof WP_Term && (int) $queried->term_id === (int) $term_id
			&& in_array( $queried->taxonomy, Graph::taxonomies(), true );

		if ( ! $match ) {
			if ( '' !== $value && has_shortcode( $value, self::TAG ) ) {
				$value = (string) preg_replace( '/' . get_shortcode_regex( [ self::TAG ] ) . '/', '', $value );
			}
			return '' === $value ? $value : do_shortcode( $value );
		}

		self::$forced = Ref::term( (int) $term_id );

		try {
			$value = '' === $value ? $value : do_shortcode( $value );

			// قالب توضیح دسته را فقط در صفحه اول آرشیو نشان می‌دهد
			if ( Settings::auto_insert( 'term' ) && ! is_paged() && ! str_contains( $value, self::MARKER ) ) {
				$box = self::box( self::$forced );
				if ( '' !== $box && ! str_starts_with( $box, '<!--' ) ) {
					$value .= $box;
				}
			}
		} finally {
			self::$forced = null;
		}

		return $value;
	}

	/**
	 * شورت‌کد در توضیح کوتاه محصول. ووکامرس خودش do_shortcode را روی این
	 * فیلتر دارد؛ نسخه قبلی یک بار دیگر هم اضافه می‌کرد و شورت‌کد گریزشده
	 * ([[x]]) در اجرای دوم اجرا می‌شد. حالا فقط اگر نبود اضافه می‌شود.
	 */
	public static function short_description(): void {
		if ( false === has_filter( 'woocommerce_short_description', 'do_shortcode' ) ) {
			add_filter( 'woocommerce_short_description', 'do_shortcode', 11 );
		}
	}

	/* =================================================================
	 * دارایی‌ها
	 * ================================================================= */

	/**
	 * استایل در <head> فقط وقتی صفحه کادر دارد (نسخه قبلی یا روی همه
	 * صفحه‌ها لود می‌شد یا دیر و در فوتر).
	 */
	public static function assets(): void {

		wp_register_style( self::HANDLE, TOPICCLUSTER_URL . 'assets/style.css', [], TOPICCLUSTER_VERSION );

		$ref = self::current();
		if ( null !== $ref && ( ! $ref->is_term() || ! is_paged() ) && self::has_box( $ref ) ) {
			wp_enqueue_style( self::HANDLE );
		}
	}

	/** کامنت اشکال‌زدایی فقط در حالت دیباگ (نه برای بازدیدکننده و ربات). */
	private static function debug( string $message ): string {
		return ( defined( 'WP_DEBUG' ) && WP_DEBUG ) ? '<!-- Hodima TC: ' . esc_html( $message ) . " -->\n" : '';
	}
}
