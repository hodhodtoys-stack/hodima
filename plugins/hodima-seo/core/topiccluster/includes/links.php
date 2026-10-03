<?php
/**
 * خوشه موضوعی — فهرست لینک‌های داخلی متن
 * Path: core/topiccluster/includes/links.php
 *
 * جدول {prefix}hodima_tc_links: یک ردیف به ازای هر «صفحه مبدأ → صفحه مقصد»
 * که داخل *متن* (محتوای نوشته یا توضیح دسته) لینک داده شده است. کادر خوشه،
 * منو، فوتر و کادرهای خودکار حساب نمی‌شوند؛ هدف دقیقا همین است: لینک داخل
 * متن ارزش سئوی بیشتری از لینک ناوبری دارد و گزارش‌های این ماژول باید
 * بدانند مقاله واقعا به پیلارش لینک داده یا نه.
 *
 * چرا جدول و نه متا: پرسش اصلی «چه کسی به این صفحه لینک داده؟» است؛ با
 * متای سریالایزشده فقط با LIKE روی کل postmeta جواب داده می‌شد.
 *
 * ساخت:
 *   - هر ذخیره نوشته منتشرشده یا ویرایش دسته، ردیف‌های همان مبدأ را از نو
 *     می‌سازد؛ خارج شدن از انتشار یا حذف، ردیف‌هایش را پاک می‌کند.
 *   - ساخت اولیه برای محتوای موجود: دسته‌ای در پس‌زمینه (WP-Cron، ۸۰ مورد در
 *     هر اجرا) و دکمه «بازسازی» در «خوشه‌بندی ← سلامت».
 */

declare(strict_types=1);

namespace Hodima\TopicCluster;

use WP_Post;
use WP_Term;

defined( 'ABSPATH' ) || exit;

final class Links {

	public const TABLE        = 'hodima_tc_links';
	private const DB_VERSION  = '1';
	private const DB_OPTION   = 'hodima_tc_links_db';
	private const STATE       = 'hodima_tc_links_state';
	public const CRON         = 'hodima_tc_links_batch';
	private const BATCH       = 80;

	/** @var array<string, Ref|null> */
	private static array $resolved = [];

	public static function init(): void {
		add_action( 'save_post', [ self::class, 'on_save_post' ], 20, 2 );
		add_action( 'transition_post_status', [ self::class, 'on_status' ], 10, 3 );
		add_action( 'delete_post', [ self::class, 'on_delete_post' ] );
		add_action( 'created_term', [ self::class, 'on_term' ], 20, 3 );
		add_action( 'edited_term', [ self::class, 'on_term' ], 20, 3 );
		add_action( 'delete_term', [ self::class, 'on_delete_term' ], 10, 3 );
		add_action( self::CRON, [ self::class, 'batch' ] );
		add_action( 'admin_init', [ self::class, 'maybe_install' ] );
	}

	public static function table(): string {
		global $wpdb;
		return $wpdb->prefix . self::TABLE;
	}

	public static function ready(): bool {
		return self::DB_VERSION === (string) get_option( self::DB_OPTION, '' );
	}

	/* =================================================================
	 * نصب و ساخت اولیه
	 * ================================================================= */

