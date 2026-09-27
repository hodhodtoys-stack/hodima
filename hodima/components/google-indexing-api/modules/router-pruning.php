<?php
/**
 * Crawl-budget URL pruning
 * Path: components/google-indexing-api/modules/router-pruning.php
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Hodima_Router_Pruning {

	private static ?self $instance = null;

	/** فیدهای پیش‌فرض وردپرس — فقط این‌ها ریدایرکت می‌شوند. */
	private const CORE_FEEDS = [ 'feed', 'rdf', 'rss', 'rss2', 'atom' ];

	private function __construct() {
		add_action( 'template_redirect', [ $this, 'prune_garbage_urls' ], 1 );
		add_filter( 'comment_reply_link', [ $this, 'remove_replytocom_parameter' ] );
	}

	public static function get_instance(): self {
		return self::$instance ??= new self();
	}

	public function prune_garbage_urls(): void {
		$this->handle_replytocom();
		$this->handle_attachments();
		$this->handle_feeds();
		$this->handle_pagination_base();
	}

	private function redirect( string $target ): void {
		if ( '' !== $target ) {
			wp_safe_redirect( $target, 301, 'Hodima Crawl Budget Optimizer' );
			exit;
		}
	}

	private function handle_replytocom(): void {
		if ( isset( $_GET['replytocom'] ) && is_singular() ) {
			$this->redirect( (string) get_permalink( get_queried_object_id() ) );
		}
	}

	private function handle_attachments(): void {
		if ( is_attachment() ) {
			$post = get_queried_object();
			$this->redirect( ( $post instanceof WP_Post && $post->post_parent ) ? (string) get_permalink( $post->post_parent ) : home_url( '/' ) );
		}
	}

	/**
	 * ریدایرکت فیدهای پیش‌فرض.
	 *
	 * باگ نسخه قبلی: *هر* فیدی را ریدایرکت می‌کرد. قالب یک فید سفارشی
	 * «podcast» با add_feed() ثبت کرده (schema/podcast-feed-core.php) که
	 * اپلیکیشن‌های پادکست از آن می‌خوانند؛ آن فید به صفحه اصلی ۳۰۱ می‌شد.
	 * همچنین get_term_link() بدون بررسی WP_Error به wp_safe_redirect داده
	 * می‌شد.
	 */
	private function handle_feeds(): void {

		if ( ! is_feed() ) {
			return;
		}

		$feed = (string) get_query_var( 'feed' );

		// فید سفارشی (پادکست و هر add_feed دیگر) دست‌نخورده می‌ماند
		if ( '' !== $feed && ! in_array( $feed, self::CORE_FEEDS, true ) ) {
			return;
		}

		if ( ! apply_filters( 'hodima_gi_redirect_core_feeds', true ) ) {
			return;
		}

		if ( is_singular() ) {
			$this->redirect( (string) get_permalink( get_queried_object_id() ) );
		}

		if ( is_category() || is_tax() || is_tag() ) {
			$link = get_term_link( get_queried_object() );
			$this->redirect( is_wp_error( $link ) ? home_url( '/' ) : (string) $link );
		}

		$this->redirect( home_url( '/' ) );
	}

	private function handle_pagination_base(): void {
		global $wp;
		$current = home_url( (string) $wp->request );
		if ( preg_match( '#/page/1/?$#i', $current ) ) {
			$this->redirect( (string) preg_replace( '#/page/1/?$#i', '/', $current ) );
		}
	}

	/**
	 * نسخه قبلی فقط href با تک‌کوتیشن را می‌گرفت؛ وردپرس مدرن لینک پاسخ
	 * را با دابل‌کوتیشن می‌سازد، پس جایگزینی هرگز انجام نمی‌شد.
	 */
	public function remove_replytocom_parameter( string $link ): string {
		return (string) preg_replace(
			'/href=(["\'])([^"\']*?)\?replytocom=(\d+)[^"\']*\1/',
			'href=$1$2#comment-$3$1',
			$link
		);
	}
}

Hodima_Router_Pruning::get_instance();