	public static function maybe_install(): void {

		if ( self::ready() || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		self::install();
		self::rebuild();
	}

	public static function install(): void {

		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table   = self::table();
		$charset = $wpdb->get_charset_collate();

		dbDelta( "CREATE TABLE {$table} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  source_kind varchar(4) NOT NULL DEFAULT '',
  source_id bigint(20) unsigned NOT NULL DEFAULT 0,
  target_kind varchar(4) NOT NULL DEFAULT '',
  target_id bigint(20) unsigned NOT NULL DEFAULT 0,
  anchor varchar(191) NOT NULL DEFAULT '',
  PRIMARY KEY  (id),
  KEY source (source_kind,source_id),
  KEY target (target_kind,target_id)
) {$charset};" );

		update_option( self::DB_OPTION, self::DB_VERSION, false );
	}

	/** شروع ساخت دوباره کل فهرست در پس‌زمینه. */
	public static function rebuild(): void {
		update_option( self::STATE, [ 'phase' => 'post', 'cursor' => 0, 'done' => 0, 'started' => time(), 'built' => 0 ], false );
		self::schedule();
	}

	private static function schedule(): void {
		if ( ! wp_next_scheduled( self::CRON ) ) {
			wp_schedule_single_event( time() + 5, self::CRON );
		}
	}

	/** @return array{phase:string, cursor:int, done:int, started:int, built:int} */
	public static function state(): array {
		$state = get_option( self::STATE, [] );
		return array_merge( [ 'phase' => 'none', 'cursor' => 0, 'done' => 0, 'started' => 0, 'built' => 0 ], is_array( $state ) ? $state : [] );
	}

	/** آیا فهرست کامل است (گزارش‌های لینک قابل اعتمادند)؟ */
	public static function complete(): bool {
		return self::ready() && 'complete' === self::state()['phase'];
	}

	/**
	 * یک دسته از ساخت اولیه. خروجی: تعداد مبدأهای پردازش‌شده.
	 */
	public static function batch( int $size = self::BATCH ): int {

		global $wpdb;

		if ( ! self::ready() ) {
			return 0;
		}

		$state = self::state();
		$count = 0;

		if ( 'post' === $state['phase'] ) {

			$types = Graph::post_types();
			$in    = implode( ',', array_fill( 0, count( $types ), '%s' ) );
			$ids   = $wpdb->get_col( $wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_type IN ({$in}) AND ID > %d ORDER BY ID ASC LIMIT %d",
				...array_merge( $types, [ $state['cursor'], $size ] )
			) );

			if ( $ids ) {
				_prime_post_caches( array_map( 'intval', $ids ), false, false );
			}
			foreach ( $ids as $id ) {
				self::index( Ref::post( (int) $id ) );
				$state['cursor'] = (int) $id;
				++$count;
			}

			if ( count( $ids ) < $size ) {
				$state['phase']  = 'term';
				$state['cursor'] = 0;
			}

		} elseif ( 'term' === $state['phase'] ) {

			$terms = get_terms( [
				'taxonomy'   => array_values( array_filter( Graph::taxonomies(), 'taxonomy_exists' ) ),
				'hide_empty' => false,
				'orderby'    => 'term_id',
				'order'      => 'ASC',
				'number'     => $size,
				'fields'     => 'ids',
				// get_terms فیلتر «شناسه بزرگ‌تر از» ندارد؛ با exclude_tree نمی‌شود،
				// پس از offset روی ترتیب شناسه استفاده می‌شود.
				'offset'     => $state['cursor'],
			] );
			$terms = is_array( $terms ) ? $terms : [];

			foreach ( $terms as $id ) {
				self::index( Ref::term( (int) $id ) );
				++$count;
			}
			$state['cursor'] += count( $terms );

			if ( count( $terms ) < $size ) {
				$state['phase'] = 'complete';
				$state['built'] = time();
			}
		}

		$state['done'] += $count;
		update_option( self::STATE, $state, false );

		if ( in_array( $state['phase'], [ 'post', 'term' ], true ) ) {
			self::schedule();
		} else {
			Graph::touch(); // گزارش‌های وابسته به لینک تازه شوند
		}

		return $count;
	}

	/* =================================================================
	 * ساخت ردیف‌های یک مبدأ
	 * ================================================================= */

	public static function on_save_post( $post_id, $post ): void {

		if ( ! $post instanceof WP_Post || wp_is_post_revision( $post ) || wp_is_post_autosave( $post ) || ! self::ready() ) {
			return;
		}
		if ( ! in_array( $post->post_type, Graph::post_types(), true ) ) {
			return;
		}

		'publish' === $post->post_status ? self::index( Ref::post( $post->ID ) ) : self::forget( Ref::post( $post->ID ), false );
	}

	public static function on_status( $new, $old, $post ): void {
		if ( $new !== $old && 'publish' === $old && $post instanceof WP_Post && self::ready() ) {
			self::forget( Ref::post( $post->ID ), false );
		}
	}

	public static function on_delete_post( $post_id ): void {
		if ( self::ready() ) {
			self::forget( Ref::post( (int) $post_id ), true );
		}
	}

	public static function on_term( $term_id, $tt_id = 0, $taxonomy = '' ): void {
		if ( self::ready() && in_array( (string) $taxonomy, Graph::taxonomies(), true ) ) {
			self::index( Ref::term( (int) $term_id ) );
		}
	}

	public static function on_delete_term( $term_id, $tt_id = 0, $taxonomy = '' ): void {
		if ( self::ready() ) {
			self::forget( Ref::term( (int) $term_id ), true );
		}
	}

	/** حذف ردیف‌های یک مبدأ (و اگر $as_target، لینک‌هایی که به آن رسیده‌اند). */
	private static function forget( Ref $ref, bool $as_target ): void {

		global $wpdb;

		$wpdb->delete( self::table(), [ 'source_kind' => $ref->kind->value, 'source_id' => $ref->id ] );

		if ( $as_target ) {
			$wpdb->delete( self::table(), [ 'target_kind' => $ref->kind->value, 'target_id' => $ref->id ] );
		}
	}

	/** بازسازی ردیف‌های یک مبدأ از متن فعلی‌اش. */
	public static function index( Ref $source ): void {

		global $wpdb;

		self::forget( $source, false );

		foreach ( self::extract( self::text( $source ), $source ) as [ $target, $anchor ] ) {
			$wpdb->insert( self::table(), [
				'source_kind' => $source->kind->value,
				'source_id'   => $source->id,
				'target_kind' => $target->kind->value,
				'target_id'   => $target->id,
				'anchor'      => mb_substr( $anchor, 0, 190 ),
			], [ '%s', '%d', '%s', '%d', '%s' ] );
		}
	}

	private static function text( Ref $ref ): string {

		if ( $ref->is_term() ) {
			$term = get_term( $ref->id );
			return $term instanceof WP_Term ? (string) $term->description : '';
		}

		$post = get_post( $ref->id );
		return $post instanceof WP_Post ? $post->post_content . "\n" . $post->post_excerpt : '';
	}

	/**
	 * لینک‌های داخلی یک متن: [مقصد، متن لینک] — هر مقصد یک بار.
	 *
	 * @return list<array{0: Ref, 1: string}>
	 */
	public static function extract( string $html, ?Ref $self = null ): array {

		if ( '' === $html || ! str_contains( $html, 'href' ) ) {
			return [];
		}

		if ( ! preg_match_all( '#<a\s[^>]*?href\s*=\s*(["\'])(.*?)\1[^>]*>(.*?)</a>#is', $html, $m, PREG_SET_ORDER ) ) {
			return [];
		}

		$out = [];

		foreach ( $m as $match ) {
			$target = self::resolve( html_entity_decode( $match[2], ENT_QUOTES, 'UTF-8' ) );
			if ( null === $target || ( null !== $self && $target->is( $self ) ) || isset( $out[ $target->key() ] ) ) {
				continue;
			}
			$anchor                  = trim( (string) preg_replace( '/\s+/u', ' ', wp_strip_all_tags( $match[3] ) ) );
			$out[ $target->key() ] = [ $target, $anchor ];
		}

		return array_values( $out );
	}

	/** مسیر نرمال‌شده یک آدرس (بدون دامنه، پرس‌وجو و لنگر؛ رمزگشایی‌شده). */
	private static function path( string $url ): string {
		$path = (string) wp_parse_url( $url, PHP_URL_PATH );
		return trim( rawurldecode( $path ), '/' );
	}

	/** آدرس داخلی → گره، یا null (خارجی، فایل، صفحه ناشناخته). */
	public static function resolve( string $url ): ?Ref {

		$url = trim( $url );

		if ( '' === $url || str_starts_with( $url, '#' ) || preg_match( '#^(mailto|tel|javascript|data):#i', $url ) ) {
			return null;
		}

		$home = (string) wp_parse_url( home_url(), PHP_URL_HOST );
		$host = (string) wp_parse_url( $url, PHP_URL_HOST );

		if ( '' !== $host && preg_replace( '/^www\./i', '', strtolower( $host ) ) !== preg_replace( '/^www\./i', '', strtolower( $home ) ) ) {
			return null;
		}

		$absolute = '' === $host ? home_url( '/' . ltrim( $url, '/' ) ) : $url;
		$path     = self::path( $absolute );

		if ( array_key_exists( $path, self::$resolved ) ) {
			return self::$resolved[ $path ];
		}

		// فایل‌ها (تصویر، PDF …) صفحه نیستند
		if ( '' === $path || preg_match( '#\.(jpe?g|png|gif|webp|avif|svg|pdf|zip|mp4|mp3|webm)$#i', $path ) || str_starts_with( $path, 'wp-content/' ) ) {
			return self::$resolved[ $path ] = null;
		}

		$matches = static function ( Ref $ref ) use ( $path ): bool {
			$node = Graph::node( $ref );
			return null !== $node && self::path( (string) $node['url'] ) === $path;
		};

		$post_id = url_to_postid( $absolute );
		if ( $post_id && $matches( Ref::post( $post_id ) ) ) {
			return self::$resolved[ $path ] = Ref::post( $post_id );
		}

		// مسیریاب آدرس تمیز (محصول/دسته بدون پایه) — از AEO اگر فعال است
		if ( class_exists( 'Hodima_AEO_Data' ) && method_exists( 'Hodima_AEO_Data', 'resolve_entity' ) ) {
			$type = '';
			$id   = (int) \Hodima_AEO_Data::resolve_entity( $absolute, $type );
			if ( $id > 0 && in_array( $type, [ 'post', 'term' ], true ) ) {
				$ref = new Ref( Kind::from( $type ), $id );
				if ( $matches( $ref ) ) {
					return self::$resolved[ $path ] = $ref;
				}
			}
		}

		// فالبک: آخرین بخش مسیر به عنوان نامک
		$parts = explode( '/', $path );
		$slug  = sanitize_title( (string) end( $parts ) );

		foreach ( get_posts( [ 'name' => $slug, 'post_type' => Graph::post_types(), 'post_status' => 'publish', 'posts_per_page' => 5, 'fields' => 'ids', 'suppress_filters' => true ] ) as $id ) {
			if ( $matches( Ref::post( (int) $id ) ) ) {
				return self::$resolved[ $path ] = Ref::post( (int) $id );
			}
		}

		foreach ( Graph::taxonomies() as $taxonomy ) {
			$term = taxonomy_exists( $taxonomy ) ? get_term_by( 'slug', $slug, $taxonomy ) : false;
			if ( $term instanceof WP_Term && $matches( Ref::term( (int) $term->term_id ) ) ) {
				return self::$resolved[ $path ] = Ref::term( (int) $term->term_id );
			}
		}

		return self::$resolved[ $path ] = null;
	}

	/* =================================================================
	 * پرسش‌ها
	 * ================================================================= */

	/** آیا متن $source به $target لینک داده؟ */
	public static function links_to( Ref $source, Ref $target ): bool {

		global $wpdb;

		if ( ! self::ready() ) {
			return false;
		}

		return (bool) $wpdb->get_var( $wpdb->prepare(
			'SELECT 1 FROM ' . self::table() . ' WHERE source_kind = %s AND source_id = %d AND target_kind = %s AND target_id = %d LIMIT 1',
			$source->kind->value,
			$source->id,
			$target->kind->value,
			$target->id
		) );
	}

	/**
	 * مقصدهای لینک‌شده از متن یک مبدأ (کلید Ref ← متن لینک).
	 *
	 * @return array<string, string>
	 */
	public static function outbound( Ref $source ): array {

		global $wpdb;

		if ( ! self::ready() ) {
			return [];
		}

		$rows = $wpdb->get_results( $wpdb->prepare(
			'SELECT target_kind, target_id, anchor FROM ' . self::table() . ' WHERE source_kind = %s AND source_id = %d',
			$source->kind->value,
			$source->id
		) );

		$out = [];
		foreach ( (array) $rows as $row ) {
			$out[ $row->target_kind . ':' . $row->target_id ] = (string) $row->anchor;
		}
		return $out;
	}

	/**
	 * تعداد مبدأهایی که به هر مقصد لینک داده‌اند.
	 *
	 * @param list<Ref> $targets
	 * @return array<string, int> کلید Ref ← تعداد
	 */
	public static function inbound_counts( array $targets ): array {

		global $wpdb;

		$out = [];
		foreach ( $targets as $ref ) {
			$out[ $ref->key() ] = 0;
		}

		if ( ! $targets || ! self::ready() ) {
			return $out;
		}

		foreach ( [ 'post', 'term' ] as $kind ) {

			$ids = array_map( static fn( Ref $r ): int => $r->id, array_filter( $targets, static fn( Ref $r ): bool => $r->kind->value === $kind ) );

			foreach ( array_chunk( array_values( $ids ), 500 ) as $chunk ) {
				$in   = implode( ',', array_map( 'intval', $chunk ) );
				$rows = $wpdb->get_results( $wpdb->prepare(
					'SELECT target_id, COUNT(*) AS n FROM ' . self::table() . " WHERE target_kind = %s AND target_id IN ({$in}) GROUP BY target_id",
					$kind
				) );
				foreach ( (array) $rows as $row ) {
					$out[ $kind . ':' . (int) $row->target_id ] = (int) $row->n;
				}
			}
		}

		return $out;
	}

	public static function total(): int {
		global $wpdb;
		return self::ready() ? (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . self::table() ) : 0;
	}
}
